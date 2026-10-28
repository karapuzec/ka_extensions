<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\Notifications;

class NotificationsTestUser {
	public $user_id;

	public function __construct($user_id) {
		$this->user_id = $user_id;
	}

	public function getId() {
		return $this->user_id;
	}
}

/**
 * Notifications: SQL of add / addForAll / remove / get for the logged in administrator, for all
 * administrators and without an administrator (catalog); no table, no errors.
 *
 * @group notifications
 */
class NotificationsTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setTableExists(null);
	}

	protected function setTableExists($value) {
		$property = new \ReflectionProperty(Notifications::class, 'table_exists');
		$property->setAccessible(true);
		$property->setValue(null, $value);
	}

	protected function login($user_id) {
		$this->registry->set('user', new NotificationsTestUser($user_id));
	}

	protected function writes() {
		return array_values(array_map(array($this, 'normalizeSql'), preg_grep('/^\s*(INSERT|DELETE|CREATE)/', $this->queries())));
	}

	public function testAddIsForTheLoggedInAdministrator() {
		$this->setTableExists(true);
		$this->login(7);
		Notifications::add('mod_code', "Limit's reached", 0);

		$writes = $this->writes();
		$this->assertSame("DELETE FROM `oc_ka_notification` WHERE `date_expires` IS NOT NULL AND `date_expires` <= NOW()", $writes[0]);
		$this->assertSame(
			"INSERT INTO `oc_ka_notification` SET `user_id` = '7', `code` = 'mod_code', `text` = 'Limit\\'s reached', `date_added` = NOW(), `date_expires` = NULL"
			. " ON DUPLICATE KEY UPDATE `text` = 'Limit\\'s reached', `date_added` = NOW(), `date_expires` = NULL",
			$writes[1]
		);
	}

	public function testAddWithoutAnAdministratorIsForAll() {
		$this->setTableExists(true);

		// catalog: no user object
		Notifications::add('mod_code', 'Text');
		$this->assertStringContainsString("SET `user_id` = '0', `code` = 'mod_code'", $this->normalizeSql($this->lastQuery()));

		// admin area, not logged in
		$this->login(0);
		Notifications::add('mod_code', 'Text');
		$this->assertStringContainsString("SET `user_id` = '0', `code` = 'mod_code'", $this->normalizeSql($this->lastQuery()));
	}

	public function testAddForAllAndTtl() {
		$this->setTableExists(true);
		$this->login(7);
		Notifications::addForAll('mod_code', 'Text', 86400);
		$this->assertStringContainsString("SET `user_id` = '0', `code` = 'mod_code', `text` = 'Text', `date_added` = NOW(), `date_expires` = DATE_ADD(NOW(), INTERVAL 86400 SECOND)", $this->normalizeSql($this->lastQuery()));
	}

	public function testRemoveAndGetUseTheUserAndAll() {
		$this->setTableExists(true);
		$this->login(7);

		Notifications::remove('mod_code');
		$this->assertSame("DELETE FROM `oc_ka_notification` WHERE `code` = 'mod_code' AND `user_id` IN ('7', '0')", $this->normalizeSql($this->lastQuery()));

		$this->adaptor->addResult('SELECT `text`', array(array('text' => 'Mine')));
		$this->assertSame('Mine', Notifications::get('mod_code'));
		$this->assertSame(
			"SELECT `text` FROM `oc_ka_notification` WHERE `code` = 'mod_code' AND `user_id` IN ('7', '0') AND (`date_expires` IS NULL OR `date_expires` > NOW()) ORDER BY `user_id` DESC LIMIT 1",
			$this->normalizeSql($this->lastQuery())
		);
	}

	public function testGetOfNothingIsNull() {
		$this->setTableExists(true);
		$this->assertNull(Notifications::get('mod_code'));
		$this->assertStringContainsString("`user_id` IN ('0')", $this->lastQuery());
	}

	public function testNoTableNoErrors() {
		// SHOW TABLES of the fake adaptor finds nothing
		$this->assertNull(Notifications::get('mod_code'));
		$this->assertSame(array(), Notifications::getList());
		$this->assertFalse(Notifications::deleteById(1));
		Notifications::remove('mod_code');
		$this->assertSame(array(), $this->writes());
	}

	public function testFirstWriteCreatesTheTable() {
		Notifications::addForAll('mod_code', 'Text');
		$writes = $this->writes();
		$this->assertStringStartsWith("CREATE TABLE IF NOT EXISTS `oc_ka_notification`", $writes[0]);
		$this->assertStringContainsString("UNIQUE KEY `code_user` (`code`, `user_id`)", $writes[0]);
	}
}
