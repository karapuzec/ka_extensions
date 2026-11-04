<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\Model;
use extension\ka_extensions\QB;

/**
	A model of a module: its queries are made with getQB().
*/
class TestQBModel extends Model {

	public function productsSql() {
		$qb = $this->getQB('product', 'p');
		$qb->select('p.product_id');
		$qb->where('p.model', 'X');
		return $qb->getSql();
	}

	public function qb($table = '', $alias = '') {
		return $this->getQB($table, $alias);
	}
}

/**
	What a kamod child of the model in another module does: it subscribes in the constructor.
*/
class TestQBModelChild extends TestQBModel {

	public $log = array();

	function __construct($registry) {
		parent::__construct($registry);

		$this->onQB('product', function($qb) {
			$this->log[] = 'product-0';
			$qb->where('p.status', 1);
		});
		$this->onQB('*', function($qb) {
			$this->log[] = 'all:' . $qb->getTable();
		});
		$this->onQB('product', function($qb) {
			$this->log[] = 'product-minus';
		}, -5);
		$this->onQB('product', function($qb) {
			$this->log[] = 'product-0b';
		});
	}
}

/**
 * getQB() / onQB() of the base Model and the hooks of QB (called on every getSql() call).
 *
 * @group orm
 */
class QBHooksTest extends UnitTestCase {

	protected function sql($sql) {
		return $this->normalizeSql($sql);
	}

	public function testGetQBWithoutSubscribers() {
		$model = new TestQBModel($this->registry);
		$this->assertSame($this->sql("SELECT p.product_id FROM `" . DB_PREFIX . "product` p WHERE (p.model = 'X')"), $this->sql($model->productsSql()));
		$this->assertSame('product', $model->qb('product')->getTable());
		$this->assertSame('', $model->qb()->getTable());
	}

	public function testSubscribersAddConditionsInOrder() {
		$model = new TestQBModelChild($this->registry);
		$sql = $this->sql($model->productsSql());
		$this->assertSame($this->sql("SELECT p.product_id FROM `" . DB_PREFIX . "product` p WHERE (p.model = 'X') AND (p.status = '1')"), $sql);
		// table callbacks by sort order, then by subscription; '*' after them
		$this->assertSame(array('product-minus', 'product-0', 'product-0b', 'all:product'), $model->log);
	}

	public function testStarIsCalledForOtherTablesAndWithoutTable() {
		$model = new TestQBModelChild($this->registry);
		$qb = $model->qb('category', 'c');
		$qb->select('*');
		$this->assertStringNotContainsString('status', $qb->getSql());
		$qb = $model->qb();
		$qb->select('1');
		$qb->getSql();
		$this->assertSame(array('all:category', 'all:'), $model->log);
	}

	public function testCallbackRunsOnEveryGetSql() {
		$model = new TestQBModelChild($this->registry);
		$qb = $model->qb('product', 'p');
		$qb->select('p.product_id');
		$qb->getSql();
		$qb->getSql();
		$this->assertSame(2, count(array_keys($model->log, 'product-0')));
	}

	public function testHooksBelongToTheObject() {
		new TestQBModelChild($this->registry);
		$model = new TestQBModel($this->registry);
		$this->assertStringNotContainsString('status', $model->productsSql());
	}

	public function testNewQBIsUnchanged() {
		$qb = new QB();
		$qb->select('*', 'product', 'p');
		$qb->where('p.product_id', 5);
		$this->assertSame('', $qb->getTable());
		$this->assertSame($this->sql("SELECT * FROM `" . DB_PREFIX . "product` p WHERE (p.product_id = '5')"), $this->sql($qb->getSql()));

		$calls = 0;
		$qb->addHook(function($q) use (&$calls) { $calls++; });
		$qb->getSql();
		$this->assertSame(1, $calls);
	}
}
