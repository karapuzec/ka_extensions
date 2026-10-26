<?php
/*
	Test recordset of the library tests (ModelRecordset + ADBTable).
*/

namespace extension\ka_extensions\ka_lib_test\dbtable {

	class Item extends \extension\ka_extensions\ADBTable {

		const TABLE_NAME = 'ka_lib_test_item';

		public function getFields() {
			return array(
				'item_id'    => array('primary_key' => true),
				'name'       => array(),
				'status'     => array(),
				'date_added' => array(),
			);
		}
	}
}

namespace extension\ka_extensions\ka_lib_test {

	class ModelItems extends \extension\ka_extensions\ModelRecordset {

		protected $table_class = __NAMESPACE__ . '\\dbtable\\Item';

		// set by tests: group the records by status
		public $group_by_status = false;

		protected function getSortWhitelist() {
			return array(
				'name' => 't.name',
				'date' => 't.date_added',
			);
		}

		protected function getRecordsQB($data = array()) {
			$qb = parent::getRecordsQB($data);
			if (!empty($data['filter_name'])) {
				$qb->where('t.name', $data['filter_name']);
			}
			if ($this->group_by_status) {
				$qb->groupBy('t.status');
			}
			return $qb;
		}

		public function fillRecord($record = array()) {
			$result = parent::fillRecord($record);
			if (!empty($record['name'])) {
				$result['record_title'] = 'Item ' . $record['name'];
			}
			return $result;
		}
	}
}

namespace {
	class_alias('\extension\ka_extensions\ka_lib_test\ModelItems', '\ModelExtensionKaExtensionsKaLibTestItems');
}
