<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\ModelRecordset;
use extension\ka_extensions\QB;

class TestRecordset extends ModelRecordset {
	protected $table_class = TestTable::class;
	public $whitelist = null;
	public $group = false;

	protected function getSortWhitelist() {
		return $this->whitelist;
	}

	protected function getRecordsQB($data = array()) {
		$qb = parent::getRecordsQB($data);
		if ($this->group) {
			$qb->groupBy('t.status');
		}
		return $qb;
	}
}

class TestPairRecordset extends ModelRecordset {
	protected $table_class = TestPairTable::class;
}

/**
 * ModelRecordset: records of a list page (sorting, paging, totals) and saving.
 *
 * @group orm
 * @group lists
 */
class ModelRecordsetTest extends UnitTestCase {

	/** @var TestRecordset */
	protected $rs;

	protected function setUp(): void {
		parent::setUp();
		$this->rs = new TestRecordset($this->registry);
		$this->rs->onLoadExt();
	}

	protected function recordsSql($data) {
		$this->rs->getRecords($data);
		return $this->normalizeSql($this->lastQuery());
	}

	public function testPrimaryField() {
		$this->assertSame('test_id', $this->rs->getPrimaryField());
		$this->assertSame(array('test_id'), $this->rs->getPrimaryFields());
	}

	public function testPrimaryFieldOfACompositeKeyThrows() {
		$rs = new TestPairRecordset($this->registry);
		$rs->onLoadExt();
		$this->expectException(\Exception::class);
		$rs->getPrimaryField();
	}

	public function testRecordsWithoutSorting() {
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t", $this->recordsSql(array()));
	}

	public function testPlainColumnIsAcceptedWithoutWhitelist() {
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t ORDER BY t.name DESC", $this->recordsSql(array('sort' => 't.name', 'order' => 'desc')));
	}

	/**
	 * @dataProvider injectedSortProvider
	 */
	public function testSqlInSortIsIgnored($sort) {
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t", $this->recordsSql(array('sort' => $sort, 'order' => 'ASC')));
	}

	public function injectedSortProvider() {
		return array(
			array('t.name; DROP TABLE oc_user'),
			array('(SELECT password FROM oc_user LIMIT 1)'),
			array('name ASC, IF(1,SLEEP(5),0)'),
			array('name`'),
		);
	}

	public function testWrongOrderIsIgnored() {
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t ORDER BY t.name", $this->recordsSql(array('sort' => 't.name', 'order' => 'ASC, SLEEP(5)')));
	}

	public function testWhitelistCode() {
		$this->rs->whitelist = array('email' => 'COALESCE(t.email, t.name)', 'name' => 't.name');
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t ORDER BY COALESCE(t.email, t.name) ASC", $this->recordsSql(array('sort' => 'email', 'order' => 'ASC')));
	}

	public function testWhitelistSqlValue() {
		$this->rs->whitelist = array('name' => 't.name');
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t ORDER BY t.name DESC", $this->recordsSql(array('sort' => 't.name', 'order' => 'DESC')));
	}

	public function testWhitelistRejectsOtherColumns() {
		$this->rs->whitelist = array('name' => 't.name');
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t", $this->recordsSql(array('sort' => 't.status')));
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t", $this->recordsSql(array('sort' => array('t.name'))));
	}

	public function testPaging() {
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t LIMIT 20, 10", $this->recordsSql(array('start' => 20, 'limit' => 10)));
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t LIMIT 0, 10", $this->recordsSql(array('start' => -30, 'limit' => 10)));
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t", $this->recordsSql(array('start' => 20)));
	}

	public function testTotal() {
		$this->adaptor->addResult('COUNT(*) AS total', array(array('total' => '17')));
		$this->assertSame('17', $this->rs->getRecordsTotal(array('sort' => 't.name', 'start' => 10, 'limit' => 5)));
		$this->assertSame("SELECT COUNT(*) AS total FROM `oc_ka_test` t", $this->normalizeSql($this->lastQuery()));
	}

	public function testTotalOfGroupedRecordsCountsGroups() {
		$this->rs->group = true;
		$this->adaptor->addResult('grouped_records', array(array('total' => '3')));
		$this->assertSame('3', $this->rs->getRecordsTotal());
		$this->assertSame(
			"SELECT COUNT(*) AS total FROM (SELECT 1 FROM `oc_ka_test` t GROUP BY t.status) AS grouped_records",
			$this->normalizeSql($this->lastQuery())
		);
	}

	public function testTotalWithoutResultIsZero() {
		$this->assertSame(0, $this->rs->getRecordsTotal());
	}

	public function testGetRecord() {
		$this->adaptor->addResult('FROM `oc_ka_test`', array(array('test_id' => '4', 'name' => 'N')));
		$this->assertSame(array('test_id' => '4', 'name' => 'N'), $this->rs->getRecord("4' OR '1'='1"));
		$this->assertSame("SELECT t.* FROM `oc_ka_test` t WHERE (t.test_id = '4\\' OR \\'1\\'=\\'1')", $this->normalizeSql($this->lastQuery()));
	}

	public function testAmbiguousRecordThrows() {
		$this->adaptor->addResult('FROM `oc_ka_test`', array(array('test_id' => '4'), array('test_id' => '4')));
		$this->expectException(\extension\ka_extensions\ExceptionData::class);
		$this->rs->getRecord(4);
	}

	public function testSaveNewRecordInsertsDeclaredFields() {
		$this->adaptor->last_id = 31;
		$this->assertSame(31, $this->rs->saveRecord(array('name' => 'N', 'user_token' => 'x', 'status' => '1')));
		$this->assertSame("INSERT INTO `oc_ka_test` SET `name`='N', `status`='1'", $this->lastQuery());
	}

	/**
	 * Documents the current behavior: an empty primary key is inserted as '' (MySQL in the OpenCart
	 * non-strict sql_mode gives the next auto increment value, see the integration test).
	 */
	public function testSaveNewRecordWithEmptyPrimaryKey() {
		$this->adaptor->last_id = 32;
		$this->assertSame(32, $this->rs->saveRecord(array('test_id' => '', 'name' => 'N')));
		$this->assertSame("INSERT INTO `oc_ka_test` SET `test_id`='', `name`='N'", $this->lastQuery());
	}

	public function testSaveExistingRecordUpdates() {
		$this->assertSame('8', $this->rs->saveRecord(array('test_id' => '8', 'name' => 'N', 'other' => 'x')));
		$this->assertSame("UPDATE `oc_ka_test` SET `name`='N' WHERE `test_id`='8'", $this->lastQuery());
	}

	public function testSaveCompositeRecord() {
		$rs = new TestPairRecordset($this->registry);
		$rs->onLoadExt();
		$rs->saveRecord(array('a_id' => 1, 'value' => 'v'));
		$this->assertSame("INSERT INTO `oc_ka_test_pair` SET `a_id`='1', `value`='v'", $this->lastQuery());

		$rs->saveRecord(array('a_id' => 1, 'b_id' => 2, 'value' => 'v'));
		$this->assertSame("UPDATE `oc_ka_test_pair` SET `value`='v' WHERE `a_id`='1' AND `b_id`='2'", $this->lastQuery());
	}

	public function testDeleteRecord() {
		$this->rs->deleteRecord('3');
		$this->assertSame("DELETE FROM `oc_ka_test` WHERE `test_id`='3'", $this->lastQuery());
	}

	public function testFillRecordAddsTitle() {
		$this->assertSame(array('test_id' => 1, 'record_title' => ''), $this->rs->fillRecord(array('test_id' => 1)));
	}
}
