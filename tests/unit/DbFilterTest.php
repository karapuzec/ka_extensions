<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\DBProxy;

// a model with TraitDBFilter, its queries are changed by the filters it sets
class DbFilterTestModel extends \Model {

	use \extension\ka_extensions\TraitDBFilter;

	public function getRows() {
		return $this->db->query("SELECT * FROM t");
	}

	// sets a filter, runs a query and removes the filter without an id (as the existing modules do)
	public function getRowsFiltered($tag) {
		$this->db->setFilter([$this, 'tagFilter'], $tag);
		$this->getRows();
		$this->db->removeFilter();
	}

	// an outer method: its filter must stay for its own queries after a nested method removed its filter
	public function getRowsTwice($outer_tag, $inner_tag) {
		$filter_id = $this->db->setFilter([$this, 'tagFilter'], $outer_tag);
		try {
			$this->getRowsFiltered($inner_tag);
			$this->getRows();
		} finally {
			$this->db->removeFilter($filter_id);
		}
	}

	public function tagFilter($sql, $tag) {
		return $sql . ' /*' . $tag . '*/';
	}
}

class DbFilterTestOtherModel extends DbFilterTestModel {
}

/**
 * TraitDBFilter / DBProxy: setFilter() returns an id, removeFilter($id) removes only that filter,
 * removeFilter() without an id removes only the last filter of the current class (before 4.1.1.29 it
 * removed all filters of the class).
 *
 * @group orm
 */
class DbFilterTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// DBProxy is a singleton, every test starts with a new one
		DBProxy::$dbproxy = null;
	}

	protected function tearDown(): void {
		DBProxy::$dbproxy = null;
	}

	public function testSetFilterReturnsUniqueIds() {
		$model = new DbFilterTestModel($this->registry);
		$other = new DbFilterTestOtherModel($this->registry);

		$id1 = $model->db->setFilter([$model, 'tagFilter'], 'a');
		$id2 = $model->db->setFilter([$model, 'tagFilter'], 'b');
		$id3 = $other->db->setFilter([$other, 'tagFilter'], 'c');

		$this->assertIsInt($id1);
		$this->assertCount(3, array_unique(array($id1, $id2, $id3)));
	}

	public function testNestedPairsKeepTheOuterFilter() {
		$model = new DbFilterTestModel($this->registry);
		$model->getRowsTwice('outer', 'inner');

		$this->assertSame(array(
			'SELECT * FROM t /*outer*/ /*inner*/',
			// the inner removeFilter() removed the inner filter only
			'SELECT * FROM t /*outer*/',
		), $this->queries());

		$model->getRows();
		$this->assertSame('SELECT * FROM t', $this->lastQuery());
	}

	public function testRemoveById() {
		$model = new DbFilterTestModel($this->registry);
		$id_a = $model->db->setFilter([$model, 'tagFilter'], 'a');
		$id_b = $model->db->setFilter([$model, 'tagFilter'], 'b');

		// not the last filter
		$this->assertTrue($model->db->removeFilter($id_a));
		$model->getRows();
		$this->assertSame('SELECT * FROM t /*b*/', $this->lastQuery());

		// an id is removed once
		$this->assertFalse($model->db->removeFilter($id_a));

		$this->assertTrue($model->db->removeFilter($id_b));
		$model->getRows();
		$this->assertSame('SELECT * FROM t', $this->lastQuery());
	}

	public function testRemoveByIdOfAnotherClass() {
		$model = new DbFilterTestModel($this->registry);
		$other = new DbFilterTestOtherModel($this->registry);

		$id_a = $model->db->setFilter([$model, 'tagFilter'], 'a');
		$other->db->setFilter([$other, 'tagFilter'], 'c');

		// the current class is the other one, the id still finds the filter of the first class
		$this->assertTrue($other->db->removeFilter($id_a));
		$model->getRows();
		$this->assertSame('SELECT * FROM t', $this->lastQuery());
		$other->getRows();
		$this->assertSame('SELECT * FROM t /*c*/', $this->lastQuery());
	}

	public function testRemoveWithoutIdRemovesTheLastFilterOfTheClass() {
		$model = new DbFilterTestModel($this->registry);
		$other = new DbFilterTestOtherModel($this->registry);

		$model->db->setFilter([$model, 'tagFilter'], 'a');
		$model->db->setFilter([$model, 'tagFilter'], 'b');
		$other->db->setFilter([$other, 'tagFilter'], 'c');

		$this->assertTrue($model->db->removeFilter());
		$model->getRows();
		$this->assertSame('SELECT * FROM t /*a*/', $this->lastQuery());

		// filters of other classes stay
		$other->getRows();
		$this->assertSame('SELECT * FROM t /*c*/', $this->lastQuery());

		$this->assertTrue($model->db->removeFilter());
		$this->assertFalse($model->db->removeFilter());
		$model->getRows();
		$this->assertSame('SELECT * FROM t', $this->lastQuery());
	}
}
