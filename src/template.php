<?php
	namespace Webbmaffian\Portation;

	class Template extends Export {
		public function __construct($collection)  {
			if(!is_array($collection)) {
				throw new Problem('Expected an array of columns.');
			}

			$this->collection = $collection;
		}


		protected function get_spreadsheet($args = array()) {
			$args = Helper::default_args($args, array(
				'author' => null,
				'title' => 'ImportTemplate',
				'filetype' => 'xlsx',
				'data_types' => array()
			));
			
			if(is_null($args['author']) && Auth::is_signed_in()) {
				$args['author'] = Auth::get_name();
			}
			
			$spreadsheet = new PhpSpreadsheet\Spreadsheet();
			
			$spreadsheet->getProperties()
				->setCreator($args['author'])
				->setLastModifiedBy($args['author'])
				->setTitle($args['title']);
			
			$sheet = $spreadsheet->getActiveSheet();
			
			$columns = null;
			$row = 0;
			
			foreach($this->collection as $i => $column_name) {
				$sheet->setCellValueByColumnAndRow($i + 1, 1, $column_name);
			}
			
			return $spreadsheet;
		}
	}