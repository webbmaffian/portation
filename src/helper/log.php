<?php

namespace Webbmaffian\MVC\Helper;

class Log {
	static private $log_dir = null;
	
	static public function __callStatic($name, $args) {
		$name = str_replace('_', '-', strtolower($name));
		$now = Helper::date('now');
		if(is_null(self::$log_dir)) {
			self::$log_dir = Helper::root_dir() . '/data/logs';
			
			if(!file_exists(self::$log_dir)) {
				mkdir(self::$log_dir, 0755, true);
			}
		}
		
		return file_put_contents(self::$log_dir . '/' . $name . '.log', $now->format('Y-m-d @ H:i:s') . ' - ' . implode(' ', array_map(function($arg) {
			return (is_string($arg) ? $arg : var_export($arg, true));
		}, $args)) . "\n", FILE_APPEND);
	}
}