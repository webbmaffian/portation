<?php

namespace Webbmaffian\MVC\Helper;

use Webbmaffian\ORM\DB;
use Webbmaffian\MVC\Model\Authable;

class Auth {
	static public function is_signed_in() {
		return isset($_SESSION['user']);
	}
	
	
	static public function maybe_sign_out() {
		if(!self::is_signed_in()) return;
		
		if(time() - $_SESSION['user']['last_active'] > 3600) {
			self::sign_out();
		}
	}
	
	
	static public function sign_out() {
		if(!self::is_signed_in()) return;
		
		unset($_SESSION['user']);

		if(ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
		}

		session_destroy();
	}


	static public function start_session() {
		if(session_status() == PHP_SESSION_NONE) {
			session_start();
		}
	}
	
	
	static public function sign_in($user) {
		if(self::is_signed_in()) return;
		
		if(!$user instanceof Authable) {
			throw new Problem('Sign in object must implement interface Authable.');
		}
		
		$_SESSION['user'] = array(
			'id' => $user->get_id(),
			'name' => $user->get_name(),
			'lang' => $user->get_language(),
			'signed_in' => time(),
			'last_active' => time()
		);
		
		static::load_capabilities();
		static::load_available_customers();
	}
	
	
	static public function register_activity() {
		if(!self::is_signed_in()) return;
		
		$_SESSION['user']['last_active'] = time();
	}
	
	
	static public function get_id() {
		if(!self::is_signed_in()) return false;
		
		return (int)$_SESSION['user']['id'];
	}
	
	
	static public function get_name() {
		if(!self::is_signed_in()) return false;
		
		return $_SESSION['user']['name'];
	}
	

	static public function get_lang() {
		if(!self::is_signed_in()) return false;

		return $_SESSION['user']['lang'];
	}


	static public function set_lang($lang) {
		$_SESSION['user']['lang'] = $lang;
	}

	
	static public function get_user() {
		return User::get_by_id(self::get_id());
	}
	
	
	static public function load_capabilities() {
		if(!self::is_signed_in()) return false;
		
		$db = DB::instance();
		$capabilities = $db->get_result('SELECT customer_id, capability FROM user_capabilities WHERE user_id = ?', self::get_id());
		
		$_SESSION['user']['caps'] = array();
		
		foreach($capabilities as $cap) {
			$customer_id = (int)$cap['customer_id'];
			$cap_parts = explode(':', $cap['capability']);
			
			if(!isset($cap_parts[1])) {
				$cap_parts[1] = 'general';
			}
			
			list($capability, $capability_group) = $cap_parts;
			
			if(!isset($_SESSION['user']['caps'][$customer_id])) {
				$_SESSION['user']['caps'][$customer_id] = array();
			}
			
			if(!isset($_SESSION['user']['caps'][$customer_id][$capability_group])) {
				$_SESSION['user']['caps'][$customer_id][$capability_group] = array();
			}
			
			$_SESSION['user']['caps'][$customer_id][$capability_group][$capability] = 1;
		}
	}
	
	
	static public function load_available_customers() {
		if(!self::is_signed_in() || !isset($_SESSION['user']['caps'])) return false;
		
		if($customer_ids = array_filter(array_keys($_SESSION['user']['caps']))) {
			$customers = Customer::collection()->select('*')->with_entity()->where('entity_id', $customer_ids)->order_by('name')->get();

			$_SESSION['user']['available_customers'] = array();
			
			foreach($customers as $customer) {
				$_SESSION['user']['available_customers'][intval($customer->get_id())] = $customer->get_name();
			}
		}
		
		// Set current customer ID to the first one, if there is none set
		if(defined('ENDPOINT') && ENDPOINT === 'admin') {
			self::set_customer_id(0);
		} elseif(!empty($customers) && !self::get_customer_id()) {
			self::set_customer_id($customers[0]->get_id());
		}
	}
	
	
	// Returns array with: id => name
	static public function get_available_customers() {
		if(!self::is_signed_in() || !isset($_SESSION['user']['available_customers'])) return array();
		
		return $_SESSION['user']['available_customers'];
	}
	
	
	static public function get_customer_id() {
		if(!self::is_signed_in()) return null;
		if(!isset($_SESSION['user']['customer_id'])) return null;
		
		return (int)$_SESSION['user']['customer_id'];
	}


	static public function get_customer_name() {
		if(!isset($_SESSION['user']['customer_name'])) {
			if(!($customer_id = self::get_customer_id())) return null;

			$customer = Customer::get_by_id($customer_id);
			$_SESSION['user']['customer_name'] = $customer->get_name();
		}

		return $_SESSION['user']['customer_name'];
	}
	
	
	static public function set_customer_id($customer_id) {
		if(!self::is_signed_in()) return false;
		
		$customer_id = (int)$customer_id;
		
		if($customer_id < 0) {
			throw new Problem('Invalid customer ID provided.');
		}
		
		$_SESSION['user']['customer_name'] = null;
		$_SESSION['user']['customer_id'] = $customer_id;
	}
	
	
	static public function can($capability, $capability_group = 'general', $customer_id = null) {
		if(!self::is_signed_in()) return false;
		
		// If null, get customer ID from current session
		if(is_null($customer_id)) {
			$customer_id = self::get_customer_id();
		}
		
		// If still unset or invalid
		if(!is_numeric($customer_id)) {
			throw new Problem('No valid customer ID provided.');
		}
		
		// If the capability is set, or if the user can do anything
		$can_do = isset($_SESSION['user']['caps'][$customer_id]['general']['do_anything']) || isset($_SESSION['user']['caps'][0]['general']['do_anything']);

		if(is_array($capability)) {
			foreach($capability as $cap) {
				if(isset($_SESSION['user']['caps'][$customer_id][$capability_group][$cap])) {
					$can_do = true;
				}
			}

			return $can_do;
		}

		return isset($_SESSION['user']['caps'][$customer_id][$capability_group][$capability]) || $can_do;
	}
	
	
	static public function has_capability_group($capability_group, $customer_id = null) {
		if(!self::is_signed_in()) return false;
		
		// If null, get customer ID from current session
		if(is_null($customer_id)) {
			$customer_id = self::get_customer_id();
		}
		
		// If still unset or invalid
		if(!is_numeric($customer_id)) {
			throw new Problem('No valid customer ID provided.');
		}
		
		// If the capability is set, or if the user can do anything
		return isset($_SESSION['user']['caps'][$customer_id][$capability_group]) || isset($_SESSION['user']['caps'][$customer_id]['general']['do_anything']) || isset($_SESSION['user']['caps'][0]['general']['do_anything']);
	}
}
