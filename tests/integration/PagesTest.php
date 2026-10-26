<?php

namespace KaExtensionsTests\Integration;

/**
 * ControllerList and ControllerForm pages of the test module, requested like the browser does.
 *
 * @group lists
 * @group forms
 */
class PagesTest extends LibraryTestCase {

	protected $saved_permissions;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->saved_permissions = $this->getPermissions($this->getUserGroupId());
		$this->grantTestPages();
		$this->createItemTable();
		$this->oc->get('config')->set('config_limit_admin', 10);
	}

	protected function tearDown(): void {
		$this->setPermissions($this->getUserGroupId(), $this->saved_permissions);
		$this->dropItemTable();
	}

	protected function addItems($count) {
		$ids = array();
		for ($i = 1; $i <= $count; $i++) {
			$ids[] = $this->addItem(sprintf('Item %02d', $i), 1, sprintf('2026-01-%02d 00:00:00', $i));
		}
		return $ids;
	}

	protected function listNames($output) {
		preg_match_all('/<p class="record" data-id="\d+">([^|]*)\|/', $output, $matches);
		return $matches[1];
	}

	protected function listPage($get = array()) {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$result = $this->oc->request(static::ITEMS_ROUTE, $get);
		$this->assertStringContainsString('id="ka-lib-test-list"', $result['output']);
		return $result['output'];
	}

	// --- list

	public function testFirstPage() {
		$this->addItems(25);
		$output = $this->listPage(array('sort' => 'name'));
		$this->assertSame(array('Item 01', 'Item 02', 'Item 03', 'Item 04', 'Item 05', 'Item 06', 'Item 07', 'Item 08', 'Item 09', 'Item 10'), $this->listNames($output));
		$this->assertStringContainsString('<p class="results">Showing 1 to 10 of 25 (3 Pages)</p>', $output);
	}

	public function testOtherPagesAndSorting() {
		$this->addItems(25);
		$output = $this->listPage(array('sort' => 'name', 'order' => 'DESC', 'page' => '3'));
		$this->assertSame(array('Item 05', 'Item 04', 'Item 03', 'Item 02', 'Item 01'), $this->listNames($output));
		$this->assertStringContainsString('Showing 21 to 25 of 25 (3 Pages)', $output);
		// the pagination links keep the sorting
		$this->assertMatchesRegularExpression('/route=extension\/ka_extensions\/ka_lib_test\/items[^"]*sort=name&amp;order=DESC[^"]*page=2/', $output);
	}

	public function testWrongPageGivesTheFirstPage() {
		$this->addItems(12);
		foreach (array('-2', '0', 'abc') as $page) {
			$output = $this->listPage(array('sort' => 'name', 'page' => $page));
			$this->assertSame('Item 01', $this->listNames($output)[0], $page);
		}
	}

	public function testPageAfterTheLastIsEmpty() {
		$this->addItems(5);
		$this->assertSame(array(), $this->listNames($this->listPage(array('page' => '9'))));
	}

	public function testInjectionInListParameters() {
		$this->addItems(3);
		$output = $this->listPage(array(
			'sort'        => '(SELECT SLEEP(3))',
			'order'       => 'ASC, SLEEP(3)',
			'filter_name' => "x' OR '1'='1",
			'page'        => '1 OR 1=1',
		));
		$this->assertSame(array(), $this->listNames($output));
		$this->assertTrue($this->tableExists(static::ITEM_TABLE));
	}

	public function testRecordActionsAndSortLinks() {
		$ids = $this->addItems(1);
		$output = $this->listPage(array('sort' => 'name', 'order' => 'ASC'));
		$this->assertMatchesRegularExpression('/\|edit=[^|]*route=extension\/ka_extensions\/ka_lib_test\/item&amp;[^|]*item_id=' . $ids[0] . '/', $output);
		$this->assertMatchesRegularExpression('/\|delete=[^|<]*route=extension\/ka_extensions\/ka_lib_test\/items\/deleteRecord[^|<]*item_id=' . $ids[0] . '/', $output);
		// array parameters of linka() are joined with '&' (not '&amp;') by OpenCart
		$this->assertMatchesRegularExpression('/<p class="sort-name">[^<]*sort=name&(amp;)?order=DESC/', $output);
		// the admin token is in the links
		$this->assertStringContainsString('user_token=' . $this->oc->getUserToken(), $output);
	}

	public function testDeleteSelected() {
		$ids = $this->addItems(3);
		$result = $this->oc->request(static::ITEMS_ROUTE . '/delete', array('page' => '2'), array('selected' => array($ids[0], $ids[2])));
		$this->assertStringContainsString('route=extension/ka_extensions/ka_lib_test/items', $result['redirect']);
		$this->assertStringContainsString('page=2', $result['redirect']);
		$this->assertEmpty($this->getItem($ids[0]));
		$this->assertNotEmpty($this->getItem($ids[1]));
		$this->assertEmpty($this->getItem($ids[2]));
		$this->assertSame(array('S'), array_column($this->topMessages(), 'type'));
	}

	public function testDeleteRecord() {
		$ids = $this->addItems(2);
		$result = $this->oc->request(static::ITEMS_ROUTE . '/deleteRecord', array('item_id' => $ids[1]), array('confirm' => '1'));
		$this->assertStringNotContainsString('item_id=', $result['redirect']);
		$this->assertEmpty($this->getItem($ids[1]));
		$this->assertNotEmpty($this->getItem($ids[0]));
	}

	// --- form

	public function testFormOfANewItem() {
		$output = $this->oc->request(static::ITEM_ROUTE)['output'];
		$this->assertMatchesRegularExpression('/name="name"/', $output);
		$this->assertMatchesRegularExpression('/name="status"\s+value="1"\s+checked/', $output);
	}

	public function testFormOfAnItem() {
		$item_id = $this->addItem('Existing', 0);
		$output = $this->oc->request(static::ITEM_ROUTE, array('item_id' => $item_id))['output'];
		$this->assertStringContainsString('value="Existing"', $output);
		$this->assertDoesNotMatchRegularExpression('/name="status"\s+value="1"\s+checked/', $output);
		$this->assertStringContainsString('Item Existing', $output);
	}

	public function testCreateItem() {
		$result = $this->oc->request(static::ITEM_ROUTE, array(), array('name' => 'Created', 'status' => '1'));
		$this->assertStringContainsString('route=extension/ka_extensions/ka_lib_test/items', $result['redirect']);
		$rows = $this->queryRows("SELECT * FROM `" . DB_PREFIX . static::ITEM_TABLE . "`");
		$this->assertCount(1, $rows);
		$this->assertSame('Created', $rows[0]['name']);
		$this->assertSame(array('S'), array_column($this->topMessages(), 'type'));
	}

	/**
	 * A new record form takes the primary key from POST when the URL has none: a posted item_id
	 * updates that record (or nothing) instead of creating a new one, and success is reported.
	 *
	 * @group known-bug
	 */
	public function testPostedPrimaryKeyIsIgnored() {
		$other_id = $this->addItem('Other');
		$this->oc->request(static::ITEM_ROUTE, array(), array('name' => 'Created', 'status' => '1', 'item_id' => $other_id));
		$this->assertSame('Other', $this->getItem($other_id)['name']);
		$this->assertCount(2, $this->queryRows("SELECT * FROM `" . DB_PREFIX . static::ITEM_TABLE . "`"));
	}

	public function testSaveAndStay() {
		$item_id = $this->addItem('Old');
		$result = $this->oc->request(static::ITEM_ROUTE, array('item_id' => $item_id, 'save_and_stay' => '1'), array('name' => 'New', 'status' => '1'));
		$this->assertStringContainsString('route=extension/ka_extensions/ka_lib_test/item&', $result['redirect']);
		$this->assertStringContainsString('item_id=' . $item_id, $result['redirect']);
		$this->assertStringNotContainsString('save_and_stay', $result['redirect']);
		$this->assertSame('New', $this->getItem($item_id)['name']);
	}

	public function testInvalidItemIsNotSaved() {
		$item_id = $this->addItem('Old');
		$result = $this->oc->request(static::ITEM_ROUTE, array('item_id' => $item_id), array('name' => ''));
		$this->assertSame('', $result['redirect']);
		$this->assertSame('Old', $this->getItem($item_id)['name']);
		$this->assertStringContainsString('<div class="text-danger">Field is empty</div>', $result['output']);
	}

	/**
	 * ControllerForm saves the raw POST: an unchecked checkbox is not posted and the old value stays.
	 *
	 * @group known-bug
	 */
	public function testUncheckedCheckboxIsSaved() {
		$item_id = $this->addItem('Old', 1);
		$this->oc->request(static::ITEM_ROUTE, array('item_id' => $item_id), array('name' => 'New'));
		$this->assertSame('New', $this->getItem($item_id)['name']);
		$this->assertSame('0', $this->getItem($item_id)['status']);
	}
}
