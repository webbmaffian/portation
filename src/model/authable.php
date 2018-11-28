<?php

namespace Webbmaffian\MVC\Model;

interface Authable {
	static public function get_by_email($email = '');

	public function set_password($password, $clear_resets = false);

	public function generate_recovery_hash();

	public function verify_password($password);
}