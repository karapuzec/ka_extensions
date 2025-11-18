<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/
	
namespace extension\ka_extensions;

/**

This class is used for building complex SELECT queries from parts and executing them later.

Here is how a simple SQRL query looks in Opencart:

$query = $this->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "'");
$product = $query->row;

This is the same query rewritten with QB class:

```
$qb = new \extension\ka_extensions\QB();
$qb->select("*", "product");
$qb->where("product_id", $product_id);
$product = $qb->query();
```

The main goal of this class is to separate building the request from executing it. That allows developers to build
the request in a separate function like this:

```

class Product extends Model {

	public function getProduct($product_id) {
		$qb = $this->getProductQB(['product_id' => $product_id]);
		$product = $qb->query()->row;
		return $product;
	}

	// 
	//	This function makes QB object. These functions for preparing QB class have 'protected' visibility.
	//
	protected function getProductQB($data) {
		$qb = new \extension\ka_extensions\QB();
	
		$qb->select("*", "product", "p");
		$qb->where("p.product_id", $product_id);
		
		return $qb;
	}
}

```

When QB is prepared in a separate function, it be can adjusted in a child class. 'Ka Extensions' library allows
developers to inherate existing classes in their modules. Also it makes simpler to position ocmod/vqmod patches
to sql queries. So, it is better to build SQL queries with QB class than writing raw SQL in the code.

*/

class QB {

	var $select    = array();
	var $delete    = array();
	var $from      = array();
	var $innerJoin = array();
	var $leftJoin  = array();
	var $where     = array();
	var $limit     = array();
	var $orderBy   = array();
	var $groupBy   = array();
	
	protected $db = null;

	public function __construct() {
		$this->db = \KaGlobal::getRegistry()->get('db');
	}

	/**
		PARAMS
		$from     - table name
		$from_key - table alias		
	*/
	public function from($from, $from_key = '') {
		if (!empty($from_key)) {
			$this->from[$from_key] = $from;
		} else {
			$this->from[$from] = $from;
		}
	}

	/*
		$what     - fields to select. String type.
		$from     - table name
		$from_key - table alias
	*/
	public function select($what, $from = '', $from_key = '') {
		
		$this->select[] = $what;
		
		if (!empty($from)) {
			$this->from($from, $from_key);
		}
	}
	

	public function delete($what, $from = '', $from_key = '') {
		
		$this->delete[] = $what;
		
		if (!empty($from)) {
			$this->from($from, $from_key);
		}
	}
	
	/*
		The function adds INNER JOIN construction to SQL request
		
		$from      - table name
		$from_key  - table key
		$condition - joining condition (following after ON in INNER JOIN)
	*/
	public function innerJoin($from, $from_key = '', $condition = '') {
		
		$arr = array(
			'table' => $from,
			'on' => $condition
		);
	
		if (!empty($from_key)) {
			$this->innerJoin[$from_key] = $arr;
		} else {
			$this->innerJoin[$from] = $arr;
		}
	}

	/*
		The function adds LEFT JOIN construction to SQL request
		
		$from      - table name
		$from_key  - table key
		$condition - joining condition (following after ON in LEFT JOIN)
	*/
	public function leftJoin($from, $from_key = '', $condition = '') {
		
		$arr = array(
			'table' => $from,
			'on' => $condition
		);
	
		if (!empty($from_key)) {
			$this->leftJoin[$from_key] = $arr;
		} else {
			$this->leftJoin[$from] = $arr;
		}
	}
	
	/*
		$where - string or array. The array means all condtions inside the array joined via OR
	*/
	public function where($where, $value = null) {
	
		if (!is_null($value)) {
			$where = "$where = '" . $this->db->escape($value) . "'";
		}
		$this->where[] = $where;
	}	
	
	/*
		Adds 'LIMIT $start, $limit' command to SQL request
	*/
	public function limit($start, $limit) {
		$this->limit = array(
			'start' => $start,
			'limit' => $limit
		);
	}
	
	/*
		Adds 'ORDER BY $order' command.
		
		$after - specifies position of the $order value. Multiple $order values can be added to
		         the request.
	*/
	public function orderBy($order, $after = '') {
	
		if (empty($after)) {
			$this->orderBy[$order] = $order;
		} else {
			$this->orderBy = Arrays::insertAfterKey($this->orderBy, $order, $after);
		}
	}

	/*
		Adds 'GROUP BY $groupBy' command.
		
		$after - specifies position of the $groupBy value. Multiple $groupBy values can be added to
		         the request.
	*/
	public function groupBy($groupBy, $after = '') {
	
		if (empty($after)) {
			$this->groupBy[$groupBy] = $groupBy;
		} else {
			$this->groupBy = Arrays::insertAfterKey($this->groupBy, $groupBy, $after);
		}
	}
	
	/*
		Returns a raw SQL request string basing on the QB data.
	*/
	public function getSql() {

		$sql = '';
		
		// select parameters
		//
		if (!empty($this->select)) {

			$sql .= "SELECT " . implode(",", $this->select) . " ";

		} elseif (!empty($this->delete)) {
		
			$sql .= "DELETE " . implode(",", $this->delete) . " ";
			
		}
		
		if (empty($sql)) {
			return $sql;
		}
		
		// from parameters
		//
		if (!empty($this->from)) {
			$sql .= " FROM ";
			foreach ($this->from as $k => $v) {			
				$sql .= DB_PREFIX . $v;
				if ($v != $k) {
					$sql .= " " . $k;
				}
			}
		}
		
		// inner join parameters
		//
		if (!empty($this->innerJoin)) {
			foreach ($this->innerJoin as $k => $v) {
				$sql .= " INNER JOIN " . DB_PREFIX . $v['table'] . ' ' . $k;
				
				if (!empty($v['on'])) {
					$sql .= " ON " . $v['on'] . " ";
				}
			}
		}

		// left join parameters
		//
		if (!empty($this->leftJoin)) {
			foreach ($this->leftJoin as $k => $v) {
				$sql .= " LEFT JOIN " . DB_PREFIX . $v['table'] . ' ' . $k;
				
				if (!empty($v['on'])) {
					$sql .= " ON " . $v['on'] . " ";
				}
			}
		}
		
		// where parameters
		//
		if (!empty($this->where)) {
			$where = "";
			foreach ($this->where as $k => $v) {
				if (!empty($where)) {
					$where .= " AND ";
				}
				if (is_array($v)) {
					$where .= ' (' . implode(" OR ", $v) . ') ';
				} else {
					$where .= ' (' . $v . ') ';
				}
			}
			$sql .= " WHERE $where";
		}

		// group by
		//
		if (!empty($this->groupBy)) {
			$sql .= " GROUP BY " . implode(", ", $this->groupBy);
		}
		
		// order by
		//
		if (!empty($this->orderBy)) {
			$sql .= " ORDER BY " . implode(", ", $this->orderBy);
		}
		
		// limit
		//
		if (!empty($this->limit)) {
			if (isset($this->limit['start'])) {
				$sql .= " LIMIT " . $this->limit['start'];
				
				if (isset($this->limit['limit'])) {
					$sql .= ", " . $this->limit['limit'];
				}
			}
		}
		
		return $sql;
	}
	
	
	/**
		Builds and runs the query from the QB data.
		Returns a standard 'sql result' object with $row, $rows and other fields.
	*/
	public function query() {
		return $this->db->query($this->getSql());
	}	
}