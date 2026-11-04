<?php
/*
	A test table with the field metadata of the default list and form pages (library tests).
*/

namespace extension\ka_extensions\ka_lib_test\dbtable;

class MetaItem extends \extension\ka_extensions\ADBTable {

	const TABLE_NAME = 'ka_lib_test_item';

	public function getFields() {
		return array(
			'item_id'    => array('primary_key' => true),
			'name'       => array('type' => 'text', 'required' => true, 'list' => true, 'sort' => true, 'filter' => 'like', 'link' => 'edit'),
			'status'     => array('type' => 'checkbox', 'default_value' => 1, 'list' => true, 'filter' => 'select', 'format' => 'enabled', 'title' => 'Status'),
			'date_added' => array('form' => false, 'list' => true, 'sort' => true, 'filter' => 'date_range', 'format' => 'date', 'title' => 'Date Added'),
		);
	}
}
