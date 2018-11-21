<?php

namespace Webbmaffian\MVC\Helper;

class Value {
	static protected $data = array();
	
	
	static public function set($name, $value = '') {
		if(is_object($name)) {
			$name = (array)$name;
		}
		
		if(is_array($name)) {
			foreach($name as $key => $val) {
				self::$data[$key] = $val;
			}
		}
		else {
			self::$data[$name] = $value;
		}
	}
	
	
	static public function get($name = '', $fallback = '') {
		if(empty($name)) return $fallback;
		
		return (isset(self::$data[$name]) ? self::$data[$name] : $fallback);
	}
	
	
	static public function get_from_array($str = '', $fallback = '') {
		$c = function($v, $w) {return $w ? $v[$w] : $v;};
		
		return array_reduce(preg_split('~\[|\]~', $str), $c, self::$data);
	}
}