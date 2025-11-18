<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	This is a general class for implementing an entity model
	
*/

namespace extension\ka_extensions;

/**
	Implements a standard interface for managing sql records

	The class provides several functions for inserting, updating and getting data from the database. It is easier
	for other modules to overwrite methods of that class to extend or restrict the module functionality.
*/
abstract class ModelRecordset extends Model {

	// this variable has to be redefined in a child class
	protected $table_class;  // = __NAMESPACE__ . '\\dbtable\\KaLicenses';
	const TABLE_ALIAS = 't'; // that default table alias can be redefined in child classes

	protected $table_name;
	
	protected $primary_fields;

	protected function onLoad() {
		
		$tbl = new $this->table_class();

		// init the primary field
		$keys = $tbl->getPrimaryKeys();
		$this->primary_fields = $keys;
		
		// init the table name
		$this->table_name = $tbl->getTableName();
	}
	
	
	public function getPrimaryFields() {
		return $this->primary_fields;
	}
	
	
	public function getPrimaryField() {
		
		if (count($this->primary_fields) > 1) {
			throw new \Exception("More than 1 primary field is specified");
		}
		
		return $this->primary_fields[0];		
	}

	
	protected function getRecordQB($data) {
	
		$qb = new QB();
		$qb->select(static::TABLE_ALIAS . '.*', $this->table_name, static::TABLE_ALIAS);
		
		foreach ($this->primary_fields as $v) {
			$qb->where(static::TABLE_ALIAS . '.' . $v, $data[$v]);
		}
		
		return $qb;
	}
	
	
	public function getRecord($primary_fields) {
	
		if (!is_array($primary_fields)) {
			$primary_fields = [$this->primary_fields[0] => $primary_fields];
		}
	
		$qry = $this->getRecordQB($primary_fields)->query();
		
		if (count($qry->rows) > 1) {
			throw new ExceptionData("Ambiguous data");
		}
		
		return $qry->row;
	}
	
	public function getRecordsTotal($data = array()) {
	
		$qb = $this->getRecordsQB($data);
		
		$qb->select = array();		
		$qb->select('COUNT(*) AS total');
		
		$result = $qb->query()->row;
      	
		if (empty($result)) {
			return 0;
		}

		return $result['total'];
	}
	
	
	public function getRecords($data = array()) {

		$qb = $this->getRecordsQB($data);
	
		if (!empty($data['sort'])) {
			$sql = $data['sort'];
			if (!empty($data['order'])) {
				$sql .= " " . $data['order'];
			}
			$qb->orderBy($sql);
		}
		
		if (isset($data['start']) && isset($data['limit'])) {
			$qb->limit(max(0, $data['start']), max(1, $data['limit']));
		}
	
		$records = $qb->query()->rows;
		
		return $records;
	}
	
	
	/*
		Any filter fields should be defined in a child class
	*/
	protected function getRecordsQB($data = array()) {
	
		$qb = new QB();
		
		$qb->select(static::TABLE_ALIAS . '.*', $this->table_name, static::TABLE_ALIAS);
		
		return $qb;
	}	

	
	/*
		This function is called from a controller to fill in data of the record with heavy values
		not directly existing in the table.
	*/
	public function fillRecord($record = []) {
		
		$result = $record;
		$result['record_title'] = '';

		return $result;
	}

	/*
		A record is saved into the table
		
		The function can save records with one or multiple primary fields. When one primary field is specified
		it will return its identifier.
	*/
	public function saveRecord($record) {
		
		$is_new = false;
		$record_id = null;
		
		if (count($this->primary_fields) > 1) {
			foreach ($this->primary_fields as $pf) {
				if (!isset($record[$pf])) {
					$is_new = true;
					break;
				}
			}

		} else {
			$primary_field = $this->getPrimaryField();
			if (empty($record[$primary_field])) {
				$is_new = true;
			} else {
				$record_id = $record[$primary_field];
			}
		}
	
		if ($is_new) {
			$record_id = $this->addRecord($record);
		} else {
			if (!$this->editRecord($record)) {
				$record_id = null;
			}
		}

		return $record_id;
	}
	
	/*
		Add a new record to the table
	*/
	public function addRecord($data) {
		
		$tbl = new $this->table_class();
	
		// copy available record values to table fields
		//
		$fields = $tbl->getFields();
		
		foreach ($data as $rk => $rv) {
			if (isset($fields[$rk])) {
				$tbl->{$rk} = $rv;
			}
		}
		
		$record_id = $tbl->insert();
		
		return $record_id;
	}
	
	
	public function editRecord($data) {
		
		$tbl = new $this->table_class();
		
		// copy available record values to table fields
		//
		$fields = $tbl->getFields();
		$where = [];
		foreach ($data as $rk => $rv) {
			if (in_array($rk, $this->primary_fields)) {
				$where[$rk] = $rv;
				continue;
			}
			
			if (isset($fields[$rk])) {
				$tbl->{$rk} = $rv;
			}
		}

		$result = $tbl->update($where);
		
		return $result;
	}
	
	
	public function deleteRecord($key_values) {
	
		$tbl = new $this->table_class();

		if (!is_array($key_values)) {
			$key_values = [$this->primary_fields[0] => $key_values];
		}

		$tbl->delete($key_values);
	}

	
	public function delete($where) {
		$records = $this->getRecords($where);
		foreach ($records as $record) {
			$this->deleteRecord($record);
		}
	}

	public function getFields() {
		
		
	}

}