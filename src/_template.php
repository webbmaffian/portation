<?php
	namespace Webbmaffian\Portation;

	use Webbmaffian\MVC\Helper\Helper;
	use Webbmaffian\MVC\Helper\Problem;
	use Webbmaffian\MVC\Helper\Auth;
	use PhpOffice\PhpSpreadsheet;

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
			$col = 1;
			
			foreach($this->collection as $i => $column_name) {
				if($column_name === 'id') continue;
				$sheet->setCellValueByColumnAndRow($col, 1, $column_name);
				$col++;
			}
			
			return $spreadsheet;
		}
	}