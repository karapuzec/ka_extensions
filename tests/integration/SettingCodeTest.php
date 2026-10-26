<?php

namespace KaExtensionsTests\Integration;

/**
 * ControllerSettings::getSettingCode(): the default values are installed to the setting group the page
 * saves, uninstallation keeps the saved values (4.1.1.29, used by the next installation). Vendor 'ka_extensions' (code 'ka_lib_test') and another
 * vendor (fixture extension/ka_lib_vendor/vtest, code 'ka_lib_vendor_vtest'). Before 4.1.1.29 the other
 * vendor got no defaults (they went to the group 'vtest' where OpenCart skips keys not starting with it)
 * and kept its saved values after uninstallation under the group 'ka_lib_vendor_vtest' while the defaults
 * went to 'vtest'.
 *
 * @group settings
 * @group installer
 */
class SettingCodeTest extends LibraryTestCase {

	const VENDOR_ROUTE = 'extension/ka_lib_vendor/vtest';

	protected $saved_permissions;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		$this->grantTestPages();

		$group_id = $this->getUserGroupId();
		$permissions = $this->getPermissions($group_id);
		foreach (array('access', 'modify') as $type) {
			$permissions[$type][] = static::VENDOR_ROUTE;
		}
		$this->setPermissions($group_id, $permissions);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);

		$this->cleanUp();
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->cleanUp();
	}

	protected function cleanUp() {
		foreach (array('ka_lib_test', 'ka_lib_vendor_vtest', 'vtest', 'ka_lib_test_hidden', 'ka_lib_vendor_vtest_hidden') as $code) {
			$this->deleteSettings($code);
		}
		$this->dropItemTable();
	}

	/*
		All rows of the settings with keys starting with $prefix: code => [key => value].
	*/
	protected function rowsByCode($prefix) {
		$rows = array();
		foreach ($this->queryRows("SELECT `code`, `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE `key` LIKE '" . $this->esc($prefix) . "%' ORDER BY setting_id") as $row) {
			$rows[$row['code']][$row['key']] = $row['value'];
		}
		return $rows;
	}

	protected function action($route) {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		return $this->oc->get('load')->controller($route);
	}

	protected function save($route, $post) {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$result = $this->oc->request($route, array(), $post);
		$this->assertStringContainsString('route=marketplace/extension', $result['redirect']);
		$this->assertSame(array('S'), array_column($this->topMessages(), 'type'));
		return $result;
	}

	public function testKaExtensionsVendorUsesOneGroup() {
		$this->assertTrue((bool)$this->action(static::MODULE_ROUTE . '/install'));
		$this->assertSame(array('ka_lib_test'), array_keys($this->rowsByCode('ka_lib_test_')));
		$this->assertSame('Default title', $this->rowsByCode('ka_lib_test_')['ka_lib_test']['ka_lib_test_title']);

		$this->save(static::MODULE_ROUTE, array('ka_lib_test_title' => 'Saved', 'ka_lib_test_mode' => 'b'));
		$rows = $this->rowsByCode('ka_lib_test_');
		$this->assertSame(array('ka_lib_test'), array_keys($rows));
		$this->assertSame('Saved', $rows['ka_lib_test']['ka_lib_test_title']);
		$this->assertSame(4, count($rows['ka_lib_test']));

		// uninstallation keeps the settings
		$this->assertTrue((bool)$this->action(static::MODULE_ROUTE . '/uninstall'));
		$this->assertSame($rows, $this->rowsByCode('ka_lib_test_'));
	}

	public function testOtherVendorDefaultsGoToTheGroupOfThePage() {
		$this->assertTrue((bool)$this->action(static::VENDOR_ROUTE . '/install'));
		$this->assertSame(array('ka_lib_vendor_vtest' => array(
			'ka_lib_vendor_vtest_title'   => 'Vendor default',
			'ka_lib_vendor_vtest_enabled' => '1',
		)), $this->rowsByCode('ka_lib_vendor_vtest_'));
		$this->assertSame(array(), $this->getSettings('vtest'));
	}

	public function testOtherVendorPageShowsInstalledDefaults() {
		$this->action(static::VENDOR_ROUTE . '/install');
		// the config of this process was loaded before the installation
		foreach ($this->getSettings('ka_lib_vendor_vtest') as $key => $value) {
			$this->oc->get('config')->set($key, $value);
		}
		$output = $this->oc->request(static::VENDOR_ROUTE)['output'];
		$this->assertStringContainsString('Ka Library Vendor Test (ver.1.0.0.0)', $output);
		$this->assertStringContainsString('value="Vendor default"', $output);
		$this->assertMatchesRegularExpression('/<form[^>]+action="[^"]*route=extension\/ka_lib_vendor\/vtest[^"]*"/', $output);
	}

	public function testOtherVendorSavesToTheInstalledGroup() {
		$this->action(static::VENDOR_ROUTE . '/install');
		$this->save(static::VENDOR_ROUTE, array('ka_lib_vendor_vtest_title' => 'Vendor saved'));

		// one group, one row per key: the saved values replaced the defaults (an unchecked box of the
		// settings page is saved as '', see SettingsTest::testSaveWithAllBoxesUnchecked)
		$this->assertSame(array('ka_lib_vendor_vtest' => array(
			'ka_lib_vendor_vtest_title'   => 'Vendor saved',
			'ka_lib_vendor_vtest_enabled' => '',
		)), $this->rowsByCode('ka_lib_vendor_vtest_'));
	}

	public function testOtherVendorReinstallKeepsSavedValues() {
		$this->action(static::VENDOR_ROUTE . '/install');
		$this->save(static::VENDOR_ROUTE, array('ka_lib_vendor_vtest_title' => 'Vendor saved', 'ka_lib_vendor_vtest_enabled' => '1'));
		$this->assertTrue((bool)$this->action(static::VENDOR_ROUTE . '/install'));
		$this->assertSame(array('ka_lib_vendor_vtest' => array(
			'ka_lib_vendor_vtest_title'   => 'Vendor saved',
			'ka_lib_vendor_vtest_enabled' => '1',
		)), $this->rowsByCode('ka_lib_vendor_vtest_'));
	}

	public function testOtherVendorUninstallKeepsSavedValues() {
		$this->action(static::VENDOR_ROUTE . '/install');
		$this->save(static::VENDOR_ROUTE, array('ka_lib_vendor_vtest_title' => 'Vendor saved'));
		$this->assertTrue((bool)$this->action(static::VENDOR_ROUTE . '/uninstall'));
		$this->assertSame(array('ka_lib_vendor_vtest' => array(
			'ka_lib_vendor_vtest_title'   => 'Vendor saved',
			'ka_lib_vendor_vtest_enabled' => '',
		)), $this->rowsByCode('ka_lib_vendor_vtest_'));
	}
}
