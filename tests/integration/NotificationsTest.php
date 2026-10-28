<?php

namespace KaExtensionsTests\Integration;

use extension\ka_extensions\Notifications;

/**
 * Notifications on MySQL: the table, add / addForAll / remove / get for administrators, the lifetime,
 * the bell in the admin header (access to Ka Extensions only, escaped text, no table = no bell) and
 * the cross (POST, modify permission of Ka Extensions).
 *
 * @group notifications
 */
class NotificationsTest extends LibraryTestCase {

	const KA_EXTENSIONS_ROUTE = 'extension/extension/ka_extensions';
	const TABLE = 'ka_notification';

	protected $saved_permissions;
	protected $user_ids = array();

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		// the table is made by the Ka Extensions page
		$this->oc->request(static::KA_EXTENSIONS_ROUTE);
		$this->db()->query("DELETE FROM `" . DB_PREFIX . static::TABLE . "`");
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		if ($this->tableExists(static::TABLE)) {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . static::TABLE . "`");
		} else {
			$this->oc->request(static::KA_EXTENSIONS_ROUTE);
		}
		foreach ($this->user_ids as $user_id) {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . "user` WHERE user_id = '" . (int)$user_id . "'");
		}
	}

	protected function userId() {
		return (int)$this->oc->get('session')->data['user_id'];
	}

	protected function rows() {
		return $this->queryRows("SELECT user_id, code, text, date_expires FROM `" . DB_PREFIX . static::TABLE . "` ORDER BY notification_id");
	}

	protected function loginOtherAdmin() {
		$this->user_ids[] = $user_id = $this->fixtures()->createUser();
		$this->oc->loginUser($user_id);
		return $user_id;
	}

	protected function header() {
		return $this->oc->request('common/header')['output'];
	}

	protected function removePermission($type, $route) {
		$group_id = $this->getUserGroupId();
		$permissions = $this->getPermissions($group_id);
		$permissions[$type] = array_values(array_diff($permissions[$type], array($route)));
		$this->setPermissions($group_id, $permissions);
		$this->oc->loginUser($this->userId());
	}

	public function testTableIsMadeByTheKaExtensionsPage() {
		$this->db()->query("DROP TABLE `" . DB_PREFIX . static::TABLE . "`");
		$this->assertFalse($this->tableExists(static::TABLE));
		$this->oc->request(static::KA_EXTENSIONS_ROUTE);
		$this->assertSame(array('notification_id', 'user_id', 'code', 'text', 'date_added', 'date_expires'), $this->columns(static::TABLE));
		// the next visit changes nothing
		$this->oc->request(static::KA_EXTENSIONS_ROUTE);
		$this->assertTrue($this->tableExists(static::TABLE));
	}

	public function testAddAndAddForAll() {
		$user_id = $this->userId();

		Notifications::add('lib_test_mine', 'Mine');
		Notifications::addForAll('lib_test_all', 'For all', 3600);
		// the same code and user: the row is updated
		Notifications::add('lib_test_mine', 'Mine again');

		$rows = $this->rows();
		$this->assertCount(2, $rows);
		$this->assertSame(array((string)$user_id, 'lib_test_mine', 'Mine again', null), array_values($rows[0]));
		$this->assertSame(array('0', 'lib_test_all', 'For all'), array_slice(array_values($rows[1]), 0, 3));
		$this->assertNotNull($rows[1]['date_expires']);

		$this->assertSame('Mine again', Notifications::get('lib_test_mine'));
		$this->assertSame('For all', Notifications::get('lib_test_all'));
		$this->assertNull(Notifications::get('lib_test_missing'));
	}

	public function testOwnNotificationIsFirstAndOthersAreNotSeen() {
		Notifications::addForAll('lib_test', 'For all');
		Notifications::add('lib_test', 'Mine');
		$this->assertSame('Mine', Notifications::get('lib_test'));

		$this->loginOtherAdmin();
		$this->assertSame('For all', Notifications::get('lib_test'));
		Notifications::add('lib_test_other', 'Other');

		// remove() of the other administrator: his notification and the one for all
		Notifications::remove('lib_test');
		$this->assertNull(Notifications::get('lib_test'));
		$this->assertSame(array('Mine', 'Other'), array_column($this->rows(), 'text'));
	}

	public function testAddWithoutAdministratorIsForAll() {
		// not logged in (e.g. a cron script of the admin area); the catalog has no user object at all
		$this->oc->get('session')->data['user_id'] = 0;
		$this->oc->loginUser(0);
		Notifications::add('lib_test', 'From a script');
		$this->assertSame(array('0'), array_column($this->rows(), 'user_id'));
	}

	public function testExpiredNotificationsAreHiddenAndCleaned() {
		Notifications::add('lib_test_old', 'Old', 60);
		Notifications::add('lib_test_new', 'New', 0);
		$this->db()->query("UPDATE `" . DB_PREFIX . static::TABLE . "` SET date_expires = NOW() - INTERVAL 1 MINUTE WHERE code = 'lib_test_old'");

		$this->assertNull(Notifications::get('lib_test_old'));
		$this->assertSame(array('New'), array_column(Notifications::getList(), 'text'));
		$this->assertCount(2, $this->rows());

		// the next write deletes the expired row
		Notifications::addForAll('lib_test_all', 'All');
		$this->assertSame(array('New', 'All'), array_column($this->rows(), 'text'));
	}

	public function testBellInTheHeader() {
		Notifications::add('lib_test_mine', 'Mine <b>bold</b> & "quoted"');
		Notifications::addForAll('lib_test_all', 'For all');

		$output = $this->header();
		$this->assertStringContainsString('id="ka-notifications"', $output);
		$this->assertStringContainsString('<span class="label label-danger ka-notifications-total">2</span>', $output);
		// the text is escaped
		$this->assertStringContainsString('Mine &lt;b&gt;bold&lt;/b&gt; &amp; &quot;quoted&quot;', $output);
		$this->assertStringNotContainsString('<b>bold</b>', $output);
		$this->assertStringContainsString('route=extension/extension/ka_extensions/deleteNotification', $output);
		// next to the Kamod Health Status icon
		$this->assertStringContainsString('Kamod Health Status', $output);
	}

	public function testNoNotificationsNoBell() {
		$this->assertStringNotContainsString('id="ka-notifications"', $this->header());
	}

	public function testNoBellWithoutAccessToKaExtensions() {
		Notifications::addForAll('lib_test_all', 'For all');
		$this->removePermission('access', static::KA_EXTENSIONS_ROUTE);
		$this->assertStringNotContainsString('id="ka-notifications"', $this->header());
	}

	public function testHeaderWorksWithoutTheTable() {
		$this->db()->query("DROP TABLE `" . DB_PREFIX . static::TABLE . "`");
		// the class checks the table once per request, the page of setUp() has checked it
		$property = new \ReflectionProperty(Notifications::class, 'table_exists');
		$property->setAccessible(true);
		$property->setValue(null, null);

		$output = $this->header();
		$this->assertStringContainsString('Kamod Health Status', $output);
		$this->assertStringNotContainsString('id="ka-notifications"', $output);
		$this->assertNull(Notifications::get('lib_test'));
	}

	protected function deleteRequest($id) {
		return $this->oc->request(static::KA_EXTENSIONS_ROUTE . '/deleteNotification', array(), $id === null ? array() : array('notification_id' => $id));
	}

	protected function notificationId($code) {
		return $this->queryRow("SELECT notification_id FROM `" . DB_PREFIX . static::TABLE . "` WHERE code = '" . $this->esc($code) . "'")['notification_id'];
	}

	public function testCrossDeletesByPost() {
		Notifications::addForAll('lib_test_all', 'For all');
		Notifications::add('lib_test_mine', 'Mine');

		$result = $this->deleteRequest($this->notificationId('lib_test_all'));
		$this->assertSame(array('success' => true), $result['json']);
		$this->assertSame(array('lib_test_mine'), array_column($this->rows(), 'code'));
	}

	public function testCrossNeedsPost() {
		Notifications::addForAll('lib_test_all', 'For all');
		$id = $this->notificationId('lib_test_all');

		$result = $this->oc->request(static::KA_EXTENSIONS_ROUTE . '/deleteNotification', array('notification_id' => $id));
		$this->assertArrayHasKey('error', $result['json']);
		$this->assertCount(1, $this->rows());
	}

	public function testCrossNeedsTheModifyPermission() {
		Notifications::addForAll('lib_test_all', 'For all');
		$this->removePermission('modify', static::KA_EXTENSIONS_ROUTE);

		$result = $this->deleteRequest($this->notificationId('lib_test_all'));
		$this->assertArrayHasKey('error', $result['json']);
		$this->assertCount(1, $this->rows());
	}

	public function testCrossDoesNotDeleteANotificationOfAnotherAdministrator() {
		Notifications::add('lib_test_mine', 'Mine');
		$id = $this->notificationId('lib_test_mine');

		$this->loginOtherAdmin();
		$result = $this->deleteRequest($id);
		$this->assertArrayHasKey('error', $result['json']);
		$this->assertCount(1, $this->rows());
	}
}
