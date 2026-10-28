<?php

namespace KaExtensionsTests\Integration;

use extension\ka_extensions\HiddenSettings;

/**
 * HiddenSettings on MySQL: hidden values of a module in the admin area and in the catalog, one key per
 * write, increase() of parallel requests, getConfigHidden() / setConfigHidden() of the admin setting
 * model are wrappers of it.
 *
 * @group upgrade
 * @group settings
 */
class HiddenSettingsTest extends LibraryTestCase {

	protected function tearDown(): void {
		$this->deleteSettings('ka_lib_hs_hidden');
		$this->deleteSettings('ka_lib_hs');
	}

	public function testAdminSetGetAndOtherKeysKept() {
		$this->bootAdmin();
		$this->fixtures()->setSetting('ka_lib_hs_hidden', 'ka_lib_hs_hidden_other', 'O');
		$this->fixtures()->setSetting('ka_lib_hs', 'ka_lib_hs_title', 'Title');

		HiddenSettings::set('ka_lib_hs', 'version', '1.0.0.0');
		HiddenSettings::set('ka_lib_hs', 'list', array('a' => "O'Reilly", 'b' => 'Привет'));
		HiddenSettings::set('ka_lib_hs', 'version', '1.0.1.0');

		$this->assertSame(array(
			'ka_lib_hs_hidden_other'   => 'O',
			'ka_lib_hs_hidden_version' => '1.0.1.0',
			'ka_lib_hs_hidden_list'    => array('a' => "O'Reilly", 'b' => 'Привет'),
		), $this->getSettings('ka_lib_hs_hidden'));
		$this->assertSame(array('ka_lib_hs_title' => 'Title'), $this->getSettings('ka_lib_hs'));
		$this->assertSame(array('a' => "O'Reilly", 'b' => 'Привет'), HiddenSettings::get('ka_lib_hs', 'list'));
		$this->assertSame('x', HiddenSettings::get('ka_lib_hs', 'missing', 'x'));

		// one row per key
		$this->assertSame('1', $this->queryRow("SELECT COUNT(*) AS cnt FROM `" . DB_PREFIX . "setting` WHERE `key` = 'ka_lib_hs_hidden_version'")['cnt']);

		HiddenSettings::set('ka_lib_hs', 'version', null);
		$this->assertSame(array('ka_lib_hs_hidden_other', 'ka_lib_hs_hidden_list'), array_keys($this->getSettings('ka_lib_hs_hidden')));
	}

	public function testAdminModelWrappers() {
		$this->bootAdmin();
		$model = $this->oc->model('setting/setting');

		$model->setConfigHidden('ka_lib_hs', 'version', '2.0.0.0');
		$this->assertSame('2.0.0.0', HiddenSettings::get('ka_lib_hs', 'version'));
		HiddenSettings::increase('ka_lib_hs', 'requests', 3);
		$this->assertSame('3', $model->getConfigHidden('ka_lib_hs', 'requests'));
		$this->assertNull($model->getConfigHidden('ka_lib_hs', 'missing'));
	}

	public function testCatalogIncreaseAndSet() {
		$this->bootCatalog();

		$this->assertSame('1', HiddenSettings::increase('ka_lib_hs', 'requests'));
		$this->assertSame('11', HiddenSettings::increase('ka_lib_hs', 'requests', 10));
		HiddenSettings::set('ka_lib_hs', 'daily_limit_date', '2026-10-28');

		$this->assertSame(array(
			'ka_lib_hs_hidden_requests'         => '11',
			'ka_lib_hs_hidden_daily_limit_date' => '2026-10-28',
		), $this->getSettings('ka_lib_hs_hidden'));
	}

	/**
	 * Four requests at the same time increase a counter that does not exist yet, 50 times each:
	 * no increment is lost and the value has one row.
	 */
	public function testParallelIncreases() {
		$this->bootAdmin();
		$store_dir = \KaTesting\Env::getStoreDir();
		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;

		$processes = array();
		for ($i = 0; $i < 4; $i++) {
			$processes[] = proc_open(
				array($php, __DIR__ . '/tools/hidden_increase.php', $store_dir, 'parallel', '50'),
				array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
				$pipes
			);
			$outputs[] = $pipes;
		}
		foreach ($processes as $i => $process) {
			$output = stream_get_contents($outputs[$i][1]) . stream_get_contents($outputs[$i][2]);
			$this->assertSame(0, proc_close($process), $output);
		}

		$rows = $this->queryRows("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` = 'ka_lib_hs_hidden_parallel'");
		$this->assertSame(array(array('value' => '200')), $rows);
	}
}
