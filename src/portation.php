<?php
	namespace Webbmaffian\Portation;

	abstract class Portation {
		private $errors = array();
		protected $stats = array();


		public function get_errors() {
			return $this->errors;
		}
		
		
		protected function add_error($error) {
			$this->errors[] = $error;
		}


		protected function reset_errors() {
			$this->errors = array();
		}


		private function reset_stats() {
			$this->stats = array(
				'total' => 0,
				'created' => 0,
				'updated' => 0,
				'failed' => 0
			);
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
	}