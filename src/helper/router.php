<?php

namespace Webbmaffian\MVC\Helper;

class Router {
	protected $routes = array();
	protected $keys = array();
	
	
	public function __construct($routes = array()) {
		$this->routes = $routes;
	}
	
	
	public function route($input) {
		$input = trim($input, '/');
		
		foreach($this->routes as $route => $controller) {
			$this->keys = array();
			
			if(substr($route, -1) === '*' && strncmp($input, $route, strlen($route) - 1) === 0) {
				if(is_string($controller)) {
					Helper::redirect('/' . $controller);
				}
				elseif(!is_array($controller)) {
					throw new Problem('Invalid controller.');
				}
				
				$controller[0] = Helper::get_controller($controller[0]);
				
				call_user_func($controller, substr($input, strlen($route) - 1));
				
				return true;
			}
			
			$regex = preg_replace_callback('/\{([^\}]+)\}/', array($this, 'extract_args'), $route);
			$regex = '/^' . str_replace('/', '\/', $regex) . '$/';
			
			if(preg_match($regex, $input, $matches)) {
				if(is_string($controller)) {
					Helper::redirect('/' . $controller);
				}
				elseif(!is_array($controller)) {
					throw new Problem('Invalid controller.');
				}
				
				$controller[0] = Helper::get_controller($controller[0]);
				
				$options = array(
					'wrap' => true
				);

				if(isset($controller[2])) {
					if(!is_array($controller[2])) {
						throw new Problem('Invalid controller.');
					}

					$options = $controller[2];
					unset($controller[2]);
				}

				ob_start();
				
				try {
					call_user_func($controller, (empty($this->keys) ? array() : array_combine($this->keys, array_slice($matches, 1))));
				} catch(Problem $e) {
					echo '<p class="error">' . $e->getMessage() . '</p>';
				}
				
				echo ob_get_clean();
				
				return true;
			}
		}
		
		return false;
	}
	
	
	protected function extract_args($matches = array()) {
		$this->keys[] = $matches[1];
		
		// The slash will be escaped later
		return '([^/]+)';
	}
}