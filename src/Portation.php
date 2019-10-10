<?php
	namespace Webbmaffian\Portation;
	use Webbmaffian\MVC\Helper\Helper;
	use Webbmaffian\MVC\Helper\Problem;


	abstract class Portation {
		private $errors = array();
		protected $stats = array();
		protected $callbacks = array();
		protected $hooks = array();


		// DEPRECATED
		public function register_callback($callback) {
			Helper::deprecated();

			if(!is_callable($callback)) return;

			$this->callbacks[] = $callback;
		}


		public function set_hook($name, $callback) {
			if(!is_callable($callback)) {
				throw new Problem(sprintf('Hook "%s" is not callable.', $name));
			}

			$this->hooks[$name] = $callback;
		}


		protected function action($name, ...$args) {
			if(isset($this->hooks[$name])) {
				call_user_func_array($this->hooks[$name], $args);
			}
		}


		protected function filter($name, ...$args) {
			if(isset($this->hooks[$name])) {
				$return = call_user_func_array($this->hooks[$name], $args);

				if(!is_null($args[0]) && gettype($return) !== gettype($args[0])) {
					throw new Problem(sprintf('Return of hook "%s" must be of type "%s".', $name, gettype($args[0])));
				}

				return $return;
			}

			return $args[0];
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