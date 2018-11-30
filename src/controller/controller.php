<?php

namespace Webbmaffian\MVC\Controller;

use \Webbmaffian\MVC\Helper\View;
use \Webbmaffian\MVC\Helper\Value;
use \Webbmaffian\MVC\Helper\Helper;
use \Webbmaffian\MVC\Helper\Problem;

abstract class Controller {
	const MUST_SIGN_IN = true;
	
	protected $notices = array();
	protected $view = null;
	
	
	public function __construct() {
		$this->check_post();
	}
	
	
	public function get_class_name() {
		$parts = explode('\\', get_class($this));
		return array_pop($parts);
	}
	
	
	protected function check_post() {
		if(!empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
			
			// Continue script even if user aborts
			ignore_user_abort(true);
			
			Value::set($_POST);
			
			try {
				$this->handle_post(isset($_POST['action']) ? $_POST['action'] : '');
			}
			catch(Problem $e) {
				$this->add_error($e->getMessage());
			}
		}
	}
	
	
	protected function handle_post($action) {
		// Overridden
	}
	
	
	protected function add_notice() {
		$args = func_get_args();
		$message = (sizeof($args) > 1) ? call_user_func_array('sprintf', $args) : $args[0];
		
		$this->notices[] = array(
			'message' => $message,
			'type' => 'success'
		);
	}
	
	
	protected function add_error() {
		$args = func_get_args();
		$message = (sizeof($args) > 1) ? call_user_func_array('sprintf', $args) : $args[0];
		
		$this->notices[] = array(
			'message' => $message,
			'type' => 'danger'
		);
	}


	protected function translated_notice() {
		$args = func_get_args();
		$message = (sizeof($args) > 1) ? call_user_func_array('__', $args) : __($args[0]);
		
		$this->add_notice($message);
	}
	
	
	protected function translated_error() {
		$args = func_get_args();
		$message = (sizeof($args) > 1) ? call_user_func_array('__', $args) : __($args[0]);
		
		$this->add_error($message);
	}
	
	
	protected function get_view($template = '') {
		if(is_null($this->view)) {
			$this->view = new View($template);
			$this->view->set('menu', $this->get_menu());
			$this->view->set('notices', $this->notices);
			
			$template_css_class = str_replace('/', '-', $template);
			$this->view->add('html_class', $template_css_class);
			$this->view->add('body_class', $template_css_class);
		}
		
		return $this->view;
	}
	
	
	protected function get_menu() {
		$menu = [
		
			// Examples
			// '' => [
			// 	'label' => __('Client dashboard'),
			// 	'icon' => 'chart-line'
			// ],

			// [
			// 	'label' => __('Support tickets'),
			// 	'icon' => 'question-circle',
			// 	'children' => [
			// 		'events/add' => [
			// 			'label' => __('New ticket')
			// 		],
			// 		'events/tickets' => [
			// 			'label' => __('Show tickets')
			// 		],
			// 	],
			// 	'capability_group' => 'event'
			// ]
			
		];

		return $this->parse_menu($menu);
	}
	
	
	protected function parse_menu(&$menu) {
		$current_url = trim(Helper::get_current_url(), '/');
		$active = null;
		
		foreach($menu as $key => &$item) {
			$str = preg_quote($key, '/');
			if(!empty($key) && preg_match("/($str)(\/|$)/", $current_url)) {
				if(isset($item['children'])) {
					$item['open'] = true;
					$this->parse_menu($item['children']);
				} else {
					$active = &$item['active'];
				}
			}
		}
		
		$active = true;
		return $menu;
	}
}