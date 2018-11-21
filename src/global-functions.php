<?php

	if(!function_exists('__')) {
		function __() {
			Webbmaffian\MVC\Helper\Lang::setup();
			return call_user_func_array(array('Autorelations\Lang', 'get_string'), func_get_args());
		}
	}
	
	if(!function_exists('_e')) {
		function _e() {
			Webbmaffian\MVC\Helper\Lang::setup();
			echo call_user_func_array(array('Autorelations\Lang', 'get_string'), func_get_args());
		}
	}