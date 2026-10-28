<?php
/*
	A separate request for HiddenSettingsTest::testParallelIncreases(): increases a hidden value
	of the module 'ka_lib_hs' N times with the HiddenSettings class of the test store.

	php hidden_increase.php <store dir> <key> <times>
*/

list(, $store_dir, $key, $times) = $argv;

require($store_dir . '/config.php');
require($store_dir . '/system/library/extension/ka_extensions/kaglobal.php');
require($store_dir . '/system/library/extension/ka_extensions/hiddensettings.php');

// the methods of the OpenCart DB class used by HiddenSettings
class HiddenIncreaseDb {
	protected $link;

	public function __construct() {
		$this->link = new \mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
	}

	public function query($sql) {
		$result = $this->link->query($sql);
		if ($result instanceof \mysqli_result) {
			$rows = $result->fetch_all(MYSQLI_ASSOC);
			return (object)array('row' => isset($rows[0]) ? $rows[0] : array(), 'rows' => $rows, 'num_rows' => count($rows));
		}
		return $result;
	}

	public function escape($value) {
		return $this->link->real_escape_string($value);
	}

	public function countAffected() {
		return $this->link->affected_rows;
	}
}

class HiddenIncreaseRegistry {
	protected $db;

	public function __construct() {
		$this->db = new HiddenIncreaseDb();
	}

	public function get($key) {
		return $key == 'db' ? $this->db : null;
	}
}

\extension\ka_extensions\KaGlobal::init(new HiddenIncreaseRegistry());

for ($i = 0; $i < (int)$times; $i++) {
	\extension\ka_extensions\HiddenSettings::increase('ka_lib_hs', $key);
}
