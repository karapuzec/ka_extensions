<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	Impelemnts the ability to adjust plain SQL queries of standard Opencart classes. It might be helpful
	if you don't want to insert SQL patches to the code directly.
	
	Once the trait is used it adds setFilter/removeFilter methods to the standard DB class.
	
	How to use:
	1. You need to attach the trait in a model/controller class.
	2. Create a function that will receive parameters: $sql string and $data array
	3. set the filter before calling the original function and remove the filter after that.
	   setFilter() returns the filter id, pass it to removeFilter($id) in a 'finally' block: only this
	   filter is removed even when the original function throws an exception. The filters of a class
	   are a stack, removeFilter() without the id removes the last filter of the current class
	   (before 4.1.1.29 it removed all filters of the class, also the ones of the calling methods).

	Here is an example:
	
```php

class ModelCatalogDownload extends \ModelCatalogDownload {

	use \extension\ka_extensions\TraitDBFilter;

	public function getDownloads($data = array()) {
	
		$filter_id = $this->db->setFilter([$this, 'getDownloadsDBFilter'], $data);
		try {
			$result = parent::getDownloads($data);
		} finally {
			$this->db->removeFilter($filter_id);
		}
	
		return $result;
	}

	public function getDownloadsDBFilter($sql, $data) {

		if (!isset($data['filter_vendor_id'])) {
			return $sql;
		}
	
		$vendor_id = (int)$data['filter_vendor_id'];
	
		$sql = str_replace(') WHERE ', 
			") WHERE vendor_id = $vendor_id AND ",
			$sql
		);
	
		return $sql;
	}	
}

```	
	
	@package DB
*/
trait TraitDBFilter {

	/**
		@internal
	*/
	protected $ka_dbproxy;

	/**
		@internal
	*/
	public function __get($key) {
		if ($key == 'db') {
			if (empty($this->ka_dbproxy)) {
				$db = $this->registry->get('db');
				$this->ka_dbproxy = DBProxy::getInstance($db);
			}
			$this->ka_dbproxy->setCurrentClass(get_class($this));
			return $this->ka_dbproxy;
		}
		
		return parent::__get($key);
	}
}