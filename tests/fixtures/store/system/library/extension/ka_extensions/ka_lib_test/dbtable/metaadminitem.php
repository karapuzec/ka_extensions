<?php
/*
	The test table of the admin list (ControllerAdminList): the status options come from a method of the table.
*/

namespace extension\ka_extensions\ka_lib_test\dbtable;

class MetaAdminItem extends MetaItem {

	public function getFields() {
		$fields = parent::getFields();
		$fields['status']['type']    = 'select';
		$fields['status']['options'] = 'getStatusOptions';
		return $fields;
	}

	public function getStatusOptions() {
		return array(1 => 'Status On', 0 => 'Status Off');
	}
}
