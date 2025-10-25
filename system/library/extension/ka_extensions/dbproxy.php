<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	This class is used by TraitDBFilter. It keeps an information about the calling class to apply
	filters to queries from that class only. The current class is updated by the Trait on each db request within
	any class method.	

*/

namespace extension\ka_extensions;

class DBProxy {

	static $dbproxy;

	protected $db;
	protected $current_class = 'db';
	protected $filters = array();

	
	public function __construct($db) {
		$this->db = $db;
	}
	
	
	static public function getInstance($db) {

		if (empty(static::$dbproxy)) {
			static::$dbproxy = new DBProxy($db);
		}
		
		return static::$dbproxy;	
	}
	
	
	public function query($sql, $params = array()) {
	
		if (!empty($this->filters[$this->current_class])) {
			foreach ($this->filters[$this->current_class] as $f) {
				$args = [$sql];
				if (!empty($f[1])) {
					$args[] = $f[1];
				}					
				$sql = call_user_func_array($f[0], $args);
			}
		}
		
		if (version_compare(VERSION, '3.0.2.0', '<')) {
			return $this->db->query($sql, $params);
		}
		
		return $this->db->query($sql);		
	}

	
	public function setCurrentClass($class_name) {
		$this->current_class = $class_name;
	}
	
	
	public function setFilter($filter, $args = null) {
	
		if (!isset($this->filters[$this->current_class])) {
			$this->filters[$this->current_class] = [];
		}
		
		$this->filters[$this->current_class][] = [$filter, $args];
	}

	
	public function removeFilter() {
		$this->filters[$this->current_class] = [];
	}	
	
	
	function __call($name, $args) {
		return call_user_func_array(array($this->db, $name), $args);
	}
	
	
	
	public function __get($name) {
		return $this->db->{$name};
	}

	
	public function __set($name, $value) {
		$this->db->{$name} = $value;
	}
}