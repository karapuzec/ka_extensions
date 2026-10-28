<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	Notifications for administrators: the bell in the admin header (Ka Extensions adds it to the header
	of OpenCart 3). A module adds a notification with a code and removes it when it is not actual
	anymore; an administrator closes it with the cross, that deletes it.

	The bell shows the notifications of the logged in administrator and the notifications for all
	administrators (user_id = 0). It is shown only to administrators with access to the
	Extensions > Ka Extensions page. The text is a ready string in the admin language made by the
	module, it is escaped on output (no HTML, no links).

	The methods work in the admin area and in the catalog. In the catalog (and without a logged in
	administrator) add() stores a notification for all administrators.

	Example:
```php
	use \extension\ka_extensions\Notifications;

	// for all administrators, until somebody closes it or for one day
	Notifications::addForAll('mymodule_daily_limit', 'My Module: the daily limit is reached.', 86400);

	if (Notifications::get('mymodule_daily_limit') !== null) {
		// shown now
	}

	Notifications::remove('mymodule_daily_limit');
```
	The notifications are stored in the table ka_notification (created on the Ka Extensions page or
	on the first write).

	@package Service
*/
class Notifications {

	const TABLE = 'ka_notification';

	/**
		@internal
		null = not checked yet in this request
	*/
	protected static $table_exists = null;


	/**
		Adds a notification for the logged in administrator, it is updated when the administrator
		has a notification with this code. Without a logged in administrator (e.g. in the catalog)
		the notification is for all administrators, as addForAll() does.

		$ttl - lifetime in seconds, 0 = until an administrator closes it.
	*/
	public static function add($code, $text, $ttl = 0) {
		return static::addForUser(static::getUserId(), $code, $text, $ttl);
	}


	/**
		Adds a notification for all administrators (user_id = 0) or updates it. There is one
		notification for everybody: when one administrator closes it, it is deleted for all.

		$ttl - lifetime in seconds, 0 = until an administrator closes it.
	*/
	public static function addForAll($code, $text, $ttl = 0) {
		return static::addForUser(0, $code, $text, $ttl);
	}


	/**
		Removes the notification with this code of the logged in administrator and the one for all
		administrators.
	*/
	public static function remove($code) {

		if (!static::tableExists()) {
			return;
		}

		$db = static::db();
		$db->query("DELETE FROM `" . DB_PREFIX . static::TABLE . "` WHERE `code` = '" . $db->escape($code) . "'
			AND `user_id` IN (" . static::getUserIds() . ")"
		);
	}


	/**
		Returns the text of the notification with this code: the one of the logged in administrator
		first, then the one for all administrators. Null when there is none or it has expired.
	*/
	public static function get($code) {

		if (!static::tableExists()) {
			return null;
		}

		$db = static::db();
		$qry = $db->query("SELECT `text` FROM `" . DB_PREFIX . static::TABLE . "` WHERE `code` = '" . $db->escape($code) . "'
			AND `user_id` IN (" . static::getUserIds() . ") AND " . static::getNotExpired() . "
			ORDER BY `user_id` DESC LIMIT 1"
		);

		if (empty($qry->row)) {
			return null;
		}

		return $qry->row['text'];
	}


	/**
		@internal
		The notifications shown to the logged in administrator, newest first:
		[['notification_id', 'code', 'text', 'date_added', 'user_id'], ...]. The header calls it;
		no table, no notifications.
	*/
	public static function getList() {

		if (!static::tableExists()) {
			return [];
		}

		$qry = static::db()->query("SELECT `notification_id`, `user_id`, `code`, `text`, `date_added`
			FROM `" . DB_PREFIX . static::TABLE . "` WHERE `user_id` IN (" . static::getUserIds() . ")
			AND " . static::getNotExpired() . " ORDER BY `date_added` DESC, `notification_id` DESC"
		);

		return $qry->rows;
	}


	/**
		@internal
		Deletes a notification closed by the logged in administrator (the cross in the header): his own
		one or one for all administrators. Returns true when it was deleted.
	*/
	public static function deleteById($notification_id) {

		if (!static::tableExists()) {
			return false;
		}

		$db = static::db();
		$db->query("DELETE FROM `" . DB_PREFIX . static::TABLE . "` WHERE `notification_id` = '" . (int)$notification_id . "'
			AND `user_id` IN (" . static::getUserIds() . ")"
		);

		return $db->countAffected() > 0;
	}


	/**
		@internal
		The table description for the patchDB() of the Ka Extensions page.
	*/
	public static function getTableInfo() {
		return [
			'query' => static::getCreateTableQuery(),
			'fields' => [
				'notification_id' => ['type' => 'int(11)'],
				'user_id'         => ['type' => 'int(11)'],
				'code'            => ['type' => 'varchar(128)'],
				'text'            => ['type' => 'text'],
				'date_added'      => ['type' => 'datetime'],
				'date_expires'    => ['type' => 'datetime'],
			],
		];
	}


	/**
		@internal
	*/
	protected static function getCreateTableQuery() {
		return "CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . static::TABLE . "` (
			`notification_id` int(11) NOT NULL AUTO_INCREMENT,
			`user_id` int(11) NOT NULL DEFAULT '0',
			`code` varchar(128) NOT NULL,
			`text` text NOT NULL,
			`date_added` datetime NOT NULL,
			`date_expires` datetime DEFAULT NULL,
			PRIMARY KEY (`notification_id`),
			UNIQUE KEY `code_user` (`code`, `user_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";
	}


	/**
		@internal
	*/
	protected static function addForUser($user_id, $code, $text, $ttl) {

		$db = static::db();

		if (!static::tableExists()) {
			$db->query(static::getCreateTableQuery());
			static::$table_exists = true;
		}

		// expired notifications are deleted on writes
		$db->query("DELETE FROM `" . DB_PREFIX . static::TABLE . "` WHERE `date_expires` IS NOT NULL AND `date_expires` <= NOW()");

		$ttl = (int)$ttl;
		$date_expires = ($ttl > 0) ? "DATE_ADD(NOW(), INTERVAL " . $ttl . " SECOND)" : "NULL";

		$values = "`text` = '" . $db->escape($text) . "', `date_added` = NOW(), `date_expires` = " . $date_expires;

		// one notification per code and user (the unique key code_user)
		$db->query("INSERT INTO `" . DB_PREFIX . static::TABLE . "` SET
			`user_id` = '" . (int)$user_id . "', `code` = '" . $db->escape($code) . "', " . $values . "
			ON DUPLICATE KEY UPDATE " . $values
		);

		return true;
	}


	/**
		@internal
		The id of the logged in administrator, 0 in the catalog or without a login.
	*/
	protected static function getUserId() {

		$user = KaGlobal::getRegistry()->get('user');
		if (is_object($user) && method_exists($user, 'getId')) {
			return (int)$user->getId();
		}

		return 0;
	}


	/**
		@internal
	*/
	protected static function getUserIds() {
		$user_id = static::getUserId();
		return $user_id ? "'" . $user_id . "', '0'" : "'0'";
	}


	/**
		@internal
	*/
	protected static function getNotExpired() {
		return "(`date_expires` IS NULL OR `date_expires` > NOW())";
	}


	/**
		@internal
	*/
	protected static function db() {
		return KaGlobal::getRegistry()->get('db');
	}


	/**
		@internal
	*/
	protected static function tableExists() {

		if (is_null(static::$table_exists)) {
			$qry = static::db()->query("SHOW TABLES LIKE '" . static::db()->escape(DB_PREFIX . static::TABLE) . "'");
			static::$table_exists = !empty($qry->num_rows);
		}

		return static::$table_exists;
	}
}
