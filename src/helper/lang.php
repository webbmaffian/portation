<?php

namespace Webbmaffian\MVC\Helper;

class Lang {
	const DEFAULT_LANG = 'en';
	
	static protected $setted_up = false;
	static protected $lang;
	static protected $strings;
	static protected $use_cache;
	static protected $translations_file;
	static protected $num_columns = null;
	
	// Cache
	static protected $done_setup = false;
	static protected $cache_path = '';
	
	static public function setup($cache = true) {
		if(self::$setted_up) return;

		if(!file_exists(Helper::root_dir() . '/data')) {
			mkdir(Helper::root_dir() . '/data', 0775, true);
		}

		if(!file_exists(Helper::root_dir() . '/data/lang-cache')) {
			mkdir(Helper::root_dir() . '/data/lang-cache', 0775, true);
		}

		self::$cache_path = Helper::root_dir() . '/data/lang-cache';
		self::$lang = Auth::get_lang() ?: self::DEFAULT_LANG;
		self::$use_cache = $cache;
		self::$translations_file = Helper::root_dir() . '/data/translations.csv';
		self::$setted_up = true;
		self::load_strings();
	}
	
	
	static protected function load_strings() {
		if(self::$lang === self::DEFAULT_LANG) {
			return;
		}
		
		if(!file_exists(self::$translations_file)) {
			file_put_contents(self::$translations_file, self::DEFAULT_LANG);
			
			self::$num_columns = 1;
		}
		
		$lang_slug = 'lang_' . self::$lang;
		
		if(!self::$use_cache || false === (self::$strings = self::cache_get($lang_slug, filemtime(self::$translations_file)))) {
			self::$strings = array();
			
			$rows = self::parse_csv(mb_convert_encoding(file_get_contents(self::$translations_file), 'UTF-8', 'Windows-1252'), ';');
			
			if(empty($rows)) {
				return;
			}
			
			$columns = array_shift($rows);
			
			
			
			if(false === ($key_key = array_search(self::DEFAULT_LANG, $columns))) {
				return;
			}
			
			$value_key = array_search(self::$lang, $columns);
			
			foreach($rows as $row) {
				if(!isset($row[$key_key]) || ($value_key !== false && !isset($row[$value_key]))) continue;
				
				self::$strings[$row[$key_key]] = ($value_key !== false ? $row[$value_key] : '');
			}
			
			if(self::$use_cache) {
				self::cache_set($lang_slug, self::$strings);
			}
		}
	}
	
	
	static public function get_string() {
		$args = func_get_args();
		$string = array_shift($args);
		
		if(self::$lang !== self::DEFAULT_LANG) {
			if(!empty(self::$strings[$string])) {
				$string = self::$strings[$string];
			}
			
			elseif(!isset(self::$strings[$string])) {
				if(empty($_ENV['DISABLE_LANG_CSV_WRITE'])) {
					if(is_null(self::$num_columns)) {
						self::$num_columns = sizeof(str_getcsv(fgets(fopen(self::$translations_file, 'r')), ';'));
					}
					
					file_put_contents(self::$translations_file, "\n" . '"' . str_replace('"', '""', mb_convert_encoding($string, 'Windows-1252', 'UTF-8')) . '"' . str_repeat(';', self::$num_columns - 1), FILE_APPEND);
				}
				
				self::$strings[$string] = '';
				
				self::cache_clear('lang_*');
			}
		}
		
		return (empty($args) ? $string : vsprintf($string, $args));
	}
	
	
	static public function exists($string) {
		return isset(self::$strings[$string]);
	}
	
	
	static public function get_language() {
		return self::$lang;
	}
	
	
	static public function parse_csv($csv_string, $delimiter = ",", $skip_empty_lines = true, $trim_fields = true) {
		return array_map(
			function ($line) use ($delimiter, $trim_fields) {
				return array_map(
					function ($field) {
						return str_replace('!!Q!!', '"', (urldecode($field)));
					},
					$trim_fields ? array_map('trim', explode($delimiter, $line)) : explode($delimiter, $line)
				);
			},
			preg_split(
				$skip_empty_lines ? ($trim_fields ? '/( *\R)+/s' : '/\R+/s') : '/\R/s',
				preg_replace_callback(
					'/"(.*?)"/s',
					function ($field) {
						return urlencode(($field[1]));
					},
					$enc = preg_replace('/(?<!")""/', '!!Q!!', $csv_string)
				)
			)
		);
	}


	/* Cache functions */
	
	static public function cache_set($key, $value) {
		self::setup();
		
		if(is_array($value)) {
			$value = json_encode($value);
		}
		
		return file_put_contents(self::$cache_path . '/' . $key, $value);
	}
	
	
	static public function cache_get($key, $timestamp = null) {
		self::setup();
		
		$path = self::$cache_path . '/' . $key;
		
		if(!file_exists($path)) return false;
		if($timestamp && filemtime($path) < $timestamp) return false;
		
		$value = file_get_contents($path);
		
		if(empty($value)) return null;
		
		if($value[0] === '[' || $value[0] === '{') {
			$value = json_decode($value, true);
		}
		
		return $value;
	}
	
	
	static public function cache_clear($pattern = '*') {
		self::setup();
		
		foreach(glob(self::$cache_path . '/' . ltrim($pattern, '.')) as $file) {
			$file = realpath($file);
			
			if(strncmp($file, self::$cache_path, strlen(self::$cache_path)) !== 0) {
				continue;
			}
			
			unlink($file);
		}
	}
}