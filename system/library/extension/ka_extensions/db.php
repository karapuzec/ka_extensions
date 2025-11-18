<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 575 $)

*/
	
namespace extension\ka_extensions;

/**
	@deprecated

	The class is DEPRECATED. We moved our functions to the standard DB class by extending it.
*/
class Db {

	protected $db = null;
	
	public function __construct($db) {
		$this->db = $db;
	}
		
 	public function query($sql) {
 		$res = $this->db->query($sql);

		if (empty($res->rows)) {
			return $res;
		}
 		
		return $res->rows;
 	}

 	
 	/*
 	*/
 	public function insertOrUpdate($table, $data) {
 		return $this->db->ka_insert($table, $data, false, true);
 	}

 	
 	/*
 		Deprecated function, use $this->db->ka_insert instead.
 	*/
	public function queryInsert($tbl, $arr, $is_replace = false) { 	
		return $this->db->ka_insert($tbl, $arr, $is_replace);
	}
	
 	
	/*
		Deprecated. use $this->db->ka_update() instead.
	*/
	public function queryUpdate($tbl, $arr, $where = '') {
		if (empty($arr)) {
			return;
		}
		$this->db->ka_update($tbl, $arr, $where);
	}
	
	
	/*
		Deprecated function. Rarely used and it will be removed later.
	*/
	public function queryFirst($qry) {
		$res = $this->db->query($qry);
		return $res->row;
	}


	public function safeQuery($query) {
	
		if (in_array('MijoShop', get_declared_classes())) {
			$prefix = DB_PREFIX;
			$prefix = MijoShop::get('db')->getDbo()->replacePrefix($prefix);
			$query = str_replace(DB_PREFIX, $prefix, $query);
		}
		
		$result = $this->db->query($query);
		
		return $result;
	}
	
	
	public function queryHash($qry_string, $key) {
	
		$qry = $this->db->query($qry_string);
		if (empty($qry->rows)) {
			return false;
		}
		
		$res = array();
		if (!isset($qry->row[$key])) {
			throw new \Exception(__METHOD__ . ": key not found ($key)");
		}
		
		foreach ($qry->rows as $row) {
			if (isset($row[$key])) {
				$res[$row[$key]] = $row;
			}
		}
		
		return $res;
	}
	
	/*
		DEPRECATED. Use $this->db->ka_insert instead.
	*/
	public function insert($tbl, $arr, $is_replace = false, $update_on_duplicate = false) {
		return $this->db->ka_insert($tbl, $arr, $is_replace, $update_on_duplicate);
	}
	
	
	/*
		DEPRECATED. Use $this->db->ka_update instead.
	*/
	public function update($tbl, $arr, $condition = '') {
		return $this->db->ka_update($tbl, $arr, $condition);
	}
	
	
	/*
		DEPRECATED. Use $this->db->ka_delete instead.
	*/
	public function delete($table, $cond) {
		return $this->db->ka_delete($table, $cond);
	}
}