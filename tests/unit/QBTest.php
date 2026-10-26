<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\QB;

/**
 * QB: generated SQL and escaping.
 *
 * @group orm
 */
class QBTest extends UnitTestCase {

	protected function sql(QB $qb) {
		return $this->normalizeSql($qb->getSql());
	}

	public function testSimpleSelect() {
		$qb = new QB();
		$qb->select('*', 'product');
		$qb->where('product_id', 42);
		$this->assertSame("SELECT * FROM `oc_product` WHERE (product_id = '42')", $this->sql($qb));
	}

	public function testNothingSelectedGivesEmptySql() {
		$qb = new QB();
		$qb->from('product');
		$this->assertSame('', $qb->getSql());
	}

	public function testAliasesJoinsGroupOrderLimit() {
		$qb = new QB();
		$qb->select('p.product_id', 'product', 'p');
		$qb->select('COUNT(*) AS total');
		$qb->innerJoin('product_description', 'pd', 'pd.product_id = p.product_id');
		$qb->leftJoin('product_to_store', 'p2s', 'p2s.product_id = p.product_id');
		$qb->where('p.status', 1);
		$qb->groupBy('p.product_id');
		$qb->orderBy('p.sort_order');
		$qb->orderBy('pd.name DESC');
		$qb->limit(20, 10);
		$this->assertSame(
			"SELECT p.product_id,COUNT(*) AS total FROM `oc_product` p"
			. " INNER JOIN `oc_product_description` pd ON pd.product_id = p.product_id"
			. " LEFT JOIN `oc_product_to_store` p2s ON p2s.product_id = p.product_id"
			. " WHERE (p.status = '1') GROUP BY p.product_id ORDER BY p.sort_order, pd.name DESC LIMIT 20, 10",
			$this->sql($qb)
		);
	}

	public function testWhereValueIsEscaped() {
		$qb = new QB();
		$qb->select('*', 'customer');
		$qb->where('email', "x' OR '1'='1");
		$this->assertSame("SELECT * FROM `oc_customer` WHERE (email = 'x\\' OR \\'1\\'=\\'1')", $this->sql($qb));

		$qb = new QB();
		$qb->select('*', 'customer');
		$qb->where('email', "a\\' OR 1 -- ");
		$this->assertStringContainsString("email = 'a\\\\\\' OR 1 -- '", $qb->getSql());
	}

	public function testWhereNullAndRawConditions() {
		$qb = new QB();
		$qb->select('*', 'customer');
		$qb->where('date_deleted', null);
		$qb->where('status = 1');
		$qb->where(array("a = '1'", "b = '2'"));
		$this->assertSame("SELECT * FROM `oc_customer` WHERE (date_deleted IS NULL) AND (status = 1) AND (a = '1' OR b = '2')", $this->sql($qb));
	}

	public function testWhereIn() {
		$qb = new QB();
		$qb->select('*', 'product');
		$qb->whereIn('product_id', array(1, "2' OR 1=1 -- "));
		$this->assertSame("SELECT * FROM `oc_product` WHERE (product_id IN ('1','2\\' OR 1=1 -- '))", $this->sql($qb));
	}

	public function testWhereInOfAnEmptyListMatchesNothing() {
		$qb = new QB();
		$qb->select('*', 'product');
		$qb->whereIn('product_id', array());
		$this->assertSame("SELECT * FROM `oc_product` WHERE (0)", $this->sql($qb));
	}

	public function testLimitIsCastToIntegers() {
		$qb = new QB();
		$qb->select('*', 'product');
		$qb->limit('5; DROP TABLE x', '10 OR 1');
		$this->assertSame("SELECT * FROM `oc_product` LIMIT 5, 10", $this->sql($qb));
	}

	public function testSameOrderIsAddedOnce() {
		$qb = new QB();
		$qb->select('*', 'product');
		$qb->orderBy('name');
		$qb->orderBy('name');
		$qb->groupBy('model');
		$qb->groupBy('model');
		$this->assertSame("SELECT * FROM `oc_product` GROUP BY model ORDER BY name", $this->sql($qb));
	}

	/**
	 * orderBy($order, $after) / groupBy() pass the arguments to Arrays::insertAfterKey($array, $key, $value)
	 * in the wrong order (the new value as the key, the string $after as the array to insert): the value
	 * is silently dropped (or a TypeError when $order is already in the list).
	 *
	 * @group known-bug
	 */
	public function testOrderByAfter() {
		$qb = new QB();
		$qb->select('*', 'product');
		$qb->orderBy('a');
		$qb->orderBy('b');
		$qb->orderBy('c', 'a');
		$this->assertSame("SELECT * FROM `oc_product` ORDER BY a, c, b", $this->sql($qb));
	}

	/**
	 * Several from() tables are joined without a comma: "FROM `oc_a` a`oc_b` b".
	 *
	 * @group known-bug
	 */
	public function testSeveralFromTables() {
		$qb = new QB();
		$qb->select('*', 'product', 'p');
		$qb->from('category', 'c');
		$this->assertSame("SELECT * FROM `oc_product` p, `oc_category` c", $this->sql($qb));
	}

	public function testDelete() {
		$qb = new QB();
		$qb->delete('t', 'session', 't');
		$qb->where('t.expire < NOW()');
		$this->assertSame("DELETE t FROM `oc_session` t WHERE (t.expire < NOW())", $this->sql($qb));
	}

	public function testUnionAll() {
		$a = new QB();
		$a->select('product_id AS id', 'product');
		$b = new QB();
		$b->select('category_id AS id', 'category');

		$qb = new QB();
		$qb->select('u.id');
		$qb->fromUnionAll($a);
		$qb->fromUnionAll($b, 'u');
		$this->assertSame(
			"SELECT u.id FROM (SELECT product_id AS id FROM `oc_product` UNION ALL SELECT category_id AS id FROM `oc_category`) AS u",
			$this->sql($qb)
		);
	}

	public function testQueryRunsTheSql() {
		$this->adaptor->addResult('FROM `oc_product`', array(array('product_id' => '1')));
		$qb = new QB();
		$qb->select('*', 'product');
		$result = $qb->query();
		$this->assertSame('1', $result->row['product_id']);
		$this->assertSame($qb->getSql(), $this->lastQuery());
	}
}
