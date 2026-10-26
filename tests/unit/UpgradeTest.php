<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\ControllerSettings;
use extension\ka_extensions\ka_extensions\ModelSettingSetting;

// ControllerInstaller looks for the kamod.ini of the module (there is none)
if (!\defined('DIR_APPLICATION')) {
	\define('DIR_APPLICATION', sys_get_temp_dir() . '/ka_unit_no_admin/');
}

// the kamod child class of admin/model/setting/setting.php (getConfigHidden, setConfigHidden)
require_once(KA_LIB_ROOT . 'admin/model/extension/ka_extensions/ka_extensions/model/setting/setting.php');

class UpgradeKaExtensionsModel {
	public $patched = 0;

	public function checkDBCompatibility(&$tables, &$messages) {
		return true;
	}

	public function patchDB(&$tables, &$messages) {
		$this->patched++;
		return true;
	}

	public function addPermission($user_group_id, $type, $route) {
	}
}

class UpgradeUser {
	public function hasPermission($type, $route) {
		return true;
	}

	public function getGroupId() {
		return 1;
	}
}

class UpgradeTestController extends ControllerSettings {
	protected $extension_version = '1.0.1.0';
	protected $min_ka_extensions_version = '0.0.0.0';
	protected $max_ka_extensions_version = '99.0.0.0';
	protected $vendor   = 'ka_extensions';
	protected $ext_code = 'ka_up';

	protected function getFields() {
		return array(
			'ka_up_title' => array('code' => 'ka_up_title', 'type' => 'text', 'default_value' => 'Default'),
			'ka_up_limit' => array('code' => 'ka_up_limit', 'type' => 'text', 'default_value' => '10'),
		);
	}
}

class UpgradeVendorTestController extends UpgradeTestController {
	protected $vendor   = 'acme';
	protected $ext_code = 'tool';
}

/**
 * Module upgrade: hidden values of a module (getConfigHidden / setConfigHidden of the setting model),
 * the installed version and the default upgradeExtension().
 *
 * @group upgrade
 */
class UpgradeTest extends UnitTestCase {

	/** @var ModelSettingSetting */
	protected $settings;

	/** @var UpgradeKaExtensionsModel */
	protected $ka_model;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new ModelSettingSetting($this->registry);
		$this->registry->set('model_setting_setting', $this->settings);
		$this->registry->set('user', new UpgradeUser());
		$this->ka_model = new UpgradeKaExtensionsModel();
		$this->registry->get('load')->setModel('extension/ka_extensions', $this->ka_model);
		$this->registry->set('model_extension_ka_extensions', $this->ka_model);
	}

	protected function rows($code) {
		$rows = array();
		foreach ($this->settings->rows as $row) {
			if ($row['code'] == $code) {
				$rows[$row['key']] = $row['value'];
			}
		}
		return $rows;
	}

	public function testMissingHiddenValueIsNull() {
		$this->assertNull($this->settings->getConfigHidden('ka_up', 'version'));
	}

	public function testHiddenValueIsStoredInTheHiddenGroup() {
		$this->settings->setConfigHidden('ka_up', 'version', '1.0.1.0');

		$this->assertSame('1.0.1.0', $this->settings->getConfigHidden('ka_up', 'version'));
		$this->assertSame(array('ka_up_hidden_version' => '1.0.1.0'), $this->rows('ka_up_hidden'));
		$this->assertSame(array(), $this->rows('ka_up'));
	}

	public function testOtherHiddenValuesAreKept() {
		// counters of a module stored by the module itself
		$this->settings->editSetting('ka_up_hidden', array(
			'ka_up_hidden_requests' => '15',
			'ka_up_hidden_users'    => array(1 => 'a', 2 => 'b'),
		));

		$this->settings->setConfigHidden('ka_up', 'version', '1.0.1.0');
		$this->settings->setConfigHidden('ka_up', 'version', '1.0.2.0');

		$this->assertSame('15', $this->settings->getConfigHidden('ka_up', 'requests'));
		$this->assertSame(array(1 => 'a', 2 => 'b'), $this->settings->getConfigHidden('ka_up', 'users'));
		$this->assertSame('1.0.2.0', $this->settings->getConfigHidden('ka_up', 'version'));
		$this->assertCount(3, $this->rows('ka_up_hidden'));
	}

	public function testArrayAndScalarValues() {
		$this->settings->setConfigHidden('ka_up', 'list', array('x' => array(1, 2)));
		$this->settings->setConfigHidden('ka_up', 'count', 5);
		$this->settings->setConfigHidden('ka_up', 'flag', true);

		$this->assertSame(array('x' => array(1, 2)), $this->settings->getConfigHidden('ka_up', 'list'));
		$this->assertSame('5', $this->settings->getConfigHidden('ka_up', 'count'));
		$this->assertSame('1', $this->settings->getConfigHidden('ka_up', 'flag'));
	}

	public function testNullRemovesTheKey() {
		$this->settings->setConfigHidden('ka_up', 'version', '1.0.1.0');
		$this->settings->setConfigHidden('ka_up', 'count', '3');
		$this->settings->setConfigHidden('ka_up', 'version', null);

		$this->assertNull($this->settings->getConfigHidden('ka_up', 'version'));
		$this->assertSame(array('ka_up_hidden_count' => '3'), $this->rows('ka_up_hidden'));
	}

	public function testRegularSettingsAndOtherModulesAreNotTouched() {
		$this->settings->editSetting('ka_up', array('ka_up_title' => 'Saved'));
		$this->settings->editSetting('ka_up2_hidden', array('ka_up2_hidden_version' => '2.0.0.0'));

		$this->settings->setConfigHidden('ka_up', 'version', '1.0.1.0');

		$this->assertSame(array('ka_up_title' => 'Saved'), $this->rows('ka_up'));
		$this->assertSame('2.0.0.0', $this->settings->getConfigHidden('ka_up2', 'version'));
	}

	public function installerProvider() {
		return array(
			'vendor ka_extensions' => array(UpgradeTestController::class, 'ka_up', 'ka_up_title'),
			'another vendor'       => array(UpgradeVendorTestController::class, 'acme_tool', 'ka_up_title'),
		);
	}

	/**
	 * @dataProvider installerProvider
	 */
	public function testInstalledVersion($class, $code, $key) {
		$controller = new $class($this->registry);

		$this->assertSame('0.0.0.0', $controller->getInstalledVersion(), 'no version stored (old installation)');

		$this->assertTrue($controller->saveInstalledVersion($controller->getVersion()));
		$this->assertSame('1.0.1.0', $controller->getInstalledVersion());
		$this->assertSame(array($code . '_hidden_version' => '1.0.1.0'), $this->rows($code . '_hidden'));
	}

	public function testDefaultUpgradeRunsInstallAndKeepsSettings() {
		$controller = new UpgradeTestController($this->registry);
		// settings of the older version: no 'limit' field yet
		$this->settings->editSetting('ka_up', array('ka_up_title' => 'Saved'));

		$this->assertTrue($controller->upgradeExtension('1.0.0.0', '1.0.1.0'));

		$this->assertSame(1, $this->ka_model->patched);
		$this->assertSame(array('ka_up_title' => 'Saved', 'ka_up_limit' => '10'), $this->settings->getSetting('ka_up'));
		// the version is stored by the caller after a successful upgrade
		$this->assertSame('0.0.0.0', $controller->getInstalledVersion());
	}

	public function testSavingTheSettingsPageKeepsHiddenValues() {
		$controller = new UpgradeTestController($this->registry);
		$controller->saveInstalledVersion('1.0.1.0');

		$request = $this->registry->get('request');
		$request->server['REQUEST_METHOD'] = 'POST';
		$request->post = array('ka_up_title' => 'Posted', 'ka_up_limit' => '20');
		try {
			$controller->index();
			$this->fail('no redirect after saving');
		} catch (\TestRedirectException $e) {
		}

		$this->assertSame('Posted', $this->settings->getSetting('ka_up')['ka_up_title']);
		$this->assertSame('1.0.1.0', $controller->getInstalledVersion());
	}

	public function testUninstallKeepsSettingsAndVersion() {
		$controller = new UpgradeTestController($this->registry);
		$this->assertTrue($controller->install());
		$controller->saveInstalledVersion('1.0.1.0');

		$this->assertTrue($controller->uninstall());

		$this->assertSame(array('ka_up_title' => 'Default', 'ka_up_limit' => '10'), $this->settings->getSetting('ka_up'));
		$this->assertSame('1.0.1.0', $controller->getInstalledVersion());
	}

	public function versionProvider() {
		return array(
			'newer files'                => array('1.0.1.0', '1.0.0.0', true),
			'old installation'           => array('1.0.0.0', '0.0.0.0', true),
			'same version'               => array('1.0.1.0', '1.0.1.0', false),
			'older files'                => array('1.0.0.0', '1.0.1.0', false),
			'numbers, not strings'       => array('1.0.10.0', '1.0.9.0', true),
			'module without a version'   => array('0.0.0', '0.0.0.0', false),
		);
	}

	/**
	 * The Ka Extensions page shows the 'Refresh' button when the files are newer (version_compare).
	 *
	 * @dataProvider versionProvider
	 */
	public function testRefreshIsOfferedForNewerFiles($files, $installed, $expected) {
		$this->assertSame($expected, version_compare($files, $installed, '>'));
	}
}
