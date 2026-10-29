<?php

namespace KaExtensionsTests\Integration;

/**
 * The license column of Extensions > Ka Extensions: Free / Registered / Register.
 *
 * The registration server is not reachable in these tests (KA_STORE_URL points to a closed port), so the
 * list keeps the 'kareg' records the tests write. A Lite module (ka_lib_lite, isLite() true) reads all
 * license data from its own record 'karegka_lib_lite_lite', a full module (ka_lib_test) from 'karegka_lib_test'.
 *
 * @group installer
 * @group license
 */
class LicenseColumnTest extends LibraryTestCase {

	const EXT_ROUTE = 'extension/extension/ka_extensions';

	protected $saved_permissions;

	protected function setUp(): void {
		if (!defined('KA_STORE_URL')) {
			define('KA_STORE_URL', 'http://127.0.0.1:1/');
		}
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
		foreach (array('karegka_lib_lite', 'karegka_lib_lite_lite', 'karegka_lib_test') as $key) {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = '" . $key . "'");
		}
	}

	protected function setKareg($key, $value) {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = '" . $this->esc($key) . "'");
		$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, `code` = 'kareg', `key` = '" . $this->esc($key) . "',
			`value` = '" . $this->esc(json_encode($value)) . "', serialized = 1"
		);
		// the marker of the registration data
		if (!$this->queryRow("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = 'kareg'")) {
			$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, `code` = 'kareg', `key` = 'kareg', `value` = '1', serialized = 0");
		}
	}

	/*
		Returns the table row of the module on the list page.
	*/
	protected function row($title) {
		$output = $this->oc->request(static::EXT_ROUTE)['output'];
		foreach (preg_split('#<tr[ >]#', $output) as $row) {
			if (strpos($row, $title) !== false) {
				return $row;
			}
		}
		$this->fail("No row of $title on the list page");
	}

	protected function licenseText($row) {
		// the third cell: name, version, license
		$cells = preg_split('#<td[^>]*>#', $row);
		return trim(preg_replace('/\s+/', ' ', strip_tags($cells[3])));
	}

	protected function installEnabled($row) {
		return (bool)preg_match('#<a href="[^"]*ka_extensions/install[^"]*"#', html_entity_decode($row));
	}

	protected function serverFree() {
		// a record saved from the server answer for a free product (saveRegAll adds url and is_registered)
		return array('license_key' => '', 'masked_key' => '', 'expiry_date' => '', 'is_free' => 1, 'url' => HTTP_CATALOG, 'is_registered' => 1);
	}

	public function testLiteWithFreeLiteRecordIsFree() {
		$this->setKareg('karegka_lib_lite_lite', $this->serverFree());

		$row = $this->row('Ka Library Lite Test');

		$this->assertSame('Free', $this->licenseText($row));
		$this->assertTrue($this->installEnabled($row));
	}

	public function testLiteIgnoresTheFullVersionRecord() {
		$this->setKareg('karegka_lib_lite_lite', $this->serverFree());
		$this->setKareg('karegka_lib_lite', array('masked_key' => 'ABCD-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'ABCD-XXXX-XXXX-XXXX-XXXX',
			'expiry_date' => '2027-01-01', 'is_registered' => 1, 'is_wrong_license' => true));

		$row = $this->row('Ka Library Lite Test');

		$this->assertSame('Free', $this->licenseText($row));
		$this->assertStringNotContainsString('ABCD', $row);
		$this->assertStringNotContainsString('Exp.date', $row);
		$this->assertStringNotContainsString('Invalid License', $row);
	}

	public function testLiteWithoutLiteRecordIsNotRegistered() {
		// only the record of the full version: the Lite version is not registered
		$this->setKareg('karegka_lib_lite', array('masked_key' => 'ABCD-XXXX-XXXX-XXXX-XXXX', 'expiry_date' => '2027-01-01', 'is_registered' => 1));

		$row = $this->row('Ka Library Lite Test');

		$this->assertSame('Register', $this->licenseText($row));
		$this->assertStringNotContainsString('ABCD', $row);
		$this->assertFalse($this->installEnabled($row));
	}

	public function testLiteWithRegisteredLiteRecord() {
		$this->setKareg('karegka_lib_lite_lite', array('masked_key' => 'EFGH-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'EFGH-XXXX-XXXX-XXXX-XXXX', 'is_registered' => 1));

		$row = $this->row('Ka Library Lite Test');

		$this->assertSame('Registered', $this->licenseText($row));
		$this->assertStringContainsString('EFGH-XXXX-XXXX-XXXX-XXXX', $row);
		$this->assertTrue($this->installEnabled($row));
	}

	public function testFullModuleIsUnchanged() {
		// no record: Register, Install disabled
		$row = $this->row('Ka Library Test');
		$this->assertSame('Register', $this->licenseText($row));
		$this->assertFalse($this->installEnabled($row));

		// a free record of the module code
		$this->setKareg('karegka_lib_test', $this->serverFree());
		$row = $this->row('Ka Library Test');
		$this->assertSame('Free', $this->licenseText($row));
		$this->assertTrue($this->installEnabled($row));

		// a license with a key and an expiry date
		$this->setKareg('karegka_lib_test', array('masked_key' => 'IJKL-XXXX-XXXX-XXXX-XXXX', 'license_key' => 'IJKL-XXXX-XXXX-XXXX-XXXX',
			'expiry_date' => '2027-01-01', 'is_registered' => 1));
		$row = $this->row('Ka Library Test');
		$this->assertStringContainsString('Registered', $this->licenseText($row));
		$this->assertStringContainsString('Exp.date', $row);
		$this->assertStringContainsString('IJKL-XXXX-XXXX-XXXX-XXXX', $row);

		// a '_lite' record does not change a full module
		$this->setKareg('karegka_lib_test', array('is_registered' => 1, 'is_wrong_license' => true));
		$this->setKareg('karegka_lib_test_lite', $this->serverFree());
		$row = $this->row('Ka Library Test');
		$this->assertStringContainsString('Invalid License', $this->licenseText($row));
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'kareg' AND `key` = 'karegka_lib_test_lite'");
	}
}
