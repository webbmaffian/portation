<?php

namespace Webbmaffian\MVC\Helper;

class JWT {
	const ALGORITHM = 'HS256';
	
	
	static public function encode($data = array()) {
		if(!is_array($data)) {
			throw new Problem('Data must be an array.');
		}
		
		if(empty($_ENV['JWT_SECRET'])) {
			throw new Problem('Missing environment variable: JWT_SECRET');
		}
		
		$now = time();
		$expire = $now + (@$_ENV['JWT_EXPIRE_TIME'] ? $_ENV['JWT_EXPIRE_TIME'] : 1800); // 30 minutes
		
		return \Firebase\JWT\JWT::encode(array(
			'iat' => $now,
			'exp' => $expire,
			'data' => $data
		), $_ENV['JWT_SECRET'], self::ALGORITHM);
	}
	
	
	static public function decode($token = '') {
		if(empty($_ENV['JWT_SECRET'])) {
			throw new Problem('Missing environment variable: JWT_SECRET');
		}
		
		$data = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET'], array(self::ALGORITHM));
		
		return (array)$data->data;
	}
}