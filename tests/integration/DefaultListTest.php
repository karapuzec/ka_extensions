<?php

namespace KaExtensionsTests\Integration;

/**
 * The default list page (pages/list.twig) and the default form of a table with field metadata:
 * fixture pages dlist, dlist_custom, ditem without a model file and without templates of their own.
 *
 * @group lists
 * @group forms
 */
class DefaultListTest extends LibraryTestCase {

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

	protected function page($get = array(), $route = self::DLIST_ROUTE) {
		$this->oc->get('session')->data['ka_top_messages'] = null;
		$result = $this->oc->request($route, $get);
		return $result['output'];
	}

	protected function names($output) {
		preg_match_all('/<td class="text-left" data-column="name">\s*<a href="[^"]*">([^<]*)<\/a>/', $output, $matches);
		return $matches[1];
	}

	public function testColumnsAndCells() {
		$id = $this->addItem('First <b>', 0, '2026-02-03 10:00:00');
		$output = $this->page();
		$this->assertStringContainsString('id="ka-list-table"', $output);
		foreach (array('name', 'status', 'date_added') as $code) {
			$this->assertStringContainsString('data-column="' . $code . '"', $output);
		}
		$this->assertStringNotContainsString('data-column="item_id"', $output);
		// escaped value, link to the form, formats 'enabled' and 'date'
		$this->assertSame(array('First &lt;b&gt;'), $this->names($output));
		$this->assertMatchesRegularExpression('/<a href="[^"]*ka_lib_test\/ditem[^"]*item_id=' . $id . '[^"]*">First/', $output);
		$this->assertMatchesRegularExpression('/data-column="status">\s*Disabled\s*</', $output);
		$this->assertMatchesRegularExpression('/data-column="date_added">\s*03\/02\/2026\s*</', $output);
		// checkbox, row delete posted, the add button
		$this->assertStringContainsString('name="selected[]" value="' . $id . '"', $output);
		$this->assertMatchesRegularExpression('/<a href="[^"]*dlist\/deleteRecord[^"]*item_id=' . $id . '[^"]*" data-ka-post="1"/', $output);
		$this->assertMatchesRegularExpression('/<a href="[^"]*ka_lib_test\/ditem[^"]*" data-toggle="tooltip" title="[^"]*" class="btn l-button-add/', $output);
		$this->assertStringContainsString('formaction="', $output);
	}

	public function testSortLinksOnlyForSortFields() {
		$this->addItem('B');
		$output = $this->page();
		$this->assertMatchesRegularExpression('/<a\s+href="[^"]*sort=t\.name[^"]*"\s*>Name/', $output);
		$this->assertMatchesRegularExpression('/sort=t\.date_added/', $output);
		$this->assertStringNotContainsString('sort=t.status', $output);
	}

	public function testSortByWhitelist() {
		$this->addItem('A', 1, '2026-01-03 00:00:00');
		$this->addItem('C', 1, '2026-01-01 00:00:00');
		$this->addItem('B', 1, '2026-01-02 00:00:00');
		$this->assertSame(array('C', 'B', 'A'), $this->names($this->page(array('sort' => 't.name', 'order' => 'DESC'))));
		$this->assertSame(array('C', 'B', 'A'), $this->names($this->page(array('sort' => 'date_added'))));
		// the current sort column is marked
		$this->assertMatchesRegularExpression('/sort=t\.name[^"]*"\s*class="desc"/', $this->page(array('sort' => 't.name', 'order' => 'DESC')));
		// a field without 'sort' is not in the whitelist: the default order
		$this->assertSame(array('A', 'C', 'B'), $this->names($this->page(array('sort' => 'status'))));
		$this->assertSame(array('A', 'C', 'B'), $this->names($this->page(array('sort' => 't.name; DROP'))));
	}

	public function testFilters() {
		$this->addItem('Apple', 1, '2026-01-05 12:00:00');
		$this->addItem('Banana', 0, '2026-01-10 00:00:00');
		$this->addItem('Pineapple', 0, '2026-01-20 00:00:00');
		$this->addItem('a_b%', 1, '2026-01-20 00:00:00');

		$output = $this->page();
		$this->assertStringContainsString('id="ka-list-filter"', $output);
		foreach (array('filter_name', 'filter_status', 'filter_date_added_from', 'filter_date_added_to') as $name) {
			$this->assertStringContainsString('name="' . $name . '"', $output);
		}

		$this->assertSame(array('Apple', 'Pineapple'), $this->names($this->page(array('filter_name' => 'apple'))));
		// LIKE wildcards are escaped
		$this->assertSame(array('a_b%'), $this->names($this->page(array('filter_name' => '_b%'))));
		// the status 0 is a value
		$this->assertSame(array('Banana', 'Pineapple'), $this->names($this->page(array('filter_status' => '0'))));
		$this->assertSame(array('Apple', 'Banana', 'Pineapple', 'a_b%'), $this->names($this->page(array('filter_status' => ''))));
		// the date range includes both days
		$this->assertSame(array('Apple', 'Banana'), $this->names($this->page(array('filter_date_added_from' => '2026-01-05', 'filter_date_added_to' => '2026-01-10'))));
		$this->assertSame(array('Pineapple', 'a_b%'), $this->names($this->page(array('filter_date_added_from' => '2026-01-11', 'filter_date_added_to' => 'wrong'))));
		// the filter value is kept in the field and in the sort links
		$output = $this->page(array('filter_name' => 'apple'));
		$this->assertMatchesRegularExpression('/name="filter_name"\s+value="apple"/', $output);
		$this->assertMatchesRegularExpression('/href="[^"]*filter_name=apple[^"]*sort=t\.name/', $output);
	}

	public function testInjectionInFilters() {
		$this->addItem('A');
		$this->assertSame(array(), $this->names($this->page(array('filter_name' => "' OR '1'='1"))));
		$this->assertSame(array(), $this->names($this->page(array('filter_status' => "0' OR '1'='1"))));
		$this->assertSame(array('A'), $this->names($this->page(array('filter_name' => array('x')))));
	}

	public function testBulkDeleteByPost() {
		$a = $this->addItem('A');
		$b = $this->addItem('B');
		$c = $this->addItem('C');
		$result = $this->oc->request(static::DLIST_ROUTE . '/delete', array(), array('selected' => array($a, $c)));
		$this->assertStringContainsString('route=extension/ka_extensions/ka_lib_test/dlist', $result['redirect']);
		$this->assertEmpty($this->getItem($a));
		$this->assertNotEmpty($this->getItem($b));
		$this->assertEmpty($this->getItem($c));
	}

	public function testPagination() {
		for ($i = 1; $i <= 12; $i++) {
			$this->addItem(sprintf('N%02d', $i));
		}
		$output = $this->page(array('sort' => 'name', 'page' => '2'));
		$this->assertSame(array('N11', 'N12'), $this->names($output));
		$this->assertStringContainsString('Showing 11 to 12 of 12 (2 Pages)', $output);
	}

	public function testOnlyTheFilterTemplateIsReplaced() {
		$this->addItem('A');
		$output = $this->page(array(), static::DLIST_CUSTOM_ROUTE);
		$this->assertStringContainsString('<div id="custom-filter">Custom filter 4</div>', $output);
		$this->assertStringNotContainsString('id="ka-list-filter"', $output);
		$this->assertStringContainsString('id="ka-list-table"', $output);
		$this->assertSame(array('A'), $this->names($output));
	}

	public function testOldListPageIsUnchanged() {
		$this->addItem('Old');
		$output = $this->oc->request(static::ITEMS_ROUTE)['output'];
		$this->assertStringContainsString('id="ka-lib-test-list"', $output);
		$this->assertStringNotContainsString('id="ka-list-table"', $output);
	}

	public function testFormFieldsFromMetadata() {
		$id = $this->addItem('Edit me', 0);
		$output = $this->oc->request(static::DITEM_ROUTE, array('item_id' => $id))['output'];
		$this->assertMatchesRegularExpression('/name="name"\s+value="Edit me"/', $output);
		$this->assertStringContainsString('name="status"', $output);
		// 'form' => false
		$this->assertStringNotContainsString('name="date_added"', $output);

		$result = $this->oc->request(static::DITEM_ROUTE, array('item_id' => $id), array('name' => 'Saved', 'status' => '1'));
		$this->assertStringContainsString('route=extension/ka_extensions/ka_lib_test/dlist', $result['redirect']);
		$item = $this->getItem($id);
		$this->assertSame('Saved', $item['name']);
		$this->assertSame('1', (string)$item['status']);
	}
}
