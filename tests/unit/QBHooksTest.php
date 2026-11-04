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
	A standard (non-library) class using the trait, e.g. a kamod child of an OpenCart model.
*/
class TestQBPlain {
	use \extension\ka_extensions\TraitQB;

	public function sub($table, $cb) { return $this->onQB($table, $cb); }
	public function unsub($id) { $this->offQB($id); }
	public function qb($table = '', $alias = '') { return $this->getQB($table, $alias); }
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
		$this->assertSame('product', $qb->getTable());
		$this->assertSame($this->sql("SELECT * FROM `" . DB_PREFIX . "product` p WHERE (p.product_id = '5')"), $this->sql($qb->getSql()));

		$calls = 0;
		$qb->addHook(function($q) use (&$calls) { $calls++; });
		$qb->getSql();
		$this->assertSame(1, $calls);
	}

	public function testTableKeyIsTheFirstFrom() {
		$qb = new QB();
		$this->assertSame('', $qb->getTable());
		$qb->from('product', 'p');
		$qb->from('category', 'c');
		$qb->innerJoin('product_description', 'pd', 'pd.product_id = p.product_id');
		$qb->leftJoin('manufacturer', 'm', 'm.manufacturer_id = p.manufacturer_id');
		$this->assertSame('product', $qb->getTable());
	}

	public function testTraitInAStandardClass() {
		$obj = new TestQBPlain();
		$obj->sub('product', function($qb) { $qb->where('p.status', 1); });
		$qb = $obj->qb('product', 'p');
		$qb->select('p.product_id');
		$this->assertSame('product', $qb->getTable());
		$this->assertStringContainsString("(p.status = '1')", $qb->getSql());
	}

	/**
		fromUnionAll(): the outer QB has no table key (no from()), the QB objects inside the union run
		their own hooks once per getSql() of the outer QB.
	*/
	public function testUnionAll() {
		$model = new TestQBModelChild($this->registry);
		$a = $model->qb('product', 'p');
		$a->select('p.product_id');
		$b = $model->qb('category', 'c');
		$b->select('c.category_id AS product_id');

		$union = $model->qb();
		$union->select('t.*');
		$union->fromUnionAll($a);
		$union->fromUnionAll($b, 'u');
		$this->assertSame('', $union->getTable());

		$model->log = array();
		$sql = $this->sql($union->getSql());
		$this->assertSame($this->sql("SELECT t.* FROM (SELECT p.product_id FROM `" . DB_PREFIX . "product` p WHERE (p.status = '1') UNION ALL SELECT c.category_id AS product_id FROM `" . DB_PREFIX . "category` c) AS u"), $sql);
		// the outer '*', then the inner objects: product (3 callbacks + '*'), category ('*')
		$this->assertSame(array('all:', 'product-minus', 'product-0', 'product-0b', 'all:product', 'all:category'), $model->log);

		$model->log = array();
		$union->getSql();
		$this->assertSame(6, count($model->log));
	}

	public function testOffQB() {
		$obj = new TestQBPlain();
		$id = $obj->sub('product', function($qb) { $qb->where('p.status', 1); });
		$id2 = $obj->sub('product', function($qb) { $qb->where('p.model', 'M'); });
		$this->assertNotEquals($id, $id2);

		$first = $obj->qb('product', 'p');
		$first->select('p.product_id');
		$obj->unsub($id);
		$obj->unsub(12345);
		$obj->unsub('no-such-id');
		$second = $obj->qb('product', 'p');
		$second->select('p.product_id');

		// the QB made while the subscription was active keeps the callback
		$this->assertStringContainsString("(p.status = '1')", $first->getSql());
		$this->assertStringNotContainsString('p.status', $second->getSql());
		$this->assertStringContainsString("(p.model = 'M')", $second->getSql());
	}
}
