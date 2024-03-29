<?php
	namespace Webbmaffian\Portation;

	use Webbmaffian\ORM\DB;
	use Webbmaffian\MVC\Helper\Helper;
	use Webbmaffian\MVC\Helper\Sanitize;
	use Webbmaffian\MVC\Helper\Problem;
	use PhpOffice\PhpSpreadsheet;

	class Import extends Portation {
		protected $filename = null;
		protected $class_name = null;
		protected $file_type = null;
		protected $identifier = null;
		protected $is_auto_increment = false;
		protected $model_data_parser = null;
		protected $meta_data_parser = null;


		public function __construct($filename, $class_name) {
			if(!is_readable($filename)) {
				throw new Problem('Can not read file - does it exist?');
			}

			if(!class_exists($class_name)) {
				throw new Problem('Class ' . $class_name . ' does not exist.');
			}

			if(!is_subclass_of($class_name, 'Webbmaffian\MVC\Model\Model')) {
				throw new Problem('Class ' . $class_name . ' must extend Model.');
			}

			$this->filename = $filename;
			$this->class_name = $class_name;
		}


		public function run($args = array()) {
			$db = DB::instance();
			$this->reset_errors();
			$this->reset_stats();
			
			$sheet = $this->get_sheet($args);
			$columns = null;
			$use_savepoints = method_exists($db, 'add_savepoint');

			// If no identifier is set, we'll use the model's primary key
			$identifier = ($this->identifier ?: $this->class_name::PRIMARY_KEY);
			$is_auto_increment = ($this->identifier ? $this->is_auto_increment : $this->class_name::IS_AUTO_INCREMENT);
			$model_fields = array_flip($this->filter('import_model_fields', $this->class_name::get_column_names(), $this->class_name));

			$this->action('before_import', $sheet);

			try {
				$db->start_transaction();
				
				foreach($sheet->getRowIterator() as $row_num => $row) {
					try {
						$this->action('before_import_row', $row, $row_num);

						if($use_savepoints) {
							$db->add_savepoint();
						}

						$cell_iterator = $row->getCellIterator();
						$cell_iterator->setIterateOnlyExistingCells(false);
						
						// First row should always contain column names
						if(is_null($columns)) {
							$columns = $this->get_columns_by_iterator($cell_iterator);
							
							continue;
						}

						$model_data = array();
						$meta_data = array();
						
						foreach($cell_iterator as $col => $cell) {
							if(!isset($columns[$col])) continue;

							if(isset($model_fields[$columns[$col]])) {
								$model_data[$columns[$col]] = trim($cell->getValue());
							}
							else {
								$meta_data[$columns[$col]] = trim($cell->getValue());
							}
						}

						// DEPRECATED
						if(!is_null($this->model_data_parser)) {
							$model_data = call_user_func($this->model_data_parser, $model_data, $row, $row_num);

							if(!is_array($model_data)) {
								throw new Problem('Model data parser does not return an array.');
							}
						}

						// DEPRECATED
						if(!is_null($this->meta_data_parser)) {
							$meta_data = call_user_func($this->meta_data_parser, $meta_data, $row, $row_num);

							if(!is_array($meta_data)) {
								throw new Problem('Meta data parser does not return an array.');
							}
						}

						$model_data = $this->filter('import_model_data', $model_data, $meta_data, $row, $row_num);
						$meta_data = $this->filter('import_meta_data', $meta_data, $model_data, $row, $row_num);
						
						// Skip row if all columns are empty
						if($this->filter('import_skip_row', count(array_filter($model_data)) === 0, $model_data, $meta_data, $row, $row_num)) {
							continue;
						}
						
						$this->stats['total']++;
						
						if(empty($model_data)) {
							throw new Problem('Empty or invalid data.');
						}

						if(isset($args['overrides']) && is_array($args['overrides'])) {
							foreach($args['overrides'] as $key => $value) {
								$model_data[$key] = $value;
							}
						}

						/** 
						 * Update model if it exists
						 * @var \Webbmaffian\MVC\Model\Model $model
						 */
						if($model = $this->get_model($model_data, $identifier, $is_auto_increment)) {
							$model->update($model_data);
							$created = false;
						}
						
						// Create model if it doesn't exist
						else {
							$model = $this->create_model($model_data, $identifier, $is_auto_increment);
							$created = true;
						}

						// DEPRECATED
						foreach($this->callbacks as $callback) {
							$callback($model, $meta_data);
						}

						$this->action('after_import', $model, $model_data, $meta_data, $row, $row_num, $created);
						$this->stats[($created ? 'created' : 'updated')]++;

						if($use_savepoints) {
							$db->release_savepoint();
						}
					}
					catch(\Exception $e) {
						if($use_savepoints) {
							$db->rollback_savepoint();
						}

						$this->stats['failed']++;
						$this->add_error('Row ' . $row_num . ': ' . $e->getMessage());
					}
				}
				
				$db->end_transaction();
			}
			catch(\Exception $e) {
				$db->rollback();
				
				if($e instanceof Problem) {
					throw $e;
				}
				
				throw new Problem($e->getMessage(), 0, $e);
			}
			
			return true;
		}


		public function set_identifier($identifier, $is_auto_increment = false) {
			$this->identifier = Sanitize::key($identifier);
			$this->is_auto_increment = (bool)$is_auto_increment;

			return $this;
		}


		public function set_file_type($file_type) {
			$this->file_type = Sanitize::key($file_type);

			return $this;
		}


		public function get_columns($args = array()) {
			$sheet = $this->get_sheet($args);

			foreach($sheet->getRowIterator() as $row_num => $row) {
				$cell_iterator = $row->getCellIterator();
				$cell_iterator->setIterateOnlyExistingCells(false);
				
				return $this->get_columns_by_iterator($cell_iterator);
			}

			return null;
		}


		protected function get_columns_by_iterator($iterator) {
			$columns = array();
			
			foreach($iterator as $col => $cell) {
				$column_name = trim($cell->getValue());
				
				if(empty($column_name)) continue;
				
				$columns[$col] = $column_name;
			}
			
			return $columns;
		}


		protected function get_sheet($args = array()) {
			$file_type = (isset($args['file_type']) ? $args['file_type'] : $this->file_type);

			if(empty($file_type)) {
				$file_type = Helper::get_file_extension($this->filename);
			}

			$reader = PhpSpreadsheet\IOFactory::createReader(ucfirst($file_type));
			$spreadsheet = $reader->load($this->filename);
			return $spreadsheet->getActiveSheet();
		}


		protected function get_model(&$data, $identifier, $is_auto_increment) {

			// Let any other code find the model
			$model = $this->filter('get_model', null, $data, $identifier, $is_auto_increment);

			if(!is_null($model)) {
				return $model;
			}

			// <- If we came here, no other code tried to find the code for us.

			if(empty($data[$identifier])) {

				// We can only accept auto-increment identifiers here, as we can't have an empty identifier
				if(!$is_auto_increment) {
					throw new Problem(sprintf('Empty identifier "%s".', $identifier));
				}
				
				return false;
			}

			// Ensure we have a method for getting a model by identifier
			if(!is_callable(array($this->class_name, 'get_by_' . $identifier))) {
				throw new Problem(sprintf('Missing "get_by_%s" method on %s.', $identifier, $this->class_name));
			}
			
			// Try to fetch the model by identifier
			try {
				$model = $this->class_name::{'get_by_' . $identifier}($data[$identifier]);
			}

			// If we catch an exception, it means that it doesn't exist
			catch(\Exception $e) {

				// If not auto-increment, it's okay if it doesn't exist
				if(!$is_auto_increment) return false;

				// Otherwise, the user is doing something wrong
				throw $e;
			}

			// We won't update the identifier - unset it
			unset($data[$identifier]);

			return $model;
		}


		protected function create_model($data, $identifier, $is_auto_increment) {

			// Let any other code create the model
			$model = $this->filter('create_model', null, $data, $identifier, $is_auto_increment);

			if(is_null($model)) {
				$model = $this->class_name::create($data);
			}

			return $model;
		}


		// -- DEPRECATED METHODS --------------------------------------------------------------------------------------------


		// DEPRECATED
		public function set_model_data_parser($callback) {
			Helper::deprecated();

			if(!is_callable($callback)) {
				throw new Problem('Data parser is not callable.');
			}

			$this->model_data_parser = $callback;

			return $this;
		}


		// DEPRECATED
		public function set_meta_data_parser($callback) {
			Helper::deprecated();

			if(!is_callable($callback)) {
				throw new Problem('Data parser is not callable.');
			}

			$this->model_data_parser = $callback;

			return $this;
		}
	}
