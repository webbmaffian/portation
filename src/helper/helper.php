<?php

namespace Webbmaffian\MVC\Helper;

class Helper {
	
	static protected $controllers = array();
	static protected $timezone = NULL;


	static public function root_dir() {
		return dirname(getcwd());
	}


	static public function is_assoc($arr) {
		if(empty($arr)) return false;
		
		if(!is_array($arr)) throw new Problem('Input must be of type Array');

		$keys = array_keys($arr);
		if(is_string($keys[0])) return true;

		return false;
	}


	static public function match($pattern, $subject) {
		if(is_array($subject)) {
			$matches = preg_grep($pattern, $subject);
		} else {
			$subject = (string)$subject;
			preg_match($pattern, $subject, $matches);
		}

		return !empty($matches) ? reset($matches) : '';
	}


	static public function get_controller($controller) {
		if(!defined('ENDPOINT')) throw new Problem("ENDPOINT constant is not defined.");

		if(substr($controller, -11) !== '_Controller') {
			$controller .= '_Controller';
		}
		
		if(!isset(self::$controllers[$controller])) {
			$file_path = Helper::root_dir() . '/app/controllers/' . ENDPOINT . '/' . strtolower(str_replace('_', '-', $controller)) . '.php';

			if(!file_exists($file_path)) {
				throw new Problem('File path does not exist: ' . $file_path);
			}

			$classes = get_declared_classes();
			include($file_path);
			$diff = array_diff(get_declared_classes(), $classes);
			$class_name = Helper::match("/$controller/", $diff);
			
			if($class_name::MUST_SIGN_IN && !Auth::is_signed_in()) {
				self::go_out();
			}
			
			self::$controllers[$controller] = new $class_name();
		}

		return self::$controllers[$controller];
	}


	static public function go_out() {
		self::redirect('/auth/sign-in');
	}


	static public function get_referer($no_query = false) {
		$referer = parse_url($_SERVER['HTTP_REFERER']);
		$home = parse_url(self::get_url());

		if($referer['host'] !== $home['host']) {
			$url = self::get_url();
		} else {
			$url = $_SERVER['HTTP_REFERER'];
		}

		if($no_query) {
			$query = '?' . parse_url($url, PHP_URL_QUERY);
			$url = str_replace($query, '', $url);
		}

		return $url;
	}


	static public function append_to_url($url, $args) {
		$parts = parse_url($_SERVER['REQUEST_URI']);

		if(!empty($parts['query'])) {
			$query_arr = array();
			parse_str($parts['query'], $query_arr);
			$args = array_merge($query_arr, $args);
		}

		$args = http_build_query($args);
		return $parts['path'] . '?' . $args;
	}


	static public function get_url($path = '', $protocol = '') {
		if(!defined('ENDPOINT')) return '';
		
		$endpoint = strtoupper(ENDPOINT);

		if(!isset($_ENV[$endpoint . '_ENDPOINT'])) return '';

		$path = trim($path, " \t\n\r\0\x0B/");
		return ($protocol ? $protocol . '://' : '//') . $_ENV[$endpoint . '_ENDPOINT'] . '/' . $path;
	}
	
	
	static public function is_ssl() {
		return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
	}
	
	
	static public function get_timezone() {
		if(is_null(self::$timezone)) {
			$timezone_string = (isset($_ENV['TIMEZONE']) ? $_ENV['TIMEZONE'] : 'Europe/Stockholm');
			
			self::$timezone = new \DateTimeZone($timezone_string);
		}
		
		return self::$timezone;
	}


	static public function date($string, $convert_timezone = false) {
		try {
			if($string instanceof \DateTime) {
				return clone $string;
			}
			
			if(!$convert_timezone) {
				return new \DateTime($string, self::get_timezone());
			}
			
			$date = new \DateTime($string);
			$date->setTimezone(self::get_timezone());
			
			return $date;
		} catch(\Exception $e) {
			throw new Problem($e->getMessage());
		}
	}
	

	static public function deprecated() {
		if(version_compare(PHP_VERSION, '5.4.0') >= 0) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		} elseif(version_compare(PHP_VERSION, '5.3.6') >= 0) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		} else {
			$trace = debug_backtrace();
		}
		
		$caller = $trace[1];
		
		error_log('Deprecated function ' . $caller['class'] . $caller['type'] . $caller['function'] . ' used at ' . substr($caller['file'], strlen(ABSPATH) - 1) . ':' . $caller['line']);
	}
	

	static public function send_json($data = array()) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
		exit;
	}
	
	
	static public function default_args($args = array(), $default = array()) {
		return array_merge($default, $args);
	}
	
	
	static public function output_csv($array, $delimiter = ',', $enclosure = '"', $eol = "\n") {
		if(!is_array($array)) {
			$array = array($array);
		}
		
		foreach($array as $subarray) {
			if(!is_array($subarray)) {
				$subarray = array($subarray);
			}
			
			echo implode($delimiter, $subarray) . $eol;
		}
	}
	
	
	static public function get_current_url() {
		return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	}
	
	
	static public function redirect($url, $permanent = false) {
		header('Location: ' . $url, true, ($permanent ? 301 : 302));
		exit;
	}
	
	
	static public function get_class_name($object) {
		if(is_object($object)) {
			$object = get_class($object);
		}
		
		$name = explode('\\', $object);
		
		return array_pop($name);
	}
	
	
	static public function get_uploaded_file($file, $valid_file_types = null) {
		if(!is_array($file) || empty($file)) {
			throw new Problem('Invalid function argument.');
		}
		
		if(!is_array($valid_file_types)) {
			$valid_file_types = array('xls', 'xlsx', 'csv');
		}
		
		if($file['error'] !== UPLOAD_ERR_OK) {
			$errors = array(
				UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
				UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
				UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
				UPLOAD_ERR_NO_FILE => 'No file was uploaded',
				UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
				UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
				UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
			);
			
			if(isset($errors[$file['error']])) {
				throw new Problem($errors[$file['error']]);
			}
			else {
				throw new Problem('Unknown upload error');
			}
		}
		
		if(empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
			throw new Problem('File was not uploaded properly.');
		}
		
		if($file['size'] <= 0) {
			throw new Problem('Empty file.');
		}
		
		if(empty($file['name'])) {
			throw new Problem('Empty file name.');
		}
		
		if(empty($file['type']) || $file['type'] !== mime_content_type($file['tmp_name'])) {
			throw new Problem('Mismatching mime type.');
		}
		
		if(!in_array(self::get_file_extension($file['name']), $valid_file_types)) {
			throw new Problem('Only the following file extensions are allowed: ' . implode(', ', $valid_file_types));
		}
		
		return $file['tmp_name'];
	}
	
	
	static public function get_file_extension($filename) {
		return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
	}
	
	
	static public function get_page_title($title = '') {
		$return = $_ENV['APP_NAME'];

		if(!empty($title)) {
			$return = trim($title) . ' - ' . $return;
		}
		
		return $return;
	}
	
	
	static public function browser_download($file_path, $exit = true) {
		if(headers_sent()) {
			throw new Problem('Can not start download after headers are sent.');
		}
		
		if(!is_readable($file_path)) {
			throw new Problem('Can not find file.');
		}
		
		header('Content-Type: ' . mime_content_type($file_path));
		header('Content-Disposition: attachment; filename="' . basename($file_path) . '"'); 
		readfile($file_path);
		
		if($exit) exit;
	}


	//truncate a string only at a whitespace (by nogdog)
	static public function truncate($text, $length = 20) {
		if(strlen($text) > $length) {
			$text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '$1...', $text);
		}

		return $text;
	}
}