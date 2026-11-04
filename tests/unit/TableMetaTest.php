<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\ADBTable;
use extension\ka_extensions\ModelRecordset;

class TestMetaTable extends ADBTable {
	const TABLE_NAME = 'ka_test_meta';

	public function getFields() {
		return array(
			'meta_id'    => array('primary_key' => true),
			'name'       => array('required' => true, 'list' => true, 'sort' => true, 'filter' => 'like', 'link' => 'edit'),
			'status'     => array('type' => 'checkbox', 'list' => true, 'filter' => 'select', 'format' => 'enabled'),
			'note'       => array('type' => 'textarea'),
			'date_added' => array('form' => false, 'list' => true, 'sort' => true, 'filter' => 'date_range'),
		);
	}
}

class TestOptionsTable extends ADBTable {
	const TABLE_NAME = 'ka_test_options';
	public $calls = 0;

	public function getFields() {
		return array(
			'option_id' => array('primary_key' => true),
			'kind'      => array('type' => 'select', 'options' => array(1 => 'One', 2 => 'Two'), 'list' => true),
			'store_id'  => array('type' => 'select', 'options' => 'getTestOptions', 'list' => true, 'filter' => 'select'),
			'color'     => array('type' => 'select', 'options' => function() { return array('r' => 'Red'); }),
			'name'      => array('list' => true),
			'bad'       => array('type' => 'select', 'options' => 'noSuchMethod'),
		);
	}

	public function getTestOptions() {
		$this->calls++;
		return array(0 => 'Default', 5 => 'Second');
	}
}

class TestMetaRecordset extends ModelRecordset {
	protected $table_class = TestMetaTable::class;
}

/**
 * Field metadata of ADBTable for the default list and form pages and the filters / sort whitelist of
 * ModelRecordset built from it. Tables without the metadata keep the old behaviour.
 *
 * @group orm
 * @group lists
 */
class TableMetaTest extends UnitTestCase {

	protected function recordsSql($rs, $data) {
		$rs->getRecords($data);
		return $this->normalizeSql($this->lastQuery());
	}

	public function testDefaultsWithoutMetadata() {
		$tbl = new TestTable();
		$this->assertSame(array('name', 'status'), array_keys($tbl->getListFields()));
		$this->assertSame(array('name', 'status'), $tbl->getSortFields());
		$this->assertSame(array(), $tbl->getFilterFields());
		$this->assertSame(array('name' => array('code' => 'name', 'type' => 'text'), 'status' => array('code' => 'status', 'type' => 'text')), $tbl->getFormFields());
		$this->assertFalse($tbl->hasFieldMeta('sort'));
		// a table without getFields()
		$this->assertSame(array(), (new TestFreeTable())->getListFields());
	}

	public function testMetadata() {
		$tbl = new TestMetaTable();
		$this->assertSame(array('name', 'status', 'date_added'), array_keys($tbl->getListFields()));
		$this->assertSame(array('name', 'date_added'), $tbl->getSortFields());
		$this->assertSame(array('name', 'status', 'date_added'), array_keys($tbl->getFilterFields()));
		$form = $tbl->getFormFields();
		$this->assertSame(array('name', 'status', 'note'), array_keys($form));
		$this->assertSame(array('required' => true, 'code' => 'name', 'type' => 'text'), $form['name']);
		$this->assertSame('textarea', $form['note']['type']);
	}

	public function testOldRecordsetIsNotFiltered() {
		$rs = new TestRecordset($this->registry);
		$rs->onLoadExt();
		// no filter metadata: filter_* values are not applied, no whitelist: a plain column is sorted as before
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t ORDER BY status", $this->recordsSql($rs, array('filter_name' => 'x', 'sort' => 'status')));
	}

	public function testMetaFiltersAndWhitelist() {
		$rs = new TestMetaRecordset($this->registry);
		$rs->onLoadExt();
		$this->assertSame('meta_id', $rs->getPrimaryField());

		$sql = $this->recordsSql($rs, array(
			'filter_name'            => "a_%'",
			'filter_status'          => '0',
			'filter_date_added_from' => '2026-01-02',
			'filter_date_added_to'   => 'wrong',
			'filter_note'            => 'ignored',
			'sort'                   => 'name',
			'order'                  => 'DESC',
		));
		$this->assertStringContainsString("t.`name` LIKE '%a\\\\_\\\\%\\'%'", $sql);
		$this->assertStringContainsString("t.`status` = '0'", $sql);
		$this->assertStringContainsString("t.`date_added` >= '2026-01-02 00:00:00'", $sql);
		$this->assertStringNotContainsString('<=', $sql);
		$this->assertStringNotContainsString('note', $sql);
		$this->assertStringEndsWith('ORDER BY t.name DESC', $sql);

		// empty values are skipped, a field without 'sort' is not sorted
		$this->assertSame("SELECT t.* FROM `oc_ka_test_meta` t", $this->recordsSql($rs, array('filter_name' => '', 'filter_status' => array(1), 'sort' => 'status')));
	}

	/**
		'options' of the metadata: an array, a method of the table, a closure; resolved once per object.
	*/
	public function testOptions() {
		$tbl = new TestOptionsTable();
		$this->assertSame(array(1 => 'One', 2 => 'Two'), $tbl->getOptions('kind'));
		$this->assertSame(array(0 => 'Default', 5 => 'Second'), $tbl->getOptions('store_id'));
		$tbl->getOptions('store_id');
		$this->assertSame(1, $tbl->calls);
		$this->assertSame(array('r' => 'Red'), $tbl->getOptions('color'));
		$this->assertSame(array(), $tbl->getOptions('name'));
		$this->assertSame(array(), $tbl->getOptions('bad'));
		$form = $tbl->getFormFields();
		$this->assertSame(array(0 => 'Default', 5 => 'Second'), $form['store_id']['options']);
		$this->assertSame(array('r' => 'Red'), $form['color']['options']);
		$this->assertArrayNotHasKey('options', $form['name']);
	}
}
