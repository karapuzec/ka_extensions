<?php
/*
	Test module of the library tests (tests/fixtures/store, copied into the test store by tests/run.sh).
	Settings page and installer built on ControllerSettings.
*/

namespace extension\ka_extensions;

class ControllerKaLibTest extends ControllerSettings {

	protected $extension_version = '1.0.0.0';
	protected $min_ka_extensions_version = '4.1.1.0';
	protected $max_ka_extensions_version = '4.1.1.99';
	protected $vendor   = 'ka_extensions';
	protected $ext_code = 'ka_lib_test';

	protected function onLoad() {

		$this->tables = array(
			'ka_lib_test_item' => array(
				'query' => "CREATE TABLE `" . DB_PREFIX . "ka_lib_test_item` (
						`item_id` int(11) NOT NULL AUTO_INCREMENT,
						`name` varchar(255) NOT NULL,
						`status` tinyint(1) NOT NULL DEFAULT '1',
						`date_added` datetime NOT NULL,
						`note` varchar(64) NOT NULL DEFAULT '',
						PRIMARY KEY (`item_id`),
						KEY `name` (`name`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
				'fields' => array(
					'item_id'    => array('type' => 'int(11)'),
					'name'       => array('type' => 'varchar(255)'),
					'status'     => array('type' => 'tinyint(1)'),
					'date_added' => array('type' => 'datetime'),
					// added by an upgrade: the field and the index are created for an existing table
					// (a new table is created by 'query' only, it must have all fields and indexes)
					'note'       => array(
						'type'  => 'varchar(64)',
						'query' => "ALTER TABLE `" . DB_PREFIX . "ka_lib_test_item` ADD `note` varchar(64) NOT NULL DEFAULT ''",
					),
				),
				'indexes' => array(
					'name' => array(
						'query' => "ALTER TABLE `" . DB_PREFIX . "ka_lib_test_item` ADD INDEX `name` (`name`)",
					),
				),
			),
		);

		parent::onLoad();
	}

	protected function getFields() {

		$fields = array(
			'ka_lib_test_title' => array(
				'code'          => 'ka_lib_test_title',
				'type'          => 'text',
				'required'      => true,
				'title'         => 'Title',
				'default_value' => 'Default title',
			),
			'ka_lib_test_enabled' => array(
				'code'          => 'ka_lib_test_enabled',
				'type'          => 'checkbox',
				'default_value' => 1,
			),
			'ka_lib_test_groups' => array(
				'code'          => 'ka_lib_test_groups',
				'type'          => 'checkboxes',
				'options'       => array(1 => 'One', 2 => 'Two', 3 => 'Three'),
				'default_value' => array(1, 2),
			),
			'ka_lib_test_mode' => array(
				'code'          => 'ka_lib_test_mode',
				'type'          => 'select',
				'options'       => array('a' => 'Mode A', 'b' => 'Mode B'),
				'default_value' => 'a',
			),
		);

		return $fields;
	}

	protected function getExtensionPages() {
		return array('items', 'item');
	}

	/*
		Upgrade of the module: the default upgrade (install()) and a migration of older versions.
		The tests make it fail with the hidden value 'fail_upgrade'.
	*/
	public function upgradeExtension($from_version, $to_version) {

		if ($this->model_setting_setting->getConfigHidden($this->getSettingCode(), 'fail_upgrade')) {
			$this->addTopMessage('The test upgrade failed.', 'E');
			return false;
		}

		if (!parent::upgradeExtension($from_version, $to_version)) {
			return false;
		}

		if (version_compare($from_version, '1.0.0.0', '<')) {
			$this->model_setting_setting->setConfigHidden($this->getSettingCode(), 'migrated_from', $from_version);
		}

		return true;
	}
}

class_alias(__NAMESPACE__ . '\ControllerKaLibTest', '\ControllerExtensionKaExtensionsKaLibTest');
