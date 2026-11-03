<?php

namespace KaExtensionsTests\Integration;

/**
 * Modules without $ext_code whose onLoad() does not call parent::onLoad() (fixtures ka_lib_noparent_a 2.0.0.1
 * and ka_lib_noparent_b 5.0.3.7): the code comes from the file name before onLoad(), every module stores its
 * own installed version. Before 4.1.1.32 the code stayed '' and all such modules shared the hidden value
 * '_hidden_version': Extensions > Ka Extensions showed 'Files: ..., installed: <the version of the module
 * installed last>' for all of them (seen on ka-station: 'installed: 1.0.3.0' for many modules).
 *
 * @group upgrade
 * @group installer
 */
class InstallerExtCodeTest extends LibraryTestCase {

	const EXT_ROUTE = 'extension/extension/ka_extensions';

	protected $codes = array('ka_lib_noparent_a' => '2.0.0.1', 'ka_lib_noparent_b' => '5.0.3.7');

	protected $saved_permissions;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		$this->cleanUp();

		$model = $this->oc->get('load')->kamodel('extension/ka_extensions');
		$model->addPermission($this->getUserGroupId(), 'access', static::EXT_ROUTE);
		$model->addPermission($this->getUserGroupId(), 'modify', static::EXT_ROUTE);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->cleanUp();
	}

	protected function cleanUp() {
		foreach (array_keys($this->codes) as $code) {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' AND `code` = '" . $this->esc($code) . "'");
			$this->deleteSettings($code . '_hidden');
		}
		$this->deleteSettings('_hidden');
	}

	protected function request($action, $code = '') {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$route = static::EXT_ROUTE . ($action ? '/' . $action : '');
		return $this->oc->request($route, array('extension' => $code));
	}

	protected function installedVersions() {
		$result = array();
		$rows = $this->db()->query("SELECT `code`, `value` FROM `" . DB_PREFIX . "setting` WHERE `key` LIKE '%\\_hidden\\_version' AND (`code` = '_hidden' OR `code` LIKE 'ka\\_lib\\_noparent\\_%')")->rows;
		foreach ($rows as $row) {
			$result[$row['code']] = $row['value'];
		}
		ksort($result);
		return $result;
	}

	public function testEveryModuleStoresItsOwnVersion() {
		foreach (array_keys($this->codes) as $code) {
			$this->assertStringContainsString('Extension has been installed successfully', $this->request('install', $code)['output'], $code);
		}

		$this->assertSame(array(
			'ka_lib_noparent_a_hidden' => '2.0.0.1',
			'ka_lib_noparent_b_hidden' => '5.0.3.7',
		), $this->installedVersions());

		// the versions are equal: no button, no hint
		$output = $this->request('')['output'];
		$this->assertStringNotContainsString('Files: ', $output);
		$this->assertStringNotContainsString('onKaRefresh(event, this)', $output);

		// an older installation of one module: the button and the hint for that module only
		$this->fixtures()->setSetting('ka_lib_noparent_a_hidden', 'ka_lib_noparent_a_hidden_version', '2.0.0.0');
		$output = $this->request('')['output'];
		$this->assertStringContainsString('<small>Files: 2.0.0.1, installed: 2.0.0.0</small>', $output);
		$this->assertSame(1, substr_count($output, 'installed: 2.0.0.0</small>'));
		$this->assertStringNotContainsString('Files: 5.0.3.7', $output);
		$this->assertSame(1, substr_count($output, 'onKaRefresh(event, this)'));
	}
}
