<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\HiddenSettings;

/**
 * HiddenSettings: hidden values of a module in the group '<ext_code>_hidden' (admin and catalog),
 * one key per write, atomic increase(). The setting table is emulated on the rows of the stub
 * ModelSettingSetting (FakeSettingTable).
 *
 * @group upgrade
 * @group settings
 */
class HiddenSettingsTest extends UnitTestCase {

	/** @var \ModelSettingSetting */
	protected $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new \ModelSettingSetting($this->registry);
		$this->adaptor->setting_table = new \FakeSettingTable($this->settings);
	}

	protected function rows($code) {
		$rows = array();
		foreach ($this->settings->rows as $row) {
			if ($row['code'] == $code) {
				$rows[$row['key']] = array($row['value'], $row['serialized']);
			}
		}
		return $rows;
	}

	public function testMissingValueGivesTheDefault() {
		$this->assertNull(HiddenSettings::get('ka_hs', 'requests'));
		$this->assertSame(5, HiddenSettings::get('ka_hs', 'requests', 5));
	}

	public function testSetStoresOneKeyInTheHiddenGroup() {
		HiddenSettings::set('ka_hs', 'version', '1.0.0.0');
		$this->assertSame(array('ka_hs_hidden_version' => array('1.0.0.0', 0)), $this->rows('ka_hs_hidden'));
		$this->assertSame('1.0.0.0', HiddenSettings::get('ka_hs', 'version'));
		// the native OpenCart method reads it as well
		$this->assertSame(array('ka_hs_hidden_version' => '1.0.0.0'), $this->settings->getSetting('ka_hs_hidden'));
	}

	public function testSetChangesOnlyItsKey() {
		$this->settings->editSetting('ka_hs_hidden', array('ka_hs_hidden_a' => '1', 'ka_hs_hidden_b' => '2'));
		$this->settings->editSetting('ka_hs', array('ka_hs_title' => 'Title'));
		$this->adaptor->queries = array();

		HiddenSettings::set('ka_hs', 'a', 10);

		$this->assertSame(array('ka_hs_hidden_a' => array('10', 0), 'ka_hs_hidden_b' => array('2', 0)), $this->rows('ka_hs_hidden'));
		$this->assertSame(array('ka_hs_title' => array('Title', 0)), $this->rows('ka_hs'));
		// one row is updated, the group is not deleted and written again
		foreach ($this->adaptor->queries as $sql) {
			$this->assertStringNotContainsString("DELETE", $sql);
		}
	}

	public function testArraysAreJson() {
		HiddenSettings::set('ka_hs', 'list', array('x' => 1, 'y' => array(2, 3)));
		$this->assertSame(array('ka_hs_hidden_list' => array('{"x":1,"y":[2,3]}', 1)), $this->rows('ka_hs_hidden'));
		$this->assertSame(array('x' => 1, 'y' => array(2, 3)), HiddenSettings::get('ka_hs', 'list'));

		// a scalar value replaces the array
		HiddenSettings::set('ka_hs', 'list', 7);
		$this->assertSame(array('ka_hs_hidden_list' => array('7', 0)), $this->rows('ka_hs_hidden'));
	}

	public function testNullRemovesTheKeyOnly() {
		HiddenSettings::set('ka_hs', 'a', '1');
		HiddenSettings::set('ka_hs', 'b', '2');
		HiddenSettings::set('ka_hs', 'a', null);
		$this->assertSame(array('ka_hs_hidden_b' => array('2', 0)), $this->rows('ka_hs_hidden'));
		$this->assertNull(HiddenSettings::get('ka_hs', 'a'));
	}

	public function testIncreaseCreatesAndAdds() {
		$this->assertSame('1', HiddenSettings::increase('ka_hs', 'requests'));
		$this->assertSame('6', HiddenSettings::increase('ka_hs', 'requests', 5));
		$this->assertSame('4', HiddenSettings::increase('ka_hs', 'requests', -2));
		$this->assertSame(array('ka_hs_hidden_requests' => array('4', 0)), $this->rows('ka_hs_hidden'));
	}

	public function testIncreaseIsOneUpdate() {
		HiddenSettings::set('ka_hs', 'requests', '3');
		$this->adaptor->queries = array();

		HiddenSettings::increase('ka_hs', 'requests', 2);

		// the value is changed by the database (value = value + 2), not read and written by PHP
		$this->assertSame(
			"UPDATE `oc_setting` SET `value` = `value` + 2 WHERE `store_id` = '0' AND `code` = 'ka_hs_hidden' AND `key` = 'ka_hs_hidden_requests'",
			$this->adaptor->queries[0]
		);
		$this->assertCount(1, preg_grep('/^(UPDATE|INSERT|DELETE)/', $this->adaptor->queries));
	}

	public function testIncreaseOfNothingChangesNothing() {
		HiddenSettings::set('ka_hs', 'requests', '3');
		$this->assertSame('3', HiddenSettings::increase('ka_hs', 'requests', 0));
		$this->assertSame('3', HiddenSettings::increase('ka_hs', 'requests', 'abc'));
	}

	public function testValuesAreEscaped() {
		HiddenSettings::set('ka_hs', "k'ey", "O'Reilly");
		$this->assertStringContainsString("`key` = 'ka_hs_hidden_k\\'ey'", $this->lastQuery());
		$this->assertSame("O'Reilly", HiddenSettings::get('ka_hs', "k'ey"));
	}

	/**
	 * A counter and the version of one module written in turns, the settings page saved in between:
	 * no write loses another value (setConfigHidden() rewrote the whole group before 4.1.1.29).
	 */
	public function testInterleavedWritesKeepAllValues() {
		HiddenSettings::set('ka_hs', 'version', '1.0.0.0');
		HiddenSettings::increase('ka_hs', 'requests');
		$this->settings->editSetting('ka_hs', array('ka_hs_title' => 'Saved'));
		HiddenSettings::increase('ka_hs', 'requests');
		HiddenSettings::set('ka_hs', 'version', '1.0.1.0');
		HiddenSettings::increase('ka_hs', 'requests');

		$this->assertSame(array(
			'ka_hs_hidden_version'  => array('1.0.1.0', 0),
			'ka_hs_hidden_requests' => array('3', 0),
		), $this->rows('ka_hs_hidden'));
		$this->assertSame(array('ka_hs_title' => array('Saved', 0)), $this->rows('ka_hs'));
	}
}
