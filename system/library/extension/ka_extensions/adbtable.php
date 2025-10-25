<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	
Basic rules:

- all class properties are treated as table values. Methods 'update','save','insert', etc. submit all fields.
- internal variables are started with '_'

*/
namespace extension\ka_extensions;

class ADBTable {

	const TABLE_NAME = '';
	
	protected $_values = array();

	protected $_registry;
	protected $_db;
	protected $_load;
	
	protected $_fields = null;
	protected $_primary_keys = null;
	
	public function __construct() {

		$this->_registry = \KaGlobal::getRegistry();
		$this->_db   = $this->_registry->get('db');
		$this->_load = $this->_registry->get('load');
	}
	
	
	/*
		The method inserts assigned values to the database.
		
		Parameters:
			$is_replace - tells the function to use 'REPLACE' operation instead of 'INSERT'. Default value = false.
			
		Returns:
			record identifier. (internally it uses $db->getLastId())
		
	*/
	function insert($is_replace = false) {
		$record = $this->_values;

		$id = $this->_db->ka_insert(static::TABLE_NAME, $record, $is_replace);
		return $id;
	}
	
	/*
		The method updates database with assigned values.

		Parameters:
			$where - array or string
			
		Returns:
			result of update operation.
	*/
	function update($where = null) {
	
		if (is_null($where)) {
			$primary_keys = $this->getPrimaryKeys();
			if (empty($primary_keys)) {
				throw new \Exception("Primary keys are not defined for the table " . static::TABLE_NAME);
			}
			
			$where = [];			
			foreach ($primary_keys as $k => $v) {
				if (!isset($this->_values[$v])) {
					throw new \Exception("Primary key values are missed in the record");
				}
				$where[$v] = $this->_values[$v];
			}
		}
	
		$record = $this->_values;

		$result = null;
		if (!empty($record)) {
			$result = $this->_db->ka_update(static::TABLE_NAME, $record, $where);
		}
		
		return $result;
	}
	
	/*
		Returns a list of fields from the table.
		
		This is an optional method redeclared in child classes. A user may work with any fields by default, but 
		it is possible to restrict access by these fields only.
		
	*/
	public function getFields() {
		return null;
	}
	
	/*
		returns an array of primary key fields from the db declaration table.
	*/
	public function getPrimaryKeys() {

		if (!is_null($this->_primary_keys)) {
			return $this->_primary_keys;
		}
	
		$fields = $this->getFields();
		if (is_null($fields)) {
			return array();
		}
		
		$pkeys = array();
		foreach ($fields as $k => $v) {
			if (!empty($v['primary_key'])) {
				$pkeys[] = $k;
			}
		}	

		return $pkeys;
	}

	
	/*
		Set a batch of values at once from an array. 
		
		Returns: none.
	*/
	public function setValues($values) {

		if (empty($this->_fields)) {
			$this->_fields = $this->getFields();
		}
		
		foreach ($values as $k => $v) {
			if (isset($this->_fields[$k])) {
				$this->{$k} = $v;
			}
		}
	}
	

	public function __set($name, $value) {
	
		$method_name = 'set_' . $name;
		if (method_exists($this, $method_name)) {
			$this->{$method_name}($value);
		} else {	
			$this->_values[$name] = $value;
		}
	}

	
	public function __get($name) {

		$method_name = 'get_' . $name;
		if (method_exists($this, $method_name)) {
			$value = $this->{$method_name}();
		} else {
			$value = $this->_values[$name];
		}
		
		return $value;
	}

	
	/*
		Unsets defined values.
	*/
	public function empty() {
		$this->_values = array();
	}


	/*
		Executes a deletion operation against the table with the specified condition.
		When the condition is not passed, the condition is generated from fields assigned to the table class.
	*/
	function delete($where = null) {
		if (is_null($where)) {
			$where = $this->_values;
		}
		$this->_db->ka_delete(static::TABLE_NAME, $where);
	}
		
	
	/*
		Executes a selection query for the table. When any fields are set, they are used as a condition.
	*/
	public function query() {
	
		$qb = new QB();
		
		$qb->select("*", static::TABLE_NAME);
		
		if (!empty($this->_values)) {
			foreach ($this->_values as $k => $v) {
				$qb->where($k, $v);
			}
		}
		
		return $qb->query();
	}
	
	
	public function getTableName() {
		return static::TABLE_NAME;
	}
	
	
	/*
		Checks if the primary record already exists. The primary keys are taken from the fields declaration.
	*/
	public function hasPrimaryRecord() {
		$primary_keys = $this->getPrimaryKeys();
		
		$qb = new QB();
		$qb->select("1", static::TABLE_NAME);
		
		foreach ($primary_keys as $key) {
			if (!isset($this->_values[$key])) {
				throw new \Exception("Primary key field is not available for the table " . static::TABLE_NAME);
			}
			$qb->where($key, $this->_values[$key]);
		}
		
		$result = $qb->query()->rows;
		
		return (count($result) > 0);
	}
	
	
	public function getEmptyRecord() {
	
		$rec = [];
	
		$fields = $this->getFields();
		
		foreach ($fields as $fk => $field) {
			$rec[$fk] = '';
		}
		
		return $rec;
	}	
}