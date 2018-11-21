<?php

namespace Webbmaffian\MVC\Model;

abstract class Model implements \JsonSerializable {
	const TABLE = '';
	const PRIMARY_KEY = 'id';
	const IS_AUTO_INCREMENT = true;
	
	protected $data;

	
	static public function get_table() {
		return static::TABLE;
	}

	
	static public function get_by_id($id = 0) {
		if(!is_numeric($id)) {
			throw new Problem(get_called_class() . ' ID must be numeric');
		}
		
		$db = DB::instance();
		
		$data = $db->get_row('SELECT * FROM ' . static::get_table() . ' WHERE ' . static::PRIMARY_KEY . ' = ?', $id);
		
		if(!$data) {
			throw new Problem('Could not find ' . get_called_class() . ' with ID ' . $id);
		}
		
		return new static($data);
	}
	
	
	static public function create($data) {
		if(!is_array($data)) {
			throw new Problem('Input must be an array');
		}
		
		if(static::IS_AUTO_INCREMENT && isset($data[static::PRIMARY_KEY])) {
			throw new Problem(get_called_class() . ' ID must not be set');
		}
		
		$db = DB::instance();
		
		if(!$db->insert(static::get_table(), $data)) {
			throw new Problem(get_called_class() . ' could not be created.');
		}
		if($id = $db->get_last_id()) $data[static::PRIMARY_KEY] = $id;
		
		return new static($data);
	}


	static public function create_update($data, $unique_keys = array(), $dont_update_keys = array()) {
		if(!is_array($data)) {
			throw new Problem('Input must be an array');
		}
		
		if(static::IS_AUTO_INCREMENT && isset($data[static::PRIMARY_KEY])) {
			throw new Problem(get_called_class() . ' ID must not be set');
		}

		$db = DB::instance();
		$result = $db->insert_update(self::get_table(), $data, $unique_keys, $dont_update_keys, static::PRIMARY_KEY);
		
		if(!$result) {
			throw new Problem(get_called_class() . ' could not be created.');
		}

		if($id = $result->fetch_value()) $data[static::PRIMARY_KEY] = $id;
		
		return new static($data, $tenant_id);
	}
	
	
	static public function get_class_name() {
		return substr(get_called_class(), strlen(__NAMESPACE__) + 1);
	}
	
	
	static public function collection() {
		$class_name = get_called_class() . '_Collection';
		
		return new $class_name;
	}
	
	
	public function __construct($data = array()) {
		if(!is_array($data)) {
			throw new Problem('Input must be an array');
		}
		
		if(!isset($data[static::PRIMARY_KEY])) {
			throw new Problem(get_class($this) . ' ID must be set');
		}
		
		if(!is_numeric($data[static::PRIMARY_KEY])) {
			throw new Problem(get_class($this) . ' ID must be numeric');
		}
		
		$this->data = $data;
	}
	
	
	public function __call($name, $args = array()) {
		list($type, $name) = explode('_', $name, 2);
		
		if($type === 'get') {
			return $this->data[$name];
		}
		elseif($type === 'has') {
			return !empty($this->data[$name]);
		}
	}

	
	public function update($data = array()) {
		$db = DB::instance();
		
		$this->data = array_merge($this->data, $data);
		
		return $db->update(static::get_table(), $data, array(
			static::PRIMARY_KEY => $this->data[static::PRIMARY_KEY]
		));
	}
	
	
	public function delete() {
		$db = DB::instance();
		
		return $db->delete(static::get_table(), array(
			static::PRIMARY_KEY => $this->data[static::PRIMARY_KEY]
		));
	}
	
	
	public function get_data() {
		return $this->data;
	}
	
	
	public function jsonSerialize() {
		return $this->get_data();
	}
}