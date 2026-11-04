<?php

namespace KaExtensionsTests\Integration;

/**
 * getQB() / onQB() of the library Model on a real database: a subscriber of a child model adds a condition.
 *
 * @group smoke
 */
class QbHooksTest extends LibraryTestCase {

	protected function setUp(): void {
		$this->bootAdmin();
		$this->createItemTable();
	}

	protected function tearDown(): void {
		$this->dropItemTable();
	}

	public function testSubscriberOfAChildModel() {
		$this->addItem('B', 1);
		$this->addItem('A', 0);
		$this->addItem('C', 1);

		$model = $this->oc->get('load')->kamodel('extension/ka_extensions/ka_lib_test/qbitems');
		$this->assertSame(array('A', 'B', 'C'), $model->getNames());
		$this->assertStringNotContainsString('status', $model->last_sql);

		$child = new \extension\ka_extensions\ka_lib_test\ModelQbitemsEnabled($this->oc->getRegistry());
		$child->onLoadExt();
		$this->assertSame(array('B', 'C'), $child->getNames());
		$this->assertStringContainsString("(i.status = '1')", $child->last_sql);
		$this->assertStringContainsString('`' . DB_PREFIX . 'ka_lib_test_item` i', $child->last_sql);
	}
}
