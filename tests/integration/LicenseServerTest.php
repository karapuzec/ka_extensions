<?php

namespace KaExtensionsTests\Integration;

/**
 * Requests to the license server: the API of ka_licenses (api/checkModules, api/linkLicense, api_version 1) with
 * the fallback to the old scripts domain_info and register_key; a server that is not available.
 *
 * The server is tests/fixtures/license_server/router.php started with the PHP built-in server for every test;
 * the test writes its mode ('new', 'old', 'error') and the states of modules, the server logs the requests.
 * The real server side (ka_licenses, ka-station scripts) is tested in the ka_licenses repository.
 *
 * @group license
 */
class LicenseServerTest extends LibraryTestCase {

	const EXT_ROUTE = 'extension/extension/ka_extensions';

	protected $server;
	protected $config_file;
	protected $log_file;
	protected $saved_permissions;

	protected function setUp(): void {
		$port = mt_rand(20000, 40000);
		$this->config_file = tempnam(sys_get_temp_dir(), 'kals_cfg');
		$this->log_file    = tempnam(sys_get_temp_dir(), 'kals_log');
		putenv('KA_TEST_LICENSE_SERVER_CONFIG=' . $this->config_file);
		putenv('KA_TEST_LICENSE_SERVER_LOG=' . $this->log_file);
		$this->server = proc_open(array(PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/../fixtures/license_server/router.php'),
			array(array('file', '/dev/null', 'r'), array('file', '/dev/null', 'w'), array('file', '/dev/null', 'w')), $pipes);
		for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
			usleep(100000);
		}
		define('KA_STORE_URL', 'http://127.0.0.1:' . $port . '/');

		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		$this->deleteSettings('kareg');
		$this->deleteSettings('kareg_ka_lib_vendor');
	}

	protected function tearDown(): void {
		if ($this->server) {
			proc_terminate($this->server);
			proc_close($this->server);
		}
		@unlink($this->config_file);
		@unlink($this->log_file);
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->deleteSettings('kareg');
		$this->deleteSettings('kareg_ka_lib_vendor');
	}

	protected function server($mode, $modules = array()) {
		file_put_contents($this->config_file, json_encode(array('mode' => $mode, 'modules' => $modules, 'key' => 'GOOD-KEY')));
	}

	protected function requests() {
		$result = array();
		foreach (file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			$result[] = json_decode($line, true);
		}
		return $result;
	}

	protected function model() {
		return $this->oc->get('load')->kamodel('extension/ka_extensions');
	}

	protected function grant() {
		$model = $this->model();
		$model->addPermission($this->getUserGroupId(), 'access', static::EXT_ROUTE);
		$model->addPermission($this->getUserGroupId(), 'modify', static::EXT_ROUTE);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
	}

	protected function routes() {
		return array_column($this->requests(), 'route');
	}

	protected function kareg($key) {
		$row = $this->queryRow("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = '" . $this->esc($key) . "'");
		return $row ? json_decode($row['value'], true) : null;
	}

	protected function setKareg($key, $value) {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = '" . $this->esc($key) . "'");
		$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, `code` = 'kareg', `key` = '" . $this->esc($key) . "',
			`value` = '" . $this->esc(json_encode($value)) . "', serialized = 1");
		if (!$this->queryRow("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = 'kareg'")) {
			$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, `code` = 'kareg', `key` = 'kareg', `value` = '1', serialized = 0");
		}
	}

	protected function row($output, $title) {
		foreach (preg_split('#<tr[ >]#', $output) as $row) {
			if (strpos($row, $title) !== false) {
				return $row;
			}
		}
		$this->fail("No row of $title on the list page");
	}

	protected function licenseText($row) {
		$cells = preg_split('#<td[^>]*>#', $row);
		return trim(preg_replace('/\s+/', ' ', strip_tags($cells[3])));
	}

	protected function findModule($request, $code) {
		foreach ($request['post']['modules'] as $module) {
			if ($module['code'] == $code) {
				return $module;
			}
		}
		return null;
	}

	const API = 'extension/ka_extensions/ka_licenses/api/';

	public function testNewServerReturnsStatesOfAllModules() {
		$this->server('new', array('ka_lib_test' => 'registered', 'ka_lib_lite' => 'free'));

		$extensions = $this->model()->checkModules('ka_extensions');

		$requests = $this->requests();
		$this->assertCount(1, $requests);
		$this->assertSame(self::API . 'checkModules', $requests[0]['route'], 'the API of ka_licenses');
		$this->assertSame(array('api_version', 'url', 'opencart_version', 'modules'), array_keys($requests[0]['post']));
		$this->assertSame('1', $requests[0]['post']['api_version']);
		$this->assertSame(VERSION, $requests[0]['post']['opencart_version']);
		$this->assertSame(array('vendor' => 'ka_extensions', 'code' => 'ka_lib_test', 'edition' => 'oc3'), $this->findModule($requests[0], 'ka_lib_test'));
		$this->assertNotNull($this->findModule($requests[0], 'ka_lib_lite'), 'all installed modules are sent');

		$this->assertSame(array('masked_key' => 'GOOD-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'GOOD-XXXX-XXXX-XXXX-XXXX', 'is_registered' => 1,
			'expiry_date' => '2030-01-01'), $extensions['ka_lib_test']);
		$this->assertSame(1, $extensions['ka_lib_lite']['is_free']);

		$this->server('new', array('ka_lib_test' => 'expired'));
		$this->assertArrayNotHasKey('ka_lib_test', $this->model()->checkModules('ka_extensions'), 'an expired license is not registered');
	}

	public function testSameCodeOfAnotherEditionIsNotRegistered() {
		$this->server('new', array('ka_lib_test@oc4' => 'registered'));
		$this->assertArrayNotHasKey('ka_lib_test', $this->model()->checkModules('ka_extensions'));

		$this->server('new', array('ka_lib_test@oc3' => 'registered', 'ka_lib_test@oc4' => 'expired'));
		$this->assertSame(1, $this->model()->checkModules('ka_extensions')['ka_lib_test']['is_registered']);
	}

	public function testListPageWithTheNewServer() {
		$this->grant();
		$this->server('new', array('ka_lib_test' => 'registered', 'ka_lib_lite' => 'expired'));

		$this->oc->request(static::EXT_ROUTE);

		$this->assertSame(array(self::API . 'checkModules'), $this->routes(), 'the old script is not called');
		$model = $this->oc->get('load')->kamodel('extension/ka_extensions');
		$this->assertTrue($model->isRegistered('ka_lib_test', 'ka_extensions'));
		$this->assertFalse($model->isRegistered('ka_lib_lite', 'ka_extensions'));
	}

	public function testOldServerFallbackToTheOldScript() {
		$this->grant();
		foreach (array('old' => '404 of the API', 'error' => 'an error of the API') as $mode => $case) {
			$this->deleteSettings('kareg');
			$this->server($mode, array('ka_lib_test' => 'registered'));
			file_put_contents($this->log_file, '');

			$this->oc->request(static::EXT_ROUTE);

			$requests = $this->requests();
			$this->assertSame(array(self::API . 'checkModules', 'extension/domain_info'), array_column($requests, 'route'), $case);
			$this->assertSame(array('url', 'protocol_version', 'opencart_version', 'vendor', 'extensions'), array_keys($requests[1]['post']),
				"$case: the old request as before");
			$this->assertTrue($this->oc->get('load')->kamodel('extension/ka_extensions')->isRegistered('ka_lib_test', 'ka_extensions'), $case);
		}
	}

	public function testRegisterKeyOnTheNewServer() {
		$this->server('new');
		$model = $this->model();

		$this->assertFalse($model->registerKey('WRONG', 'ka_lib_test'));
		$this->assertSame('Invalid license key', $model->getLastError());
		$this->assertSame(array(self::API . 'linkLicense'), $this->routes(), 'an error of the API is the answer, no old request');

		$this->assertTrue($model->registerKey('GOOD-KEY', 'ka_lib_test'));
		$requests = $this->requests();
		$this->assertSame(self::API . 'linkLicense', $requests[1]['route']);
		$this->assertSame(array('api_version' => '1', 'license_key' => 'GOOD-KEY', 'surface_uri' => HTTP_CATALOG, 'vendor' => 'ka_extensions',
			'code' => 'ka_lib_test', 'edition' => 'oc3', 'opencart_version' => VERSION), $requests[1]['post']);
		$this->assertTrue($model->isRegistered('ka_lib_test', 'ka_extensions'));

		$this->assertTrue($model->registerKey('GOOD-KEY', 'ka_lib_vendor/vtest'));
		$this->assertSame('ka_lib_vendor', $this->requests()[2]['post']['vendor']);
		$this->assertSame('vtest', $this->requests()[2]['post']['code']);
		$this->assertTrue($model->isRegistered('vtest', 'ka_lib_vendor'));
	}

	public function testRegisterKeyOnTheOldServer() {
		$this->server('old');
		$model = $this->model();

		$this->assertFalse($model->registerKey('WRONG', 'ka_lib_test'));
		$this->assertStringStartsWith('The license key (WRONG) was not found', $model->getLastError());

		$this->assertTrue($model->registerKey('GOOD-KEY', 'ka_lib_test'));
		$requests = $this->requests();
		$this->assertSame(array(self::API . 'linkLicense', 'extension/register_key'), array_column(array_slice($requests, 2), 'route'));
		$this->assertSame(array('key' => 'GOOD-KEY', 'url' => HTTP_CATALOG, 'extension' => 'ka_lib_test'), $requests[3]['post'], 'the old request as before');
		$this->assertTrue($model->isRegistered('ka_lib_test', 'ka_extensions'));
	}

	/**
	 * The license server is not available: no module is disabled, the saved states are kept, the list shows
	 * the modules as before and the admin pages work. Both requests are tried: the API and the old script.
	 *
	 * @dataProvider failures
	 */
	public function testServerNotAvailableChangesNothing($mode) {
		$this->grant();
		$registered = array('masked_key' => 'IJKL-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'IJKL-XXXX-XXXX-XXXX-XXXX', 'expiry_date' => '2030-01-01',
			'is_registered' => 1, 'url' => HTTP_CATALOG);
		$this->setKareg('karegka_lib_test', $registered);
		$installed = $this->queryRows("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' ORDER BY extension_id");
		$settings = $this->queryRows("SELECT `code`, `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE `code` <> 'kareg' ORDER BY setting_id");
		if ($mode == 'down') {
			proc_terminate($this->server);
			proc_close($this->server);
			$this->server = null;
		} else {
			$this->server($mode, array('ka_lib_test' => 'registered'));
		}

		$output = $this->oc->request(static::EXT_ROUTE)['output'];

		if ($mode != 'down') {
			$this->assertSame(array(self::API . 'checkModules', 'extension/domain_info'), $this->routes(), 'the API, then the old script');
		}
		$this->assertStringNotContainsString('Fatal error', $output);
		$this->assertStringNotContainsString('Warning:', $output);
		$row = $this->row($output, 'Ka Library Test');
		$this->assertStringContainsString('Registered', $this->licenseText($row), 'the module is shown as licensed');
		$this->assertStringContainsString('IJKL-XXXX-XXXX-XXXX-XXXX', $row);
		$this->assertStringNotContainsString('Invalid License', $row);
		$this->assertSame($registered, $this->kareg('karegka_lib_test'), 'the saved state is kept');
		$this->assertSame($installed, $this->queryRows("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'ka_extensions' ORDER BY extension_id"),
			'no module is uninstalled');
		$this->assertSame($settings, $this->queryRows("SELECT `code`, `key`, `value` FROM `" . DB_PREFIX . "setting` WHERE `code` <> 'kareg' ORDER BY setting_id"),
			'no setting (statuses of modules) is changed');

		// the registration fails with a message, nothing is changed
		$model = $this->oc->get('load')->kamodel('extension/ka_extensions');
		$this->assertFalse($model->registerKey('GOOD-KEY', 'ka_lib_test'));
		$this->assertSame($registered, $this->kareg('karegka_lib_test'));
	}

	public function failures() {
		return array('connection refused' => array('down'), 'HTTP 500' => array('500'), 'empty answer' => array('empty'),
			'invalid json' => array('invalid'), 'timeout' => array('timeout'));
	}
}
