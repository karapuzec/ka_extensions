<?php

namespace KaExtensionsTests\Integration;

/*
	Base class of the integration tests. Every test runs in its own process (one OpenCart application
	per process). Tests remove the data they create; the snapshot is restored once per run.
*/
abstract class LibraryTestCase extends \KaTesting\TestCase {

	const MODULE_ROUTE = 'extension/ka_extensions/ka_lib_test';
	const ITEMS_ROUTE  = 'extension/ka_extensions/ka_lib_test/items';
	const ITEM_ROUTE   = 'extension/ka_extensions/ka_lib_test/item';
	const ITEM_TABLE   = 'ka_lib_test_item';

	protected function db() {
		return $this->oc->db();
	}

	protected function esc($value) {
		return $this->db()->escape($value);
	}

	protected function getUserGroupId() {
		return (int)$this->oc->get('user')->getGroupId();
	}

	protected function getPermissions($user_group_id) {
		$row = $this->queryRow("SELECT permission FROM `" . DB_PREFIX . "user_group` WHERE user_group_id = '" . (int)$user_group_id . "'");
		$permissions = json_decode($row['permission'], true);
		return is_array($permissions) ? $permissions : array();
	}

	protected function countPermission($user_group_id, $type, $route) {
		$permissions = $this->getPermissions($user_group_id);
		if (empty($permissions[$type])) {
			return 0;
		}
		return count(array_keys($permissions[$type], $route, true));
	}

	/*
		Restores the permission list of the group (tests change the group of the logged in administrator).
	*/
	protected function setPermissions($user_group_id, $permissions) {
		$this->db()->query("UPDATE `" . DB_PREFIX . "user_group` SET permission = '" . $this->esc(json_encode($permissions)) . "' WHERE user_group_id = '" . (int)$user_group_id . "'");
	}

	protected function tableExists($table) {
		return !empty($this->queryRows("SHOW TABLES LIKE '" . $this->esc(DB_PREFIX . $table) . "'"));
	}

	protected function columns($table) {
		return array_column($this->queryRows("DESCRIBE `" . DB_PREFIX . $table . "`"), 'Field');
	}

	protected function dropItemTable() {
		$this->db()->query("DROP TABLE IF EXISTS `" . DB_PREFIX . static::ITEM_TABLE . "`");
	}

	protected function createItemTable() {
		$this->dropItemTable();
		$this->db()->query("CREATE TABLE `" . DB_PREFIX . static::ITEM_TABLE . "` (
			`item_id` int(11) NOT NULL AUTO_INCREMENT,
			`name` varchar(255) NOT NULL,
			`status` tinyint(1) NOT NULL DEFAULT '1',
			`date_added` datetime NOT NULL,
			PRIMARY KEY (`item_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	protected function addItem($name, $status = 1, $date_added = '2026-01-01 00:00:00') {
		$this->db()->query("INSERT INTO `" . DB_PREFIX . static::ITEM_TABLE . "` SET name = '" . $this->esc($name) . "', status = '" . (int)$status . "', date_added = '" . $this->esc($date_added) . "'");
		return (int)$this->db()->getLastId();
	}

	protected function getItem($item_id) {
		return $this->queryRow("SELECT * FROM `" . DB_PREFIX . static::ITEM_TABLE . "` WHERE item_id = '" . (int)$item_id . "'");
	}

	protected function getSettings($code) {
		$settings = array();
		foreach ($this->queryRows("SELECT `key`, `value`, serialized FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = '" . $this->esc($code) . "' ORDER BY setting_id") as $row) {
			$settings[$row['key']] = $row['serialized'] ? json_decode($row['value'], true) : $row['value'];
		}
		return $settings;
	}

	protected function deleteSettings($code) {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = '" . $this->esc($code) . "'");
	}

	/*
		Grants access and modify of the test module pages to the group of the administrator.
	*/
	protected function grantTestPages() {
		$group_id = $this->getUserGroupId();
		$permissions = $this->getPermissions($group_id);
		foreach (array('access', 'modify') as $type) {
			foreach (array(static::MODULE_ROUTE, static::ITEMS_ROUTE, static::ITEM_ROUTE) as $route) {
				if (empty($permissions[$type]) || !in_array($route, $permissions[$type])) {
					$permissions[$type][] = $route;
				}
			}
		}
		$this->setPermissions($group_id, $permissions);
		// the user object keeps the permissions loaded at login
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
	}

	protected function topMessages() {
		$session = $this->oc->get('session');
		return isset($session->data['ka_top_messages']) ? $session->data['ka_top_messages'] : array();
	}
}
