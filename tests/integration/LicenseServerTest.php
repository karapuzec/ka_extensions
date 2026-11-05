<?php

namespace KaExtensionsTests\Integration;

/**
 * Requests to the license server: checkModules (api_version 1) with the fallback to the old domain_info and
 * register_key answers.
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
		proc_terminate($this->server);
		proc_close($this->server);
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

	protected function findModule($request, $code) {
		foreach ($request['post']['modules'] as $module) {
			if ($module['code'] == $code) {
				return $module;
			}
		}
		return null;
	}

	public function testNewServerReturnsStatesOfAllModules() {
		$this->server('new', array('ka_lib_test' => 'registered', 'ka_lib_lite' => 'free'));

		$extensions = $this->model()->checkModules('ka_extensions');

		$requests = $this->requests();
		$this->assertCount(1, $requests);
		$this->assertSame('extension/domain_info', $requests[0]['route']);
		$this->assertSame('1', $requests[0]['post']['api_version']);
		$this->assertSame(VERSION, $requests[0]['post']['opencart_version']);
		$this->assertSame(array('vendor' => 'ka_extensions', 'code' => 'ka_lib_test', 'edition' => 'oc3'), $this->findModule($requests[0], 'ka_lib_test'));
		$this->assertNotNull($this->findModule($requests[0], 'ka_lib_lite'), 'all installed modules are sent');
		$this->assertArrayHasKey('extensions', $requests[0]['post'], 'the old fields for an old server');

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

	public function testOldServerAnswerIsUsed() {
		$this->server('old', array('ka_lib_test' => 'registered', 'ka_lib_lite' => 'free'));

		$extensions = $this->model()->checkModules('ka_extensions');

		$this->assertCount(1, $this->requests(), 'the old answer to the same request, no second request');
		$this->assertSame(1, $extensions['ka_lib_test']['is_registered']);
		$this->assertSame(1, $extensions['ka_lib_lite']['is_free']);
	}

	public function testErrorFallsBackToTheOldRequest() {
		$model = $this->model();
		$model->addPermission($this->getUserGroupId(), 'access', static::EXT_ROUTE);
		$model->addPermission($this->getUserGroupId(), 'modify', static::EXT_ROUTE);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
		$this->server('error', array('ka_lib_test' => 'registered'));

		$this->assertNull($model->checkModules('ka_extensions'));
		$this->oc->request(static::EXT_ROUTE);

		$requests = $this->requests();
		$this->assertCount(3, $requests);
		$this->assertArrayHasKey('api_version', $requests[1]['post']);
		$this->assertArrayNotHasKey('api_version', $requests[2]['post'], 'the old request');
		$this->assertSame(array('url', 'protocol_version', 'opencart_version', 'vendor', 'extensions'), array_keys($requests[2]['post']));
		$this->assertTrue($this->oc->get('load')->kamodel('extension/ka_extensions')->isRegistered('ka_lib_test', 'ka_extensions'));
	}

	public function testListPageSavesStatesFromTheNewServer() {
		$model = $this->model();
		$model->addPermission($this->getUserGroupId(), 'access', static::EXT_ROUTE);
		$model->addPermission($this->getUserGroupId(), 'modify', static::EXT_ROUTE);
		$this->oc->loginUser($this->oc->get('session')->data['user_id']);
		$this->server('new', array('ka_lib_test' => 'registered', 'ka_lib_lite' => 'expired'));

		$this->oc->request(static::EXT_ROUTE);

		$this->assertCount(1, $this->requests());
		$model = $this->oc->get('load')->kamodel('extension/ka_extensions');
		$this->assertTrue($model->isRegistered('ka_lib_test', 'ka_extensions'));
		$this->assertFalse($model->isRegistered('ka_lib_lite', 'ka_extensions'));
	}

	public function testRegisterKeyOnTheNewServer() {
		$this->server('new');
		$model = $this->model();

		$this->assertFalse($model->registerKey('WRONG', 'ka_lib_test'));
		$this->assertSame('Invalid license key', $model->getLastError());

		$this->assertTrue($model->registerKey('GOOD-KEY', 'ka_lib_test'));
		$post = $this->requests()[1]['post'];
		$this->assertSame(array('key' => 'GOOD-KEY', 'url' => HTTP_CATALOG, 'extension' => 'ka_lib_test', 'api_version' => '1',
			'vendor' => 'ka_extensions', 'code' => 'ka_lib_test', 'edition' => 'oc3', 'opencart_version' => VERSION), $post);
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
		$this->assertTrue($model->isRegistered('ka_lib_test', 'ka_extensions'));
	}
}
