<?php

namespace Webbmaffian\MVC\Model;

use \Webbmaffian\MVC\Helper\Problem;
use \Webbmaffian\ORM\DB;

abstract class Model_Collection {
	const TABLE = '';
	
	protected $select = array();
	protected $join = array();
	protected $where = array();
	protected $where_raw = array();
	protected $having = array();
	protected $group_by = array();
	protected $limit = 0;
	protected $offset = 0;
	
	protected $results = array();
	protected $rows = null;
	protected $rows_key = null;

	
	static public function get_table() {
		return static::TABLE;
	}


	public function get_base_class() {
		return trim(str_replace('Collection', '', get_class($this)), '_');
	}


	public function select() {
		$fields = func_get_args();
		
		foreach($fields as $field) {
			$this->select[] = static::format_key($field);
		}
		
		return $this;
	}
	
	
	public function select_raw() {
		$fields = func_get_args();
		
		foreach($fields as $field) {
			$this->select[] = self::format_raw_value($field);
		}
		
		return $this;
	}
	
	
	public function join($join) {
		$this->join[] = $join;
		
		return $this;
	}
	
	
	public function where() {
		$args = func_get_args();
		
		$this->where[] = $this->get_where($args);

		return $this;
	}


	// This function takes any number of arrays as OR condition
	public function where_any() {
		$arrays = func_get_args();
		$ors = array();

		foreach($arrays as $args) {
			if(!is_array($args)) {
				throw new Problem('All arguments must be arrays.');
			}

			$ors[] = $this->get_where($args);
		}

		$this->where[] = '(' . implode(' OR ', $ors) . ')';

		return $this;
	}


	protected function get_where($args = array(), $format_key = true) {
		$num_args = sizeof($args);

		$a = $args[0];

		if(isset($args[2])) {
			$b = self::format_value($args[2]);
			$comparison = $args[1];
		}
		elseif(isset($args[1]) || is_null($args[1])) {
			$b = self::format_value($args[1]);
			$comparison = (is_array($args[1]) ? 'IN' : '=');

			if(is_array($args[1]) && empty($args[1])) {
				throw new Problem('Empty array sent to where().');
			}
		}
		else {
			throw new Problem('Invalid number of arguments.');
		}

		if($b === 'NULL') {
			if($comparison === '=') $comparison = 'IS';
			elseif($comparison === '!=') $comparison = 'IS NOT';
		}

		return ($format_key ? static::format_key($a) : $a) . ' ' . $comparison . ' ' . $b;
	}


	public function having() {
		$args = func_get_args();
		
		$this->having[] = $this->get_where($args, false);

		return $this;
	}


	public function where_raw() {
		$fields = func_get_args();
		foreach($fields as $field) {
			$this->where_raw[] = self::format_raw_value($field);
		}
		return $this;
	}
	
	
	public function where_match($match, $against) {
		$this->where[] = 'MATCH(' . $match . ') AGAINST("' . $against . '" IN BOOLEAN MODE)';
		
		return $this;
	}
	
	
	public function group_by($group_by) {
		$this->group_by[] = static::format_key($group_by);
		
		return $this;
	}
	
	
	public function order_by($order_by, $order = 'ASC') {
		$this->order_by[] = $order_by . ' ' . $order;
		
		return $this;
	}
	
	
	public function order_by_relevance($match, $against) {
		$this->order_by[] = '(MATCH(' . $match . ') AGAINST("' . $against . '" IN BOOLEAN MODE)) DESC';
		
		return $this;
	}
	
	
	public function limit($limit) {
		$this->limit = $limit;
		
		return $this;
	}


	public function offset($offset) {
		$this->offset = $offset;
		
		return $this;
	}
	
	
	public function set_rows_key($key) {
		$this->rows_key = $key;
		
		return $this;
	}
	
	
	public function get() {
		if(is_null($this->rows)) {
			$this->rows = array();
			$this->run();
			$class_name = trim(str_replace('Collection', '', get_class($this)), '_');
			
			foreach($this->results as $data) {
				if($this->rows_key && isset($data[$this->rows_key])) {
					$this->rows[$data[$this->rows_key]] = new $class_name($data);
				}
				else {
					$this->rows[] = new $class_name($data);
				}
			}
			
			$this->results = null;
		}
		
		return $this->rows;
	}


	public function get_single() {
		$this->get();

		return reset($this->rows);
	}
	
	
	public function num_rows() {
		return (is_array($this->rows) ? sizeof($this->rows) : 0);
	}
	
	
	public function get_query() {
		$q = array();
		
		// Ensure we have no duplications
		$this->select = array_unique($this->select);
		$this->join = array_unique($this->join);
		$this->where = array_unique($this->where);
		$this->where_raw = array_unique($this->where_raw);
		$this->group_by = array_unique($this->group_by);
		$this->having = array_unique($this->having);
		
		if(!empty($this->select)) {
			$q['select'] = 'SELECT ' . implode(', ', $this->select);
		}

		else {
			$q['select'] = 'SELECT ' . self::get_table() . '.*';
		}
		
		$q['from'] = 'FROM ' . self::get_table();
		
		if(!empty($this->join)) {
			$q['join'] = implode("\n", $this->join);
		}
		
		if(!empty($this->where)) {
			$q['where'] = 'WHERE ' . implode(' AND ', $this->where);
		}

		if(!empty($this->where_raw)) {
			if(empty($this->where)) {
				$q['where'] = 'WHERE ' . implode(' ', $this->where_raw);
			} else {
				$q['where'] .= ' AND (' . implode(' ', $this->where_raw) . ')';
			}
		}
		
		if(!empty($this->group_by)) {
			$q['group_by'] = 'GROUP BY ' . implode(', ', $this->group_by);
		}

		if(!empty($this->having)) {
			$q['having'] = 'HAVING ' . implode(' AND ', $this->having);
		}
		
		if(!empty($this->order_by)) {
			$q['order_by'] = 'ORDER BY ' . implode(', ', $this->order_by);
		}
		
		if($this->limit) {
			$q['limit'] = 'LIMIT ' . $this->limit;
		}

		if($this->offset) {
			$q['offset'] = 'OFFSET ' . $this->offset;
		}
		
		return implode("\n", $q);
	}


	public function get_count() {
		$this->select = array();
		$this->order_by = array();
		$this->select_raw('COUNT(*) AS count');
		$this->rows = array();
		$this->run();

		return isset($this->results[0]['count']) ? $this->results[0]['count'] : false;
	}
	
	
	protected function run() {
		$db = DB::instance();
		if(!$this->results = $db->query($this->get_query())->fetch_all()) {
			$this->results = array();
		}
	}
	
	
	static protected function format_key($key) {
		if(strpos($key, '.') === false) {
			$key = self::get_table() . '.' . $key;
		}
		
		return $key;
	}


	static protected function format_value($value) {
		if($value instanceof \DateTime) {
			$value = $value->format('Y-m-d H:i:s');
		}

		if(is_string($value)) {
			return "'" . $value . "'";
		}

		if(is_bool($value)) {
			return (int)$value;
		}

		if(is_array($value)) {
			$value = array_map(function($e) {
				return "'" . $e . "'";
			}, $value);

			return '(' . implode(', ', $value) . ')';
		}

		if(is_null($value)) {
			return 'NULL';
		}

		return $value;
	}


	static protected function format_raw_value($value) {
		return str_replace('"', '\'', $value);
	}
}