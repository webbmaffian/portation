<?php

namespace Webbmaffian\MVC\Model;

interface Authable {
	static public function get_by_email($email = '');

	public function set_password($password, $clear_resets = false);

	public function generate_recovery_hash();

	public function verify_password($password);

	public function add_capability($capability, $tenant_id);

	public function remove_capability($capability, $tenant_id);

	public function get_capabilities($tenant_id = 'all', $as_string = false, $delimiter = ', ');
}