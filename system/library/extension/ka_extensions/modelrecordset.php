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
	
	@package DB
*/
abstract class ModelRecordset extends Model {

	// this variable has to be redefined in a child class
	protected $table_class;  // = __NAMESPACE__ . '\\dbtable\\KaLicenses';
	const TABLE_ALIAS = 't'; // that default table alias can be redefined in child classes

	protected $table_name;
	
	protected $primary_fields;

	protected function onLoad() {
		
		$tbl = $this->getTable();

		// init the primary field
		$keys = $tbl->getPrimaryKeys();
		$this->primary_fields = $keys;
		
		// init the table name
		$this->table_name = $tbl->getTableName();
	}
	
	
	/*
		Returns a new object of the table class (field metadata: ADBTable::getListFields() etc.).
	*/
	public function getTable() {
		$tbl = new $this->table_class();
		return $tbl;
	}


	/*
		Returns a recordset of the table without a model file of its own (see ControllerList::TABLE_CLASS).
	*/
	public static function forTable($registry, $table_class) {
		$recordset = new ModelTableRecordset($registry, $table_class);
		return $recordset;
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
		$qb = $this->getRecordQB($primary_fields);
		$qry = $qb->query();

		if (count($qry->rows) > 1) {
			throw new ExceptionData("Ambiguous data");
		}
		
		return $qry->row;
	}
	
	public function getRecordsTotal($data = array()) {
	
		$qb = $this->getRecordsQB($data);
		
		if (!empty($qb->groupBy)) {
			// with GROUP BY, COUNT(*) returns one row per group and the first row is not the total.
			// The grouped rows are counted in a subquery then.
			//
			$qb->select = array();
			$qb->select('1');
			$qb->orderBy = array();
			$qb->limit = array();
			
			$result = $this->db->query("SELECT COUNT(*) AS total FROM (" . $qb->getSql() . ") AS grouped_records")->row;
			
		} else {
			$qb->select = array();		
			$qb->select('COUNT(*) AS total');
		
			$result = $qb->query()->row;
		}
      	
		if (empty($result)) {
			return 0;
		}

		return $result['total'];
	}
	
	
	public function getRecords($data = array()) {

		$qb = $this->getRecordsQB($data);
	
		// sort and order values may come from the URL. Without a sort whitelist accept only plain column names
		// like 'name' or 't.name' (as before), with a whitelist only the listed values. ASC/DESC directions only
		// to prevent SQL injections
		//
		$sql = '';
		$sort_whitelist = $this->getSortWhitelist();
		if (empty($sort_whitelist)) {
			if (!empty($data['sort']) && preg_match('/^[\w.]+$/', $data['sort'])) {
				$sql = $data['sort'];
			}
		} elseif (!empty($data['sort']) && is_string($data['sort'])) {
			if (isset($sort_whitelist[$data['sort']])) {
				$sql = $sort_whitelist[$data['sort']];
			} elseif (in_array($data['sort'], $sort_whitelist, true)) {
				$sql = $data['sort'];
			}
		}
		if (!empty($sql)) {
			if (!empty($data['order'])) {
				$order = strtoupper($data['order']);
				if (in_array($order, ['ASC', 'DESC'])) {
					$sql .= " " . $order;
				}
			}
			$qb->orderBy($sql);
		}
		
		if (isset($data['start']) && isset($data['limit'])) {
			$qb->limit(max(0, (int)$data['start']), $data['limit']);
		}
		
		$records = $qb->query()->rows;
		
		return $records;
	}
	
	
	/*
		Returns allowed sort values of getRecords() or null.

		null or empty (default) - any plain column name like 'name' or 't.name' is accepted.
		array                   - only the listed values are accepted: 'sort code' => 'SQL column or expression'.
		                          The sort value can be a code or one of the SQL values (ControllerList::getSortFields()
		                          puts the column into sort links). Other values are ignored and the records are sorted
		                          as getRecordsQB() defines.

		Example:
			protected function getSortWhitelist() {
				return array(
					'date'  => 't.date_added',
					'email' => 'COALESCE(t.recipient_email, c.email)',
				);
			}
	*/
	protected function getSortWhitelist() {

		// the fields with the 'sort' metadata of the table
		//
		$tbl = $this->getTable();
		if (!$tbl->hasFieldMeta('sort')) {
			return null;
		}

		$whitelist = array();
		foreach ($tbl->getSortFields() as $code) {
			$whitelist[$code] = static::TABLE_ALIAS . '.' . $code;
		}

		return $whitelist;
	}


	/*
		Any filter fields should be defined in a child class
	*/
	protected function getRecordsQB($data = array()) {
	
		$qb = new QB();
		
		$qb->select(static::TABLE_ALIAS . '.*', $this->table_name, static::TABLE_ALIAS);
		
		$this->applyMetaFilters($qb, $data);
		
		return $qb;
	}	


	/*
		Applies the filters of the table fields with the 'filter' metadata (ADBTable::getFilterFields()).
		Parameters: 'filter_<field>' ('like', 'eq', 'select'), 'filter_<field>_from' / 'filter_<field>_to'
		('date_range', dates Y-m-d). Empty values are skipped, '0' is a value. Tables without the metadata
		are not filtered here.
	*/
	protected function applyMetaFilters($qb, $data) {

		$tbl = $this->getTable();
		foreach ($tbl->getFilterFields() as $code => $field) {

			$column = static::TABLE_ALIAS . '.`' . $code . '`';
			$name   = 'filter_' . $code;

			if ($field['filter'] == 'date_range') {
				foreach (array('_from' => '>=', '_to' => '<=') as $suffix => $op) {
					$value = isset($data[$name . $suffix]) && is_scalar($data[$name . $suffix]) ? (string)$data[$name . $suffix] : '';
					$time  = strlen($value) ? strtotime($value) : false;
					if ($time !== false) {
						$date = date('Y-m-d', $time) . ($suffix == '_from' ? ' 00:00:00' : ' 23:59:59');
						$qb->where($column . ' ' . $op . " '" . $this->db->escape($date) . "'");
					}
				}
				continue;
			}

			if (!isset($data[$name]) || !is_scalar($data[$name]) || !strlen((string)$data[$name])) {
				continue;
			}
			$value = (string)$data[$name];

			if ($field['filter'] == 'like') {
				$qb->where($column . " LIKE '%" . $this->db->escape(addcslashes($value, '%_\\')) . "%'");
			} else {
				$qb->where($column, $value);
			}
		}
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