<?php

namespace extension\ka_extensions;

/**
	@internal
*/
class SystemLibraryDB extends \DB {

	public function ka_insert($tbl, $arr, $is_replace = false, $update_on_duplicate = false) {

	    if (empty($arr)) {
	    	return;
	    }

	    if (empty($tbl) ||!is_array($arr)) {
    	    throw new \Exception(__METHOD__ . ": wrong parameters");
	    }

	    $query = $is_replace ? 'REPLACE' : 'INSERT';

		$r = $this->ka_getPairs($arr);

    	$tbl = DB_PREFIX . $tbl;
    	$query .= ' INTO `' . $tbl . '` SET ' . implode(', ', $r);

    	if ($update_on_duplicate) {
    		$query .= " ON DUPLICATE KEY UPDATE " . implode(', ', $r);
    	}
    	
    	if (!$this->adaptor->query($query)) {
	    	return false;
		}

		return $this->adaptor->getLastId();
	}


	public function ka_update($tbl, $arr, $condition = '') {
	    if (empty($arr)) {
	    	return null;
	    }

	    if (empty($tbl) ||!is_array($arr)) {
    	    throw new \Exception(__METHOD__ . ": wrong parameters");
	    }
	    
		$tbl = DB_PREFIX . $tbl;

		$r = $this->ka_getPairs($arr);

		if (is_string($condition)) {
			$where = $condition;
		} else {
	    	$where = implode(" AND ", $this->ka_getPairs($condition, true));
	    }
    	
	    $query = 'UPDATE `' . $tbl . '` SET ' . implode(', ', $r) . ($where ? ' WHERE ' . $where : '');

    	return $this->adaptor->query($query);
	}
	
	
	/*
		$cond - can be string or array
	*/
	public function ka_delete($table, $cond) {
	
	    if (empty($table) || is_null($cond)) {
    	    throw new ExceptionData(__METHOD__ . ": wrong parameters");
	    }

		$tbl = DB_PREFIX . $table;

		$where = '';
		if (!empty($cond)) {
			if (is_string($cond)) {
				$where = $cond;
			} else {
				$where = implode(' AND ', $this->ka_getPairs($cond, true));
			}
		}

	    $query = 'DELETE FROM `' . $tbl . '`';
	    if (!empty($where)) {
	    	$query .= ' WHERE ' . $where;
	    }	
	    
    	return $this->adaptor->query($query);
	}
	
	
	protected function ka_getPairs($array, $is_where = false) {
	
		$pairs = [];
	
	    foreach ($array as $k => $v) {
	    	if (is_numeric($k)) {
	    		$pairs[] = $v;
	    		continue;
	    	}
	    
   	        $k = "`$k`";

        	if (!is_null($v)) {
		        $v = "'" . $this->adaptor->escape($v) . "'";
	    		$pairs[] = $k . "=" . $v;
			} else {
				if ($is_where) {
					$pairs[] = $k . ' IS NULL';
				} else {
					$pairs[] = $k . ' = NULL';
				}
			}
    	}
	
    	return $pairs;
	}

	
	public function ka_safeQuery($query) {
	
		if (in_array('MijoShop', get_declared_classes())) {
			$prefix = DB_PREFIX;
			$prefix = MijoShop::get('db')->getDbo()->replacePrefix($prefix);
			$query = str_replace(DB_PREFIX, $prefix, $query);
		}
		
		$result = $this->query($query);
		
		return $result;
	}
}