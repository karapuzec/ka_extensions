<?php

namespace KaExtensionsTests\Unit;

/**
 * Ka Extensions DB methods (ka_insert, ka_update, ka_delete): generated SQL and escaping.
 *
 * @group orm
 */
class DbTest extends UnitTestCase {

	protected function db() {
		return $this->registry->get('db');
	}

	public function testInsert() {
		$this->adaptor->last_id = 7;
		$id = $this->db()->ka_insert('product', array('model' => "O'Reilly", 'price' => 1.5, 'image' => null));
		$this->assertSame(7, $id);
		$this->assertSame("INSERT INTO `oc_product` SET `model`='O\\'Reilly', `price`='1.5', `image` = NULL", $this->lastQuery());
	}

	public function testInsertEscapesInjections() {
		$this->db()->ka_insert('customer', array('firstname' => "x', `status`='1", 'lastname' => "a\\"));
		$this->assertSame("INSERT INTO `oc_customer` SET `firstname`='x\\', `status`=\\'1', `lastname`='a\\\\'", $this->lastQuery());
	}

	public function testReplaceAndUpdateOnDuplicate() {
		$this->db()->ka_insert('setting', array('key' => 'k', 'value' => 'v'), true);
		$this->assertSame("REPLACE INTO `oc_setting` SET `key`='k', `value`='v'", $this->lastQuery());

		$this->db()->ka_insert('setting', array('key' => 'k', 'value' => 'v'), false, true);
		$this->assertSame("INSERT INTO `oc_setting` SET `key`='k', `value`='v' ON DUPLICATE KEY UPDATE `key`='k', `value`='v'", $this->lastQuery());
	}

	public function testInsertOfNothingDoesNoQuery() {
		$this->assertNull($this->db()->ka_insert('product', array()));
		$this->assertSame(array(), $this->queries());
	}

	public function testInsertWithoutTableThrows() {
		$this->expectException(\Exception::class);
		$this->db()->ka_insert('', array('a' => 1));
	}

	public function testUpdateWithArrayCondition() {
		$this->db()->ka_update('product', array('model' => 'M', 'image' => null), array('product_id' => "5' OR '1'='1", 'date_deleted' => null));
		$this->assertSame(
			"UPDATE `oc_product` SET `model`='M', `image` = NULL WHERE `product_id`='5\\' OR \\'1\\'=\\'1' AND `date_deleted` IS NULL",
			$this->lastQuery()
		);
	}

	public function testUpdateWithStringAndRawConditions() {
		$this->db()->ka_update('product', array('status' => 0), "product_id > '10'");
		$this->assertSame("UPDATE `oc_product` SET `status`='0' WHERE product_id > '10'", $this->lastQuery());

		$this->db()->ka_update('product', array('status' => 0), array('quantity < 0', 'store_id' => 0));
		$this->assertSame("UPDATE `oc_product` SET `status`='0' WHERE quantity < 0 AND `store_id`='0'", $this->lastQuery());
	}

	public function testUpdateOfNothingDoesNoQuery() {
		$this->assertNull($this->db()->ka_update('product', array(), array('product_id' => 1)));
		$this->assertSame(array(), $this->queries());
	}

	/**
	 * Documents the current behavior: ka_update() without a condition updates every row.
	 * ADBTable::update() refuses an empty condition.
	 */
	public function testUpdateWithoutConditionHasNoWhere() {
		$this->db()->ka_update('product', array('status' => 0));
		$this->assertSame("UPDATE `oc_product` SET `status`='0'", $this->lastQuery());
	}

	public function testDelete() {
		$this->db()->ka_delete('product', array('product_id' => "1' OR 1=1 -- "));
		$this->assertSame("DELETE FROM `oc_product` WHERE `product_id`='1\\' OR 1=1 -- '", $this->lastQuery());

		$this->db()->ka_delete('product', "product_id IN (1,2)");
		$this->assertSame("DELETE FROM `oc_product` WHERE product_id IN (1,2)", $this->lastQuery());
	}

	public function testDeleteWithNullConditionThrows() {
		$this->expectException(\extension\ka_extensions\ExceptionData::class);
		$this->db()->ka_delete('product', null);
	}

	public function testDeleteWithEmptyConditionDeletesAll() {
		$this->db()->ka_delete('product', '');
		$this->assertSame("DELETE FROM `oc_product`", $this->lastQuery());
	}
}
