<?php

namespace KaExtensionsTests\Integration;

/**
 * Installation: permissions (added once, 802ae61), database patching, the installed version, reinstall and
 * uninstall (the settings stay).
 *
 * @group installer
 */
class InstallerTest extends LibraryTestCase {

	const EXT_ROUTE = 'extension/extension/ka_extensions';

	protected $saved_permissions;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' AND `code` = 'ka_lib_test'");
		$this->deleteSettings('ka_lib_test');
		$this->deleteSettings('ka_lib_test_hidden');
		$this->dropItemTable();
	}

	protected function model() {
		return $this->oc->get('load')->kamodel('extension/ka_extensions');
	}

	public function testAddPermissionAddsTheRouteOnce() {
		$group_id = $this->fixtures()->createUserGroup(array('access' => array('common/dashboard'), 'modify' => array()));
		try {
			$this->model()->addPermission($group_id, 'access', 'extension/ka_extensions/ka_lib_test');
			$this->model()->addPermission($group_id, 'access', 'extension/ka_extensions/ka_lib_test');
			$this->model()->addPermission($group_id, 'modify', 'extension/ka_extensions/ka_lib_test');

			$permissions = $this->getPermissions($group_id);
			$this->assertSame(array('common/dashboard', 'extension/ka_extensions/ka_lib_test'), $permissions['access']);
			$this->assertSame(array('extension/ka_extensions/ka_lib_test'), $permissions['modify']);
		} finally {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . "user_group` WHERE user_group_id = '" . (int)$group_id . "'");
		}
	}

	public function testAddPermissionToAGroupWithoutPermissions() {
		$this->db()->query("INSERT INTO `" . DB_PREFIX . "user_group` SET name = 'Empty test group', permission = ''");
		$group_id = (int)$this->db()->getLastId();
		try {
			$this->model()->addPermission($group_id, 'modify', 'extension/ka_extensions/ka_lib_test');
			$this->model()->addPermission($group_id, 'modify', 'extension/ka_extensions/ka_lib_test');
			$this->assertSame(1, $this->countPermission($group_id, 'modify', 'extension/ka_extensions/ka_lib_test'));
		} finally {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . "user_group` WHERE user_group_id = '" . (int)$group_id . "'");
		}
	}

	protected function extensionAction($action) {
		// the Ka Extensions page needs the modify permission
		$this->model()->addPermission($this->getUserGroupId(), 'modify', static::EXT_ROUTE);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$result = $this->oc->request(static::EXT_ROUTE . '/' . $action, array('extension' => 'ka_lib_test'));
		// install shows the list with the messages, uninstall keeps them in the session for the next page
		return $result['output'] . print_r($this->topMessages(), true);
	}

	public function testInstallCreatesTablesPermissionsAndDefaultSettings() {
		$this->assertStringContainsString('Extension has been installed successfully', $this->extensionAction('install'));

		$this->assertTrue($this->tableExists(static::ITEM_TABLE));
		$this->assertContains('name', array_column($this->queryRows("SHOW INDEXES FROM `" . DB_PREFIX . static::ITEM_TABLE . "`"), 'Key_name'));

		$group_id = $this->getUserGroupId();
		foreach (array('access', 'modify') as $type) {
			foreach (array(static::MODULE_ROUTE, static::ITEMS_ROUTE, static::ITEM_ROUTE) as $route) {
				$this->assertSame(1, $this->countPermission($group_id, $type, $route), "$type $route");
			}
		}

		$this->assertSame(array(
			'ka_lib_test_title'   => 'Default title',
			'ka_lib_test_enabled' => '1',
			'ka_lib_test_groups'  => array(1, 2),
			'ka_lib_test_mode'    => 'a',
		), $this->getSettings('ka_lib_test'));

		$this->assertNotEmpty($this->queryRow("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' AND `code` = 'ka_lib_test'"));

		// the installed version is a hidden value of the module
		$this->assertSame(array('ka_lib_test_hidden_version' => '1.0.0.0'), $this->getSettings('ka_lib_test_hidden'));
	}

	public function testReinstallDoesNotDuplicatePermissions() {
		$this->extensionAction('install');
		$this->extensionAction('uninstall');
		$this->assertStringContainsString('Extension has been installed successfully', $this->extensionAction('install'));

		$group_id = $this->getUserGroupId();
		foreach (array('access', 'modify') as $type) {
			foreach (array(static::MODULE_ROUTE, static::ITEMS_ROUTE, static::ITEM_ROUTE, static::EXT_ROUTE) as $route) {
				$this->assertSame(1, $this->countPermission($group_id, $type, $route), "$type $route");
			}
		}
	}

	public function testReinstallKeepsChangedSettings() {
		$this->extensionAction('install');
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_title', 'Changed');
		// the settings stay when the module is installed again over the installed one
		$this->assertTrue((bool)$this->oc->get('load')->controller(static::MODULE_ROUTE . '/install'));
		$settings = $this->getSettings('ka_lib_test');
		$this->assertSame('Changed', $settings['ka_lib_test_title']);
		$this->assertSame(array(1, 2), $settings['ka_lib_test_groups']);
	}

	public function testUninstallKeepsSettings() {
		$this->extensionAction('install');
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_title', 'Changed');
		$output = $this->extensionAction('uninstall');
		$this->assertStringContainsString('[type] => S', $output);
		$this->assertStringNotContainsString('[type] => E', $output);
		$this->assertSame('Changed', $this->getSettings('ka_lib_test')['ka_lib_test_title']);
		$this->assertSame(array('ka_lib_test_hidden_version' => '1.0.0.0'), $this->getSettings('ka_lib_test_hidden'));
		$this->assertEmpty($this->queryRow("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' AND `code` = 'ka_lib_test'"));
	}

	public function testUpgradeAddsMissingFieldAndIndex() {
		// a table of an older module version: no 'note' field and no 'name' index
		$this->createItemTable();
		$item_id = $this->addItem('Kept');

		$this->assertTrue((bool)$this->oc->get('load')->controller(static::MODULE_ROUTE . '/install'));
		$this->assertContains('note', $this->columns(static::ITEM_TABLE));
		$this->assertContains('name', array_column($this->queryRows("SHOW INDEXES FROM `" . DB_PREFIX . static::ITEM_TABLE . "`"), 'Key_name'));
		$this->assertSame('Kept', $this->getItem($item_id)['name']);

		// once more: nothing to change, no errors
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$this->assertTrue((bool)$this->oc->get('load')->controller(static::MODULE_ROUTE . '/install'));
		$this->assertSame(array(), $this->topMessages());
		$this->assertSame(1, count(array_keys($this->columns(static::ITEM_TABLE), 'note')));
	}

	public function testCheckDbCompatibilityMarksExistingParts() {
		$this->createItemTable();
		$tables = array(
			static::ITEM_TABLE => array(
				'fields'  => array('name' => array(), 'missing' => array()),
				'indexes' => array('PRIMARY' => array(), 'missing_index' => array()),
			),
			'ka_lib_test_no_table' => array('fields' => array('x' => array())),
		);
		$messages = array();
		$this->assertTrue($this->model()->checkDBCompatibility($tables, $messages));
		$this->assertTrue($tables[static::ITEM_TABLE]['exists']);
		$this->assertTrue($tables[static::ITEM_TABLE]['fields']['name']['exists']);
		$this->assertArrayNotHasKey('exists', $tables[static::ITEM_TABLE]['fields']['missing']);
		$this->assertTrue($tables[static::ITEM_TABLE]['indexes']['PRIMARY']['exists']);
		$this->assertArrayNotHasKey('exists', $tables[static::ITEM_TABLE]['indexes']['missing_index']);
		$this->assertArrayNotHasKey('exists', $tables['ka_lib_test_no_table']);
	}

	public function testPatchDbRefusesAFieldWithoutQuery() {
		$this->createItemTable();
		$tables = array(static::ITEM_TABLE => array('fields' => array('missing' => array('type' => 'int(11)'))));
		$messages = array();
		$this->model()->checkDBCompatibility($tables, $messages);
		$this->assertFalse($this->model()->patchDB($tables, $messages));
		$this->assertNotEmpty($messages);
		$this->assertNotContains('missing', $this->columns(static::ITEM_TABLE));
	}

	public function testIncompatibleLibraryVersionStopsTheInstallation() {
		require_once(modification(DIR_APPLICATION . 'controller/' . static::MODULE_ROUTE . '.php'));
		$controller = new \extension\ka_extensions\ControllerKaLibTest($this->oc->getRegistry());
		$property = new \ReflectionProperty($controller, 'min_ka_extensions_version');
		$property->setAccessible(true);
		$property->setValue($controller, '9.9.9.9');

		$this->oc->get('session')->data['ka_top_messages'] = null;
		$this->assertFalse($controller->install());
		$this->assertSame(array('E'), array_column($this->topMessages(), 'type'));
		$this->assertFalse($this->tableExists(static::ITEM_TABLE));
	}
}
