<?php
	namespace Webbmaffian\Portation;

 	use Webbmaffian\MVC\Helper\Problem;


	abstract class Portation {
		private $errors = array();
		protected $stats = array();
		protected $callbacks = array();


		protected function register_callback($callback) {
			if(!is_callable($callback)) return;

			$this->callbacks[] = $callback;
		}
		
		
		protected function add_error($error) {
			$this->errors[] = $error;
		}


		protected function reset_errors() {
			$this->errors = array();
		}


		protected function reset_stats() {
			$this->stats = array(
				'total' => 0,
				'created' => 0,
				'updated' => 0,
				'failed' => 0
			);
		}


		public function get_errors() {
			return $this->errors;
		}


		public function get_total() {
			return $this->stats['total'];
		}


		public function get_created() {
			return $this->stats['created'];
		}


		public function get_updated() {
			return $this->stats['updated'];
		}


		public function get_failed() {
			return $this->stats['failed'];
		}


		public function get_mime_type($extension) {
			$extension = strtolower($extension);
			$types = array(
				'xls' => 'application/vnd.ms-excel',
				'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
				'csv' => 'text/csv',
				'html' => 'text/html',
				'pdf' => 'application/pdf'
			);

			if(!isset($types[$extension])) throw new Problem('Unknown file type.');

			return $types[$extension];
		}
	}