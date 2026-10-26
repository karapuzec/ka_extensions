<?php

namespace KaExtensionsTests\Integration;

use extension\ka_extensions\QB;

/**
 * Ka ORM on MySQL: ADBTable, QB, ModelRecordset and the DB methods with real queries.
 *
 * @group orm
 */
class OrmTest extends LibraryTestCase {

	const TRICKY = "O'Reilly \\ \"quoted\" ; -- `tick` Привет";

	protected function setUp(): void {
		$this->bootAdmin();
		$this->createItemTable();
	}

	protected function tearDown(): void {
		$this->dropItemTable();
	}

	protected function table() {
		$this->oc->get('load')->kamodel(static::ITEMS_ROUTE);
		return new \extension\ka_extensions\ka_lib_test\dbtable\Item();
	}

	protected function recordset() {
		return $this->oc->get('load')->kamodel(static::ITEMS_ROUTE);
	}

	public function testTableInsertUpdateDeleteRoundTrip() {
		$tbl = $this->table();
		$tbl->name = static::TRICKY;
		$tbl->status = 0;
		$tbl->date_added = '2026-10-26 10:00:00';
		$item_id = (int)$tbl->insert();
		$this->assertGreaterThan(0, $item_id);
		$this->assertSame(static::TRICKY, $this->getItem($item_id)['name']);

		$tbl = $this->table();
		$tbl->item_id = $item_id;
		$tbl->name = 'Updated ' . static::TRICKY;
		$tbl->update();
		$row = $this->getItem($item_id);
		$this->assertSame('Updated ' . static::TRICKY, $row['name']);
		$this->assertSame('0', $row['status']);

		$tbl = $this->table();
		$tbl->item_id = $item_id;
		$this->assertTrue($tbl->hasPrimaryRecord());

		$tbl = $this->table();
		$tbl->name = 'Updated ' . static::TRICKY;
		$this->assertCount(1, $tbl->query()->rows);

		$tbl->delete();
		$this->assertEmpty($this->getItem($item_id));
	}

	public function testUpdateAndDeleteTouchOnlyMatchingRows() {
		$a = $this->addItem('A');
		$b = $this->addItem('B');

		$tbl = $this->table();
		$tbl->status = 0;
		$tbl->update(array('item_id' => "$a' OR '1'='1"));
		$this->assertSame('1', $this->getItem($b)['status']);

		(new \extension\ka_extensions\ka_lib_test\dbtable\Item())->delete(array('name' => "A' OR '1'='1"));
		$this->assertNotEmpty($this->getItem($a));
		$this->assertNotEmpty($this->getItem($b));
	}

	public function testQbWithInjectedValues() {
		$this->addItem('A');
		$this->addItem(static::TRICKY);

		$qb = new QB();
		$qb->select('t.*', static::ITEM_TABLE, 't');
		$qb->where('t.name', "A' OR '1'='1");
		$this->assertSame(array(), $qb->query()->rows);

		$qb = new QB();
		$qb->select('t.name', static::ITEM_TABLE, 't');
		$qb->where('t.name', static::TRICKY);
		$this->assertSame(array(array('name' => static::TRICKY)), $qb->query()->rows);

		$qb = new QB();
		$qb->select('t.name', static::ITEM_TABLE, 't');
		$qb->whereIn('t.name', array('A', static::TRICKY, "x') OR ('1'='1"));
		$qb->orderBy('t.item_id');
		$this->assertSame(array('A', static::TRICKY), array_column($qb->query()->rows, 'name'));
	}

	public function testRecordsSortingPagingAndFilter() {
		foreach (array('C', 'A', 'E', 'B', 'D') as $i => $name) {
			$this->addItem($name, $i % 2, '2026-01-0' . ($i + 1) . ' 00:00:00');
		}
		$rs = $this->recordset();

		$this->assertSame(array('E', 'D', 'C'), array_column($rs->getRecords(array('sort' => 'name', 'order' => 'DESC', 'start' => 0, 'limit' => 3)), 'name'));
		$this->assertSame(array('B', 'A'), array_column($rs->getRecords(array('sort' => 't.name', 'order' => 'DESC', 'start' => 3, 'limit' => 3)), 'name'));
		$this->assertSame(array('C', 'A', 'E', 'B', 'D'), array_column($rs->getRecords(array('sort' => 'date', 'order' => 'asc')), 'name'));
		$this->assertSame(5, (int)$rs->getRecordsTotal(array('sort' => 'name', 'start' => 0, 'limit' => 2)));

		$this->assertSame(array('A'), array_column($rs->getRecords(array('filter_name' => 'A')), 'name'));
		$this->assertSame(array(), $rs->getRecords(array('filter_name' => "A' OR '1'='1")));
		$this->assertSame(0, (int)$rs->getRecordsTotal(array('filter_name' => "A' OR '1'='1")));
	}

	public function testSortInjectionIsIgnored() {
		$this->addItem('B');
		$this->addItem('A');
		$rs = $this->recordset();
		foreach (array('(SELECT 1)', 't.name, (SELECT SLEEP(3))', 'name; DROP TABLE x', 't.status') as $sort) {
			$started = microtime(true);
			$rows = $rs->getRecords(array('sort' => $sort, 'order' => 'DESC, SLEEP(3)'));
			$this->assertLessThan(2, microtime(true) - $started, $sort);
			$this->assertCount(2, $rows, $sort);
		}
		$this->assertTrue($this->tableExists(static::ITEM_TABLE));
	}

	public function testTotalOfGroupedRecords() {
		$this->addItem('A', 1);
		$this->addItem('B', 1);
		$this->addItem('C', 0);
		$rs = $this->recordset();
		$rs->group_by_status = true;
		$this->assertSame(2, (int)$rs->getRecordsTotal());
		$this->assertCount(2, $rs->getRecords());
	}

	public function testSaveRecord() {
		$rs = $this->recordset();

		// a new record: the empty primary key from a form gets the next auto increment value
		$item_id = $rs->saveRecord(array('item_id' => '', 'name' => static::TRICKY, 'status' => '0', 'date_added' => '2026-10-26 00:00:00', 'not_a_field' => 'x'));
		$this->assertGreaterThan(0, (int)$item_id);
		$row = $this->getItem($item_id);
		$this->assertSame(static::TRICKY, $row['name']);
		$this->assertSame('0', $row['status']);

		// an existing record: only the given fields change
		$this->assertSame($item_id, $rs->saveRecord(array('item_id' => $item_id, 'name' => 'Changed')));
		$row = $this->getItem($item_id);
		$this->assertSame('Changed', $row['name']);
		$this->assertSame('0', $row['status']);

		$record = $rs->getRecord($item_id);
		$this->assertSame('Changed', $record['name']);
		$this->assertSame('Item Changed', $rs->fillRecord($record)['record_title']);

		$rs->deleteRecord($item_id);
		$this->assertEmpty($this->getItem($item_id));
		$this->assertSame(array(), $rs->getRecord($item_id));
	}

	public function testKaInsertUpdateDelete() {
		$db = $this->db();
		$item_id = (int)$db->ka_insert(static::ITEM_TABLE, array('name' => static::TRICKY, 'status' => 1, 'date_added' => '2026-01-01 00:00:00'));
		$this->assertSame(static::TRICKY, $this->getItem($item_id)['name']);

		$db->ka_insert(static::ITEM_TABLE, array('item_id' => $item_id, 'name' => 'Dup', 'status' => 0, 'date_added' => '2026-01-01 00:00:00'), false, true);
		$this->assertSame('Dup', $this->getItem($item_id)['name']);

		$db->ka_update(static::ITEM_TABLE, array('status' => 1), array('item_id' => $item_id));
		$this->assertSame('1', $this->getItem($item_id)['status']);

		$db->ka_delete(static::ITEM_TABLE, array('item_id' => $item_id));
		$this->assertEmpty($this->getItem($item_id));
	}
}
