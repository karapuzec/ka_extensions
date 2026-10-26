<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\ControllerSettings;

// ControllerInstaller looks for the kamod.ini of the module (there is none)
if (!\defined('DIR_APPLICATION')) {
	\define('DIR_APPLICATION', sys_get_temp_dir() . '/ka_unit_no_admin/');
}

class FakeSettingModel {
	public $calls = array();
	public $stored = array();

	public function getSetting($code, $store_id = 0) {
		$this->calls[] = array('getSetting', $code);
		return isset($this->stored[$code]) ? $this->stored[$code] : array();
	}

	public function editSetting($code, $data, $store_id = 0) {
		$this->calls[] = array('editSetting', $code, $data);
		$this->stored[$code] = $data;
	}

	public function deleteSetting($code, $store_id = 0) {
		$this->calls[] = array('deleteSetting', $code);
		unset($this->stored[$code]);
	}

	public function codes() {
		return array_values(array_unique(array_column($this->calls, 1)));
	}
}

class FakeKaExtensionsModel {
	public function checkDBCompatibility(&$tables, &$messages) {
		return true;
	}

	public function patchDB(&$tables, &$messages) {
		return true;
	}

	public function addPermission($user_group_id, $type, $route) {
	}
}

class FakeUser {
	public function hasPermission($type, $route) {
		return true;
	}

	public function getGroupId() {
		return 1;
	}
}

class TestSettingsController extends ControllerSettings {
	protected $min_ka_extensions_version = '0.0.0.0';
	protected $max_ka_extensions_version = '99.0.0.0';
	protected $vendor   = 'ka_extensions';
	protected $ext_code = 'ka_test';

	protected function getFields() {
		return array(
			'ka_test_title' => array('code' => 'ka_test_title', 'type' => 'text', 'default_value' => 'Default'),
		);
	}
}

class TestVendorSettingsController extends ControllerSettings {
	protected $min_ka_extensions_version = '0.0.0.0';
	protected $max_ka_extensions_version = '99.0.0.0';
	protected $vendor   = 'acme';
	protected $ext_code = 'tool';

	protected function getFields() {
		return array(
			'acme_tool_title' => array('code' => 'acme_tool_title', 'type' => 'text', 'default_value' => 'Default'),
		);
	}
}

class TestOverriddenSettingsController extends TestVendorSettingsController {
	protected function getSettingCode() {
		return 'acme';
	}
}

/**
 * ControllerSettings: one setting code (getSettingCode()) for saving, installation and uninstallation.
 *
 * @group settings
 */
class SettingsControllerTest extends UnitTestCase {

	/** @var FakeSettingModel */
	protected $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new FakeSettingModel();
		$this->registry->set('model_setting_setting', $this->settings);
		$this->registry->set('user', new FakeUser());
		$ka_model = new FakeKaExtensionsModel();
		$this->registry->get('load')->setModel('extension/ka_extensions', $ka_model);
		$this->registry->set('model_extension_ka_extensions', $ka_model);
	}

	public function codeProvider() {
		return array(
			'vendor ka_extensions' => array(TestSettingsController::class, 'ka_test', 'ka_test_title'),
			'another vendor'       => array(TestVendorSettingsController::class, 'acme_tool', 'acme_tool_title'),
			'overridden'           => array(TestOverriddenSettingsController::class, 'acme', 'acme_tool_title'),
		);
	}

	/**
	 * @dataProvider codeProvider
	 */
	public function testSettingCode($class, $code, $key) {
		$controller = new $class($this->registry);
		$this->assertSame($code, $this->call($controller, 'getSettingCode'));
	}

	/**
	 * @dataProvider codeProvider
	 */
	public function testInstallSaveAndUninstallUseTheSameCode($class, $code, $key) {
		$controller = new $class($this->registry);

		$this->assertTrue($controller->install());
		$this->assertSame(array($key => 'Default'), $this->settings->stored[$code]);

		$request = $this->registry->get('request');
		$request->server['REQUEST_METHOD'] = 'POST';
		$request->post = array($key => 'Saved');
		try {
			$controller->index();
			$this->fail('no redirect after saving');
		} catch (\TestRedirectException $e) {
		}
		$this->assertSame(array($key => 'Saved'), $this->settings->stored[$code]);

		// installation over an installed module keeps the saved values
		$this->assertTrue($controller->install());
		$this->assertSame(array($key => 'Saved'), $this->settings->stored[$code]);

		$this->assertTrue($controller->uninstall());
		$this->assertSame(array(), $this->settings->stored);
		$this->assertSame(array($code), $this->settings->codes());
	}
}
