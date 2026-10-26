<?php

namespace KaExtensionsTests\Integration;

/**
 * Module upgrade: the installed version, the 'Refresh' button of Extensions > Ka Extensions and the
 * upgrade request (POST only, modify permission, upgradeExtension() of the module, the version is
 * stored after a successful upgrade only, the settings stay).
 *
 * The test module ka_lib_test has version 1.0.0.0 in its files, an older installation is made by
 * storing an older version in its hidden values.
 *
 * @group upgrade
 * @group installer
 */
class UpgradeTest extends LibraryTestCase {

	const EXT_ROUTE = 'extension/extension/ka_extensions';

	protected $saved_permissions;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		$this->cleanUp();
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->cleanUp();
	}

	protected function cleanUp() {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' AND `code` = 'ka_lib_test'");
		$this->deleteSettings('ka_lib_test');
		$this->deleteSettings('ka_lib_test_hidden');
		$this->dropItemTable();
	}

	protected function model() {
		return $this->oc->get('load')->kamodel('extension/ka_extensions');
	}

	protected function allowExtensionsPage() {
		$this->model()->addPermission($this->getUserGroupId(), 'access', static::EXT_ROUTE);
		$this->model()->addPermission($this->getUserGroupId(), 'modify', static::EXT_ROUTE);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
	}

	protected function request($action, $post = array()) {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$route = static::EXT_ROUTE . ($action ? '/' . $action : '');
		return $this->oc->request($route, array('extension' => 'ka_lib_test'), $post);
	}

	protected function install() {
		$this->allowExtensionsPage();
		$output = $this->request('install')['output'];
		$this->assertStringContainsString('Extension has been installed successfully', $output);
	}

	protected function listPage() {
		return $this->request('')['output'];
	}

	protected function installedVersion() {
		$settings = $this->getSettings('ka_lib_test_hidden');
		return isset($settings['ka_lib_test_hidden_version']) ? $settings['ka_lib_test_hidden_version'] : null;
	}

	protected function setInstalledVersion($version) {
		$this->fixtures()->setSetting('ka_lib_test_hidden', 'ka_lib_test_hidden_version', $version);
	}

	protected function messageTypes() {
		return array_column($this->topMessages(), 'type');
	}

	public function testInstallStoresTheVersionAndNoButton() {
		$this->install();
		$this->assertSame('1.0.0.0', $this->installedVersion());
		$this->assertStringNotContainsString('onKaRefresh(event', $this->listPage());
	}

	public function testButtonForNewerFiles() {
		$this->install();
		$this->setInstalledVersion('0.9.0.0');

		$output = $this->listPage();
		$this->assertStringContainsString('class="btn btn-warning" onclick="javascript: onKaRefresh(event, this);"', $output);
		$this->assertStringContainsString('route=extension/extension/ka_extensions/upgrade', $output);
		$this->assertStringContainsString('Files: 1.0.0.0, installed: 0.9.0.0', $output);
	}

	public function testOldInstallationWithoutVersionIsZero() {
		$this->install();
		$this->deleteSettings('ka_lib_test_hidden');
		$this->assertStringContainsString('Files: 1.0.0.0, installed: 0.0.0.0', $this->listPage());
	}

	public function testRefreshUpgradesAndKeepsSettings() {
		$this->install();
		$this->setInstalledVersion('0.9.0.0');
		$this->fixtures()->setSetting('ka_lib_test', 'ka_lib_test_title', 'Changed');
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'ka_lib_test_mode'");
		// a table of the older version is missing
		$this->dropItemTable();

		$result = $this->request('upgrade', array('confirm' => '1'));
		$this->assertSame(array(), $result['json']);
		$this->assertSame(array('S'), $this->messageTypes());
		$this->assertStringContainsString('refreshed to version 1.0.0.0', print_r($this->topMessages(), true));

		$this->assertSame('1.0.0.0', $this->installedVersion());
		$settings = $this->getSettings('ka_lib_test');
		$this->assertSame('Changed', $settings['ka_lib_test_title'], 'the saved value stays');
		$this->assertSame('a', $settings['ka_lib_test_mode'], 'a new setting gets its default value');
		$this->assertTrue($this->tableExists(static::ITEM_TABLE), 'install() patched the database');
		// the migration of the module got the installed version
		$this->assertSame('0.9.0.0', $this->getSettings('ka_lib_test_hidden')['ka_lib_test_hidden_migrated_from']);

		$this->assertFalse(\extension\ka_extensions\KamodManager::getInstance()->isKamodCacheValid());
		foreach (array('access', 'modify') as $type) {
			foreach (array(static::MODULE_ROUTE, static::ITEMS_ROUTE, static::ITEM_ROUTE) as $route) {
				$this->assertSame(1, $this->countPermission($this->getUserGroupId(), $type, $route), "$type $route");
			}
		}

		// nothing to refresh any more
		$this->assertStringNotContainsString('onKaRefresh(event', $this->listPage());
		$this->request('upgrade', array('confirm' => '1'));
		$this->assertSame(array('E'), $this->messageTypes());
		$this->assertStringContainsString('nothing to refresh', print_r($this->topMessages(), true));
	}

	public function testOldInstallationWithoutVersionIsRefreshed() {
		$this->install();
		$this->deleteSettings('ka_lib_test_hidden');

		$this->request('upgrade', array('confirm' => '1'));
		$this->assertSame(array('S'), $this->messageTypes());
		$this->assertSame('1.0.0.0', $this->installedVersion());
		$this->assertSame('0.0.0.0', $this->getSettings('ka_lib_test_hidden')['ka_lib_test_hidden_migrated_from']);
	}

	public function testGetRequestDoesNotUpgrade() {
		$this->install();
		$this->setInstalledVersion('0.9.0.0');

		$this->request('upgrade');
		$this->assertSame(array('E'), $this->messageTypes());
		$this->assertSame('0.9.0.0', $this->installedVersion());
	}

	public function testUpgradeNeedsTheModifyPermission() {
		$this->install();
		$this->setInstalledVersion('0.9.0.0');

		$permissions = $this->getPermissions($this->getUserGroupId());
		$permissions['modify'] = array_values(array_diff($permissions['modify'], array(static::EXT_ROUTE)));
		$this->setPermissions($this->getUserGroupId(), $permissions);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);

		$this->request('upgrade', array('confirm' => '1'));
		$this->assertSame(array('E'), $this->messageTypes());
		$this->assertSame('0.9.0.0', $this->installedVersion());
	}

	public function testFailedUpgradeKeepsTheVersion() {
		$this->install();
		$this->setInstalledVersion('0.9.0.0');
		$this->fixtures()->setSetting('ka_lib_test_hidden', 'ka_lib_test_hidden_fail_upgrade', '1');

		$this->request('upgrade', array('confirm' => '1'));
		$this->assertContains('E', $this->messageTypes());
		$this->assertNotContains('S', $this->messageTypes());
		$this->assertStringContainsString('the installed version 0.9.0.0 is kept', print_r($this->topMessages(), true));
		$this->assertSame('0.9.0.0', $this->installedVersion());
		$this->assertStringContainsString('onKaRefresh(event', $this->listPage(), 'the button stays');
	}

	public function testNotInstalledModuleIsNotUpgraded() {
		$this->allowExtensionsPage();
		$this->setInstalledVersion('0.9.0.0');

		$this->request('upgrade', array('confirm' => '1'));
		$this->assertSame(array('E'), $this->messageTypes());
		$this->assertStringContainsString('Extension is not installed', print_r($this->topMessages(), true));
		$this->assertSame('0.9.0.0', $this->installedVersion());
	}

	public function testRussianStrings() {
		$this->install();
		$this->setInstalledVersion('0.9.0.0');
		$language = new \Language('ru-ru');
		$language->load('extension/extension/ka_extensions');
		$this->assertSame('Обновить', $language->get('button_refresh_extension'));
		$this->assertSame('Файлы: %s, установлено: %s', $language->get('text_refresh_hint'));
		// strings missing in ru-ru are taken from en-gb
		$this->assertSame('Ka Extensions', $language->get('heading_title'));
	}
}
