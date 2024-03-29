<?php

namespace Webbmaffian\Portation;

use Webbmaffian\MVC\Helper\Helper;
use Webbmaffian\MVC\Helper\Problem;
use Webbmaffian\MVC\Helper\Auth;
use Webbmaffian\MVC\Model\Model_Collection;
use PhpOffice\PhpSpreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Export extends Portation {
	protected $collection = null;


	public function __construct($collection)  {
		if(!$collection instanceof Model_Collection) {
			throw new Problem('Expected a Model Collection.');
		}

		$this->collection = $collection;
	}


	public function to_file($filepath, $args = array()) {
		try {
			if(!is_writeable(dirname($filepath))) {
				throw new Problem('File path is not writeable.');
			}

			$this->reset_stats();

			$spreadsheet = $this->get_spreadsheet($args);
			
			if(empty($args['filetype'])) {
				$args['filetype'] = Helper::get_file_extension($filepath);
			}
			
			$writer = IOFactory::createWriter($spreadsheet, ucfirst($args['filetype'] ?? ''));
			$writer->save($filepath);
			
			return true;
		}
		catch(\Exception $e) {
			if($e instanceof Problem) {
				throw $e;
			}
			
			throw new Problem($e->getMessage(), 0, $e);
		}
	}


	public function to_browser($filename, $args = array()) {
		try {
			if(headers_sent()) {
				throw new Problem('Can\'t output export after headers are sent.');
			}

			$this->reset_stats();

			$spreadsheet = $this->get_spreadsheet($args);

			if(empty($args['filetype'])) {
				$args['filetype'] = Helper::get_file_extension($filename);
			}
			
			// Redirect output to a client’s web browser (Xlsx)
			header('Content-Type: ' . $this->get_mime_type($args['filetype'] ?? ''));
			header('Content-Disposition: attachment;filename="' . $filename . '"');
			header('Cache-Control: max-age=0');

			$writer = PhpSpreadsheet\IOFactory::createWriter($spreadsheet, ucfirst($args['filetype'] ?? ''));
			$writer->save('php://output');
			
			exit;
		}
		catch(\Exception $e) {
			if($e instanceof Problem) {
				throw $e;
			}
			
			throw new Problem($e->getMessage(), 0, $e);
		}
	}


	protected function get_spreadsheet($args = array()) {
		$args = Helper::default_args($args, array(
			'author' => null,
			'title' => str_replace('_', ' ', Helper::get_class_name($this->collection)),
			'filetype' => 'xlsx',
			'data_types' => array(),
			'ignore_columns' => array(),
			'allow_empty' => false
		));
		
		if(is_null($args['author']) && Auth::is_signed_in()) {
			$args['author'] = Auth::get_name();
		}
		
		if(!empty($args['data_types'])) {
			foreach($args['data_types'] as $column => $type) {
				$new_type = @constant('PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_' . strtoupper($type));
				
				if(is_null($new_type)) {
					throw new Problem('Invalid data type: ' . $type);
				}
				
				$args['data_types'][$column] = $new_type;
			}
		}
		
		$spreadsheet = new Spreadsheet();
		
		$spreadsheet->getProperties()
		->setCreator($args['author'])
		->setLastModifiedBy($args['author'])
		->setTitle($args['title']);
		
		$sheet = $spreadsheet->getActiveSheet();
		
		/**
		 * @var array $columns
		 */
		$columns = $this->filter('export_columns', null, $this->collection);
		$row = 0;

		$collection_rows = $this->collection->get();

		if(empty($collection_rows)) {
			throw new Problem('Empty data set.');
		}
		
		foreach($collection_rows as $model) {
			$row++;
			$model_type = get_class($model);

			// DEPRECATED
			foreach($this->callbacks as $callback) {
				$callback($model);
			}

			$this->action('before_export', $model);

			$model_data = $this->filter('export_model_data', $model->get_data(), $model, $row);
			
			if(is_null($columns)) {
				$columns = array_keys($model_data);
			}

			if($row === 1) {
				if(!empty($args['ignore_columns'])) {
					$columns = array_diff($columns, $args['ignore_columns']);
				}

				foreach($columns as $x => $column) {
					$sheet->setCellValueByColumnAndRow($x + 1, $row, $column);
				}
				
				$row++;
			}
			
			foreach($columns as $x => $column) {
				if(!isset($model_data[$column])) continue;
				
				$col = $x + 1;
				
				if(isset($args['data_types'][$column])) {
					$sheet->setCellValueExplicitByColumnAndRow($col, $row, $model_data[$column], $args['data_types'][$column]);
				}
				else {
					$sheet->setCellValueByColumnAndRow($col, $row, $model_data[$column]);
				}
			}

			$this->stats['total']++;
		}
		
		return $spreadsheet;
	}
}
