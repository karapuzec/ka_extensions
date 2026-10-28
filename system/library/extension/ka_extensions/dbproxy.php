<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)

*/

namespace extension\ka_extensions;

/**
	@internal
	
	This class is used by TraitDBFilter. It keeps an information about the calling class to apply
	filters to queries from that class only. The current class is updated by the Trait on each db request within
	any class method.	
*/
class DBProxy {

	static $dbproxy;

	protected $db;
	protected $current_class = 'db';
	// filters by class: class name => [filter id => [callable, args]], in the order of setting
	protected $filters = array();

	// the last filter id, ids are unique for all classes
	protected static $last_filter_id = 0;

	
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
	
	
	/*
		Adds a filter to the queries of the current class. Returns the filter id for removeFilter().
	*/
	public function setFilter($filter, $args = null) {
	
		if (!isset($this->filters[$this->current_class])) {
			$this->filters[$this->current_class] = [];
		}
		
		$id = ++static::$last_filter_id;
		$this->filters[$this->current_class][$id] = [$filter, $args];

		return $id;
	}

	
	/*
		Removes the filter with the id returned by setFilter(). Without the id the last filter set by
		the current class is removed (the filters of a class are a stack), other filters stay.
		Returns false when there was no such filter.
	*/
	public function removeFilter($id = null) {

		if (is_null($id)) {
			if (empty($this->filters[$this->current_class])) {
				return false;
			}
			array_pop($this->filters[$this->current_class]);
			return true;
		}

		foreach ($this->filters as $class => $filters) {
			if (isset($filters[$id])) {
				unset($this->filters[$class][$id]);
				return true;
			}
		}

		return false;
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