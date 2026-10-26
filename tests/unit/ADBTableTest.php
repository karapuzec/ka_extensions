<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\ADBTable;
use extension\ka_extensions\ExceptionData;

class TestTable extends ADBTable {
	const TABLE_NAME = 'ka_test';

	public function getFields() {
		return array(
			'test_id' => array('primary_key' => true),
			'name'    => array(),
			'status'  => array(),
		);
	}
}

class TestPairTable extends ADBTable {
	const TABLE_NAME = 'ka_test_pair';

	public function getFields() {
		return array(
			'a_id'  => array('primary_key' => true),
			'b_id'  => array('primary_key' => true),
			'value' => array(),
		);
	}
}

class TestFreeTable extends ADBTable {
	const TABLE_NAME = 'ka_test_free';
}

/**
 * ADBTable: insert, update, delete and selection by the assigned values.
 *
 * @group orm
 */
class ADBTableTest extends UnitTestCase {

	public function testPrimaryKeysAndTableName() {
		$this->assertSame(array('test_id'), (new TestTable())->getPrimaryKeys());
		$this->assertSame(array('a_id', 'b_id'), (new TestPairTable())->getPrimaryKeys());
		$this->assertSame(array(), (new TestFreeTable())->getPrimaryKeys());
		$this->assertSame('ka_test', (new TestTable())->getTableName());
	}

	public function testInsertOfAssignedValues() {
		$this->adaptor->last_id = 12;
		$tbl = new TestTable();
		$tbl->name = "N'1";
		$tbl->status = 1;
		$this->assertSame(12, $tbl->insert());
		$this->assertSame("INSERT INTO `oc_ka_test` SET `name`='N\\'1', `status`='1'", $this->lastQuery());
		$this->assertSame("N'1", $tbl->name);
	}

	public function testReplace() {
		$tbl = new TestTable();
		$tbl->test_id = 3;
		$tbl->insert(true);
		$this->assertSame("REPLACE INTO `oc_ka_test` SET `test_id`='3'", $this->lastQuery());
	}

	public function testSetValuesTakesDeclaredFieldsOnly() {
		$tbl = new TestTable();
		$tbl->setValues(array('name' => 'N', 'is_admin' => 1, 'status' => 0));
		$this->assertSame(array('name' => 'N', 'status' => 0), $tbl->getValues());
	}

	public function testUpdateByPrimaryKey() {
		$tbl = new TestTable();
		$tbl->test_id = 5;
		$tbl->name = 'N';
		$tbl->update();
		$this->assertSame("UPDATE `oc_ka_test` SET `test_id`='5', `name`='N' WHERE `test_id`='5'", $this->lastQuery());
	}

	public function testUpdateByCompositeKey() {
		$tbl = new TestPairTable();
		$tbl->a_id = 1;
		$tbl->b_id = 2;
		$tbl->value = 'v';
		$tbl->update();
		$this->assertSame("UPDATE `oc_ka_test_pair` SET `a_id`='1', `b_id`='2', `value`='v' WHERE `a_id`='1' AND `b_id`='2'", $this->lastQuery());
	}

	public function testUpdateWithCondition() {
		$tbl = new TestTable();
		$tbl->status = 0;
		$tbl->update(array('name' => 'x'));
		$this->assertSame("UPDATE `oc_ka_test` SET `status`='0' WHERE `name`='x'", $this->lastQuery());
	}

	public function testUpdateWithoutPrimaryValueThrows() {
		$tbl = new TestTable();
		$tbl->name = 'N';
		$this->expectException(\Exception::class);
		$tbl->update();
	}

	public function testUpdateOfATableWithoutKeysThrows() {
		$tbl = new TestFreeTable();
		$tbl->name = 'N';
		$this->expectException(\Exception::class);
		$tbl->update();
	}

	public function testUpdateWithEmptyConditionThrows() {
		$tbl = new TestTable();
		$tbl->name = 'N';
		try {
			$tbl->update(array());
			$this->fail('No exception');
		} catch (ExceptionData $e) {
		}
		try {
			$tbl->update('');
			$this->fail('No exception');
		} catch (ExceptionData $e) {
		}
		$this->assertSame(array(), $this->queries());
	}

	public function testDeleteByAssignedValues() {
		$tbl = new TestTable();
		$tbl->test_id = "1' OR '1'='1";
		$tbl->delete();
		$this->assertSame("DELETE FROM `oc_ka_test` WHERE `test_id`='1\\' OR \\'1\\'=\\'1'", $this->lastQuery());

		(new TestTable())->delete(array('status' => 0));
		$this->assertSame("DELETE FROM `oc_ka_test` WHERE `status`='0'", $this->lastQuery());
	}

	public function testDeleteWithoutConditionThrows() {
		try {
			(new TestTable())->delete();
			$this->fail('No exception');
		} catch (ExceptionData $e) {
		}
		try {
			(new TestTable())->delete(array());
			$this->fail('No exception');
		} catch (ExceptionData $e) {
		}
		$this->assertSame(array(), $this->queries());
	}

	public function testDeleteAll() {
		(new TestTable())->deleteAll();
		$this->assertSame("DELETE FROM `oc_ka_test`", $this->lastQuery());
	}

	public function testEmptyResetsValues() {
		$tbl = new TestTable();
		$tbl->name = 'N';
		$tbl->empty();
		$this->assertSame(array(), $tbl->getValues());
	}

	public function testQueryByAssignedValues() {
		$tbl = new TestTable();
		$tbl->name = "a'b";
		$tbl->query();
		$this->assertSame("SELECT * FROM `oc_ka_test` WHERE (name = 'a\\'b')", $this->normalizeSql($this->lastQuery()));
	}

	public function testHasPrimaryRecord() {
		$tbl = new TestTable();
		$tbl->test_id = 9;
		$this->assertFalse($tbl->hasPrimaryRecord());
		$this->assertSame("SELECT 1 FROM `oc_ka_test` WHERE (test_id = '9')", $this->normalizeSql($this->lastQuery()));

		$this->adaptor->addResult('FROM `oc_ka_test`', array(array('1' => '1')));
		$this->assertTrue($tbl->hasPrimaryRecord());
	}

	public function testHasPrimaryRecordWithoutKeyThrows() {
		$this->expectException(\Exception::class);
		(new TestTable())->hasPrimaryRecord();
	}

	public function testEmptyRecord() {
		$this->assertSame(array('test_id' => '', 'name' => '', 'status' => ''), (new TestTable())->getEmptyRecord());
	}
}
