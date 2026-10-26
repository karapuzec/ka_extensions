<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\Arrays;
use extension\ka_extensions\Pagination;

class TopMessagesHost {
	use \extension\ka_extensions\TraitController;

	public $session;
}

/**
 * Small library helpers: top messages, Arrays, Pagination.
 *
 * @group helpers
 */
class MiscTest extends UnitTestCase {

	// --- top messages

	protected function host() {
		$host = new TopMessagesHost();
		$host->session = $this->registry->get('session');
		return $host;
	}

	public function testTopMessageTypes() {
		$host = $this->host();
		$this->call($host, 'addTopMessage', 'Saved', 'S');
		$this->call($host, 'addTopMessage', 'Failed', 'E');
		$this->call($host, 'addTopMessage', 'Careful', 'W');
		$this->call($host, 'addTopMessage', 'Note');
		$this->assertSame(array(
			array('type' => 'S', 'content' => 'Saved'),
			array('type' => 'E', 'content' => 'Failed'),
			array('type' => 'W', 'content' => 'Careful'),
			array('type' => 'I', 'content' => 'Note'),
		), $this->call($host, 'getTopMessages', false));
	}

	public function testSeveralTopMessagesAtOnce() {
		$host = $this->host();
		$this->call($host, 'addTopMessage', array('One', 'Two'), 'E');
		$this->assertSame(array(
			array('type' => 'E', 'content' => 'One'),
			array('type' => 'E', 'content' => 'Two'),
		), $this->call($host, 'getTopMessages'));
	}

	public function testTopMessagesAreCleared() {
		$host = $this->host();
		$this->assertNull($this->call($host, 'getTopMessages'));
		$this->call($host, 'addTopMessage', 'A', 'S');
		$this->assertCount(1, $this->call($host, 'getTopMessages', false));
		$this->assertCount(1, $this->call($host, 'getTopMessages'));
		$this->assertNull($this->call($host, 'getTopMessages'));
		$this->call($host, 'addTopMessage', 'B', 'S');
		$this->assertSame(array(array('type' => 'S', 'content' => 'B')), $this->call($host, 'getTopMessages'));
	}

	// --- Arrays

	public function testInsertAfterAndBeforeKey() {
		$array = array('a' => 1, 'b' => 2, 'c' => 3);
		$this->assertSame(array('a' => 1, 'x' => 9, 'b' => 2, 'c' => 3), Arrays::insertAfterKey($array, 'a', array('x' => 9)));
		$this->assertSame(array('a' => 1, 'b' => 2, 'c' => 3, 'x' => 9), Arrays::insertAfterKey($array, 'c', array('x' => 9)));
		$this->assertSame(array('x' => 9, 'a' => 1, 'b' => 2, 'c' => 3), Arrays::insertBeforeKey($array, 'a', array('x' => 9)));
		$this->assertSame($array, Arrays::insertAfterKey($array, 'missing', array('x' => 9)));
		$this->assertSame($array, Arrays::insertBeforeKey($array, 'missing', array('x' => 9)));
	}

	public function testInArray() {
		$rows = array(array('id' => '1'), 'not an array', array('name' => 'x'), array('id' => 2));
		$this->assertTrue(Arrays::inArray(2, $rows, 'id'));
		$this->assertTrue(Arrays::inArray('1', $rows, 'id'));
		$this->assertFalse(Arrays::inArray(1, $rows, 'id'));
		$this->assertFalse(Arrays::inArray(1, 'string', 'id'));
	}

	public function testFillObject() {
		$obj = new \stdClass();
		Arrays::fillObject($obj, array('a' => 1, 'b' => 'x'));
		$this->assertSame(1, $obj->a);
		$this->assertSame('x', $obj->b);
	}

	// --- Pagination

	public function testPaginationParameters() {
		$p = new Pagination(array('total' => 95, 'page' => 3, 'limit' => 10, 'url' => 'list?page={page}'));
		$this->assertSame(95, $p->total);
		$this->assertSame(10, $p->limit);
		$this->assertSame('pagination:95:3:10:list?page={page}', $p->render());
	}

	public function testPaginationResults() {
		$this->registry->get('language')->set('text_pagination', 'Showing %d to %d of %d (%d Pages)');
		$p = new Pagination(array('total' => 95, 'page' => 3, 'limit' => 10));
		$this->assertSame('Showing 21 to 30 of 95 (10 Pages)', $p->getResults());

		$p = new Pagination(array('total' => 95, 'page' => 10, 'limit' => 10));
		$this->assertSame('Showing 91 to 95 of 95 (10 Pages)', $p->getResults());

		$p = new Pagination(array('total' => 0, 'page' => 1, 'limit' => 10));
		$this->assertSame('Showing 0 to 0 of 0 (0 Pages)', $p->getResults());

		$p = new Pagination(array('total' => 5, 'page' => 1, 'limit' => 10));
		$this->assertSame('Items 1-5 of 5', $p->getResults('Items %d-%d of %d'));
	}

	public function testPaginationText() {
		$p = new Pagination();
		$p->text = 'custom';
		$this->assertSame('custom', $p->text);
	}

	/**
	 * Without 'limit' the class reads 'config_pagination_admin' (the OpenCart 4 setting). OpenCart 3
	 * keeps 'Default Items Per Page (Admin)' in 'config_limit_admin', so the store setting is ignored
	 * and 20 is used.
	 *
	 * @group known-bug
	 */
	public function testPaginationTakesTheOpenCart3Limit() {
		$this->registry->get('config')->set('config_limit_admin', 7);
		$p = new Pagination(array('total' => 50, 'page' => 1));
		$this->assertSame(7, $p->limit);
	}
}
