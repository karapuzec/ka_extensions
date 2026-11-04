<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/
namespace extension\ka_extensions;

/**	

Provides declaration of tables and their structure for easy inserting/updating operations. 

It was mostly made for easy form creation. The class introduces an additional abstraction level between 
models and database functions to avoid writing sql queries directly. 

Basic rules:
- all class properties (not starting with $_) are treated as table values. 
  Methods 'update','save','insert', etc. submit all these fields.
- internal variables are started with '_'

Here is a typical class declaration for a table. Only fields declared in the class will be affected by
insert/update operations.

```php
namespace extension\ka_extensions\ka_multivendor\dbtable;
use extension\ka_extensions\ADBTable as ADBTable;

class Customer extends ADBTable {

	const TABLE_NAME = 'customer';

	public function getFields() {
		
		$fields = array(
			'customer_id'  => [
				'primary_key' => true,
			],
			'firstname'    => [],
			'lastname'     => [],
			'email'        => [],
		);

		return $fields;
	}
}
```

	@package DB
*/
abstract class ADBTable {

	/**
		This constant should be overwritten in inhertior. Contains a table name without DB_PREFIX like 'product'.
	*/
	const TABLE_NAME = '';
	
	/** 
		@internal
	*/
	protected $_values = array();

	/** 
		@internal
	*/
	protected $_registry;
	/** 
		@internal true
		@hidden true
	*/
	protected $_db;
	/** 
		@internal
	*/
	protected $_load;
	
	/** 
		@internal
	*/
	protected $_fields = null;
	/** 
		@internal
	*/
	protected $_primary_keys = null;
	/**
		@internal options of the fields resolved by getOptions()
	*/
	protected $_options = array();
	
	public function __construct() {

		$this->_registry = \KaGlobal::getRegistry();
		$this->_db   = $this->_registry->get('db');
		$this->_load = $this->_registry->get('load');
	}
	
	
	/**
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
	
	/**
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
	
		// an empty condition would update all records of the table
		//
		if (empty($where)) {
			throw new ExceptionData("Empty update condition for the table " . static::TABLE_NAME);
		}
	
		$record = $this->_values;

		$result = null;
		if (!empty($record)) {
			$result = $this->_db->ka_update(static::TABLE_NAME, $record, $where);
		}
		
		return $result;
	}
	
	/**
		Returns a list of fields from the table.
		
		This is an optional method redeclared in child classes. A user may work with any fields by default, but 
		it is possible to restrict access by these fields only.
		
	*/
	public function getFields() {
		return null;
	}
	
	/**
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

	
	/**
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
	

	/** 
		@internal
	*/
	public function __set($name, $value) {
	
		$method_name = 'set_' . $name;
		if (method_exists($this, $method_name)) {
			$this->{$method_name}($value);
		} else {	
			$this->_values[$name] = $value;
		}
	}

	
	/** 
		@internal
	*/
	public function __get($name) {

		$method_name = 'get_' . $name;
		if (method_exists($this, $method_name)) {
			$value = $this->{$method_name}();
		} else {
			$value = $this->_values[$name];
		}
		
		return $value;
	}

	
	/**
		Unsets defined values (without updating the database) for starting a new record.
	*/
	public function empty() {
		$this->_values = array();
	}


	/**
		Executes a deletion operation against the table with the specified condition.
		When the condition is not passed, the condition is generated from fields assigned to the table class.
		An empty condition throws an exception, use deleteAll() to delete all records of the table.
	*/
	function delete($where = null) {
		if (is_null($where)) {
			$where = $this->_values;
		}

		if (empty($where)) {
			throw new ExceptionData("Empty delete condition for the table " . static::TABLE_NAME);
		}

		$this->_db->ka_delete(static::TABLE_NAME, $where);
	}
	
	
	/**
		Deletes all records of the table.
	*/
	function deleteAll() {
		$this->_db->ka_delete(static::TABLE_NAME, '');
	}
	
	
	/**
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
	
	
	/**
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
	
	/**
		Returns an empty record (for a new form).
	*/
	public function getEmptyRecord() {
	
		$rec = [];
	
		$fields = $this->getFields();
		
		foreach ($fields as $fk => $field) {
			$rec[$fk] = '';
		}
		
		return $rec;
	}

	/**
		Return already defined values
	*/
	public function getValues() {
	 	return $this->_values;
	}


	/**
		Field metadata for the default list and form pages (ControllerList, ControllerForm). All keys are
		optional, a table without them works as before:

		- 'list'   => true / false  - a column of the list (no field with 'list': all fields except primary keys)
		- 'sort'   => true          - a sortable column (no field with 'sort': all list columns)
		- 'filter' => 'like' | 'eq' | 'select' | 'date_range' - a filter of the list (none by default)
		- 'form'   => false         - the field is not in the form
		- 'title'  => text          - the title (default: the language string 'txt_title_<field>')
		- 'format' => 'price' | 'date' | 'datetime' | 'enabled' - the value format in the list
		- 'width'  => css width of the column, 'link' => 'edit' - the value links to the form
		- 'type', 'required', 'max_length', 'options', 'default_value' - form field keys (TraitControllerForm),
		  'type' is 'text' by default
		- 'options' => value => text: the options of a select field in the form and of a 'select' filter, the
		  list shows the text of the value. Options from the database: the name of a method of the table
		  class or a closure, called when the options are needed (getOptions()), e.g.
		  'store_id' => ['type' => 'select', 'options' => 'getStoreOptions', 'list' => true]

		Example:
			'name'       => ['type' => 'text', 'required' => true, 'list' => true, 'sort' => true, 'filter' => 'like', 'link' => 'edit'],
			'status'     => ['type' => 'checkbox', 'default_value' => 1, 'list' => true, 'filter' => 'select', 'format' => 'enabled'],
			'date_added' => ['form' => false, 'list' => true, 'sort' => true, 'format' => 'datetime', 'filter' => 'date_range'],
	*/
	protected function getMetaFields() {

		$fields = $this->getFields();
		if (empty($fields)) {
			return array();
		}

		$result = array();
		foreach ($fields as $code => $field) {
			if (!empty($field['primary_key'])) {
				continue;
			}
			$field['code'] = $code;
			$result[$code] = $field;
		}

		return $result;
	}


	/**
		Returns true when a field declares the metadata key (e.g. 'sort', 'filter').
	*/
	public function hasFieldMeta($key) {

		foreach ($this->getMetaFields() as $field) {
			if (isset($field[$key])) {
				return true;
			}
		}

		return false;
	}


	/**
		Returns the columns of the list: fields with 'list' => true, without such fields all fields except
		primary keys.
	*/
	public function getListFields() {

		$fields = $this->getMetaFields();
		if (!$this->hasFieldMeta('list')) {
			return $fields;
		}

		return array_filter($fields, function($field) {
			return !empty($field['list']);
		});
	}


	/**
		Returns the codes of sortable fields: fields with 'sort' => true, without such fields all list columns.
	*/
	public function getSortFields() {

		if (!$this->hasFieldMeta('sort')) {
			return array_keys($this->getListFields());
		}

		return array_keys(array_filter($this->getMetaFields(), function($field) {
			return !empty($field['sort']);
		}));
	}


	/**
		Returns the fields with a 'filter' type (none by default).
	*/
	public function getFilterFields() {

		return array_filter($this->getMetaFields(), function($field) {
			return !empty($field['filter']);
		});
	}


	/**
		Returns the options (value => text) of a field: the 'options' array of the metadata, the result of
		the method of the table named in 'options' or of the closure. An empty array without options.
		The result is kept for the object.
	*/
	public function getOptions($code) {

		if (isset($this->_options[$code])) {
			return $this->_options[$code];
		}

		$fields = $this->getFields();
		$options = isset($fields[$code]['options']) ? $fields[$code]['options'] : array();

		if ($options instanceof \Closure) {
			$options = $options();
		} elseif (is_string($options) && method_exists($this, $options)) {
			$options = $this->$options();
		}
		if (!is_array($options)) {
			$options = array();
		}

		$this->_options[$code] = $options;

		return $options;
	}


	/**
		Options of the stores: store_id => name, the default store (0) first. For 'options' => 'getStoreOptions'.
	*/
	public function getStoreOptions() {

		$options = array(0 => $this->_registry->get('config')->get('config_name'));

		$qb = new QB();
		$qb->select('store_id, name', 'store');
		$qb->orderBy('store_id');
		foreach ($qb->query()->rows as $row) {
			$options[$row['store_id']] = $row['name'];
		}

		return $options;
	}


	/**
		Options of the languages: language_id => name in the order of the languages. For 'options' => 'getLanguageOptions'.
	*/
	public function getLanguageOptions() {

		$qb = new QB();
		$qb->select('language_id, name', 'language');
		$qb->orderBy('sort_order');
		$qb->orderBy('name');

		$options = array_column($qb->query()->rows, 'name', 'language_id');

		return $options;
	}


	/**
		Returns form fields: all fields except primary keys and fields with 'form' => false, 'type' is 'text'
		by default. The list keys are removed.
	*/
	public function getFormFields() {

		$result = array();
		foreach ($this->getMetaFields() as $code => $field) {
			if (isset($field['form']) && !$field['form']) {
				continue;
			}
			unset($field['list'], $field['sort'], $field['filter'], $field['form'], $field['format'], $field['width'], $field['link']);
			if (empty($field['type'])) {
				$field['type'] = 'text';
			}
			if (isset($field['options'])) {
				$field['options'] = $this->getOptions($code);
			}
			$result[$code] = $field;
		}

		return $result;
	}
}