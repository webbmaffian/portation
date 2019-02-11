<?php
	namespace Webbmaffian\Portation;

	use Webbmaffian\ORM\DB;
	use Webbmaffian\MVC\Helper\Helper;
	use Webbmaffian\MVC\Helper\Problem;
	use PhpOffice\PhpSpreadsheet;

	class Import extends Portation {
		private $filename = null;
		private $class_name = null;


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
			try {
				$db = DB::instance();
				$this->reset_errors();
				$this->reset_stats();
				
				if(!isset($args['file_type'])) {
					$args['file_type'] = Helper::get_file_extension($this->filename);
				}
				
				$reader = PhpSpreadsheet\IOFactory::createReader(ucfirst($args['file_type']));
				$spreadsheet = $reader->load($this->filename);
				$sheet = $spreadsheet->getActiveSheet();
				$columns = null;
				$primary_key = $this->class_name::PRIMARY_KEY;
				$is_auto_increment = $this->class_name::IS_AUTO_INCREMENT;
				
				$db->start_transaction();
				
				foreach($sheet->getRowIterator() as $row_num => $row) {
					try {
						$cell_iterator = $row->getCellIterator();
						$cell_iterator->setIterateOnlyExistingCells(false);
						
						// First row should always contain column names
						if(is_null($columns)) {
							$columns = array();
							
							foreach($cell_iterator as $col => $cell) {
								$column_name = $cell->getValue();
								
								if(empty($column_name)) continue;
								
								$columns[$col] = $column_name;
							}
							
							continue;
						}

						$model_fields = $this->class_name::get_column_names();
						$meta_fields = array_diff($columns, $model_fields);
						$model_columns = array_filter($columns, function($e) use ($meta_fields) { return !in_array($e, $meta_fields); });
						
						$model_data = array();
						$meta_data = array();
						
						foreach($cell_iterator as $col => $cell) {
							if(isset($model_columns[$col])) {
								$model_data[$model_columns[$col]] = trim($cell->getValue());
							} elseif(isset($meta_fields[$col])) {
								$meta_data[$meta_fields[$col]] = trim($cell->getValue());
							}
						}
						
						// Skip row if all columns are empty
						if(count(array_filter($model_data)) === 0) {
							continue;
						}
						
						$this->stats['total']++;
						
						$create = false;
						
						if(empty($model_data)) {
							throw new Problem('Empty or invalid data.');
						}

						if(isset($args['overrides']) && is_array($args['overrides'])) {
							foreach($args['overrides'] as $key => $value) {
								$model_data[$key] = $value;
							}
						}
						
						// Primary key is set
						if(!empty($model_data[$primary_key])) {
							try {
								
								// Try to fetch the model by primary key (if it doesn't exist it might be created in the catch block)
								$model = $this->class_name::get_by_id($model_data[$primary_key]);
								
								// We won't update the primary key, as it is the unique identifier - unset it
								unset($model_data[$primary_key]);
								
								// Update model
								$model->update($model_data);
								$this->stats['updated']++;
							}
							catch(Problem $e) {
								
								// If it is an auto-increment model, abort and throw the problem further
								if($is_auto_increment) throw $e;
								
								// If we arrived here, the model doesn't exist and should be created further down
								$create = true;
							}
						}
						
						// Primary key is not set
						else {
							$create = true;
						}
						
						// Create new model
						if($create) {
							$model = $this->class_name::create($model_data);
							$this->stats['created']++;
						}

						foreach($this->callbacks as $callback) {
							$callback($model, $meta_data);
						}
					}
					catch(Problem $e) {
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
	}