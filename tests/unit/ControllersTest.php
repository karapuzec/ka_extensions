<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\ControllerList;
use extension\ka_extensions\ControllerForm;
use extension\ka_extensions\ExceptionData;

/*
	A recordset recording the calls of the controllers.
*/
class FakeRecordset {
	public $records = array();
	public $total = 0;
	public $calls = array();
	public $deleted = array();
	public $saved = array();
	public $save_result = 77;
	public $save_exception = null;

	public function getPrimaryField() {
		return 'item_id';
	}

	public function getRecordsTotal($params) {
		$this->calls[] = array('getRecordsTotal', $params);
		return $this->total;
	}

	public function getRecords($params) {
		$this->calls[] = array('getRecords', $params);
		return $this->records;
	}

	public function getRecord($id) {
		$this->calls[] = array('getRecord', $id);
		foreach ($this->records as $record) {
			if ($record['item_id'] == $id) {
				return $record + array('full' => true);
			}
		}
		return array();
	}

	public function fillRecord($record) {
		$record['record_title'] = isset($record['name']) ? 'Item ' . $record['name'] : '';
		return $record;
	}

	public function deleteRecord($id) {
		$this->deleted[] = $id;
	}

	public function saveRecord($data) {
		if ($this->save_exception) {
			throw $this->save_exception;
		}
		$this->saved[] = $data;
		return $this->save_result;
	}
}

class TestListController extends ControllerList {
	const PAGE_ROUTE = 'extension/ka_extensions/ka_test/items';
	const FORM_ROUTE = 'extension/ka_extensions/ka_test/item';

	public $enable_delete = false;
	public $complete_records = true;

	protected function getSortFields() {
		return array('name' => 't.name', 'date' => 't.date_added');
	}
}

class TestFormController extends ControllerForm {
	const PARENT_ROUTE = 'extension/ka_extensions/ka_test/items';
	const PAGE_ROUTE   = 'extension/ka_extensions/ka_test/item';

	protected function onLoad() {
		$this->recordset = $this->load->kamodel('extension/ka_extensions/ka_test/items');
		parent::onLoad();
	}

	protected function getFields() {
		return array(
			'name'   => array('type' => 'text', 'required' => true, 'title' => 'Name'),
			'status' => array('type' => 'checkbox', 'default_value' => 1),
			'groups' => array('type' => 'checkboxes'),
		);
	}
}

/**
 * ControllerList and ControllerForm with stub services: paging, sorting links, actions, deleting, saving.
 *
 * @group lists
 * @group forms
 */
class ControllersTest extends UnitTestCase {

	/** @var FakeRecordset */
	protected $rs;

	protected function setUp(): void {
		parent::setUp();
		$this->rs = new FakeRecordset();
		$this->registry->get('load')->setModel('extension/ka_extensions/ka_test/items', $this->rs);
		$this->registry->get('config')->set('config_limit_admin', 10);
	}

	protected function request($get = array(), $post = null) {
		$request = $this->registry->get('request');
		$request->get = $get;
		$request->post = is_null($post) ? array() : $post;
		$request->server['REQUEST_METHOD'] = is_null($post) ? 'GET' : 'POST';
	}

	protected function lastView() {
		$views = $this->registry->get('load')->views;
		return end($views);
	}

	protected function recordsParams() {
		foreach ($this->rs->calls as $call) {
			if ($call[0] == 'getRecords') {
				return $call[1];
			}
		}
		return null;
	}

	protected function topMessages() {
		$session = $this->registry->get('session');
		return isset($session->data['ka_top_messages']) ? $session->data['ka_top_messages'] : array();
	}

	protected function runList($get = array()) {
		$this->request($get);
		$controller = new TestListController($this->registry);
		$controller->index();
		return $this->lastView();
	}

	// --- list page

	/**
	 * @dataProvider pageProvider
	 */
	public function testPageFromTheUrl($page, $expected_page, $expected_start) {
		$this->runList(array('page' => $page));
		$params = $this->recordsParams();
		$this->assertSame($expected_page, $params['page']);
		$this->assertSame($expected_start, $params['start']);
		$this->assertSame(10, $params['limit']);
	}

	public function pageProvider() {
		return array(
			'first'        => array('1', 1, 0),
			'third'        => array('3', 3, 20),
			'negative'     => array('-5', 1, 0),
			'zero'         => array('0', 1, 0),
			'not a number' => array("2' OR 1=1", 2, 10),
			'text'         => array('abc', 1, 0),
		);
	}

	public function testDefaultParameters() {
		$this->runList();
		$params = $this->recordsParams();
		$this->assertSame(1, $params['page']);
		$this->assertSame('', $params['sort']);
		$this->assertSame('ASC', $params['order']);
	}

	public function testSortIsPassedToTheRecordset() {
		$this->runList(array('sort' => 't.name', 'order' => 'DESC', 'unknown' => 'x'));
		$params = $this->recordsParams();
		$this->assertSame('t.name', $params['sort']);
		$this->assertSame('DESC', $params['order']);
		$this->assertArrayNotHasKey('unknown', $params);
	}

	public function testRecordsAreCompletedAndGetActions() {
		$this->rs->records = array(array('item_id' => 5, 'name' => 'A'), array('item_id' => 6, 'name' => 'B'));
		$this->rs->total = 2;
		$view = $this->runList(array('page' => '2', 'sort' => 't.name'));

		$records = $view['data']['records'];
		$this->assertCount(2, $records);
		$this->assertTrue($records[0]['full']);
		$this->assertSame('Item A', $records[0]['record_title']);
		$this->assertSame(array('edit'), array_column($records[0]['actions'], 'type'));
		$this->assertSame('extension/ka_extensions/ka_test/item?sort=t.name&page=2&item_id=5', $records[0]['actions'][0]['href']);
	}

	public function testRecordsAreNotCompletedOnRequest() {
		$this->rs->records = array(array('item_id' => 5, 'name' => 'A'));
		$this->request();
		$controller = new TestListController($this->registry);
		$controller->complete_records = false;
		$controller->index();
		$this->assertSame(array('getRecordsTotal', 'getRecords'), array_column($this->rs->calls, 0));
		$this->assertArrayNotHasKey('full', $this->lastView()['data']['records'][0]);
	}

	public function testDeleteActionWhenEnabled() {
		$this->rs->records = array(array('item_id' => 5, 'name' => 'A'));
		$this->request();
		$controller = new TestListController($this->registry);
		$controller->enable_delete = true;
		$controller->index();
		$actions = $this->lastView()['data']['records'][0]['actions'];
		$this->assertSame(array('edit', 'delete'), array_column($actions, 'type'));
		$this->assertSame('extension/ka_extensions/ka_test/items/deleteRecord?item_id=5', $actions[1]['href']);
	}

	public function testSortLinks() {
		$data = $this->runList(array('sort' => 't.name', 'order' => 'ASC', 'page' => '2'))['data'];
		$this->assertSame('extension/ka_extensions/ka_test/items?sort=t.name&order=DESC&page=2', $data['sort_name']);
		$this->assertSame('extension/ka_extensions/ka_test/items?sort=t.date_added&order=ASC&page=2', $data['sort_date']);
	}

	public function testPaginationUrl() {
		$this->rs->total = 35;
		$data = $this->runList(array('page' => '2', 'sort' => 't.name'))['data'];
		$this->assertSame('pagination:35:2:10:extension/ka_extensions/ka_test/items?sort=t.name&page={page}', $data['pagination']);
	}

	public function testListTitleAndTemplate() {
		$this->registry->get('language')->set('txt_list_page_title', 'Items');
		$view = $this->runList();
		$this->assertSame('extension/ka_extensions/ka_test/items_list', $view['template']);
		$this->assertSame('Items', $this->registry->get('document')->getTitle());
		$this->assertSame('extension/ka_extensions/ka_test/item', $view['data']['action_add']);
		$this->assertSame('extension/ka_extensions/ka_test/items/delete', $view['data']['action_delete']);
	}

	// --- deleting

	protected function runDelete($method, $get, $post, $enable_delete) {
		$this->request($get, $post);
		$controller = new TestListController($this->registry);
		$controller->enable_delete = $enable_delete;
		try {
			$controller->{$method}();
			$this->fail('No redirect');
		} catch (\TestRedirectException $e) {
			return $e->url;
		}
	}

	public function testDeleteIsRefusedWhenDisabled() {
		$url = $this->runDelete('delete', array(), array('selected' => array(1, 2)), false);
		$this->assertSame(array(), $this->rs->deleted);
		$this->assertSame(array(array('type' => 'E', 'content' => 'error_permission')), $this->topMessages());
		$this->assertSame('extension/ka_extensions/ka_test/items', $url);
	}

	public function testDeleteSelected() {
		$url = $this->runDelete('delete', array('page' => '2'), array('selected' => array('1', '2')), true);
		$this->assertSame(array('1', '2'), $this->rs->deleted);
		$this->assertSame(array(array('type' => 'S', 'content' => 'txt_operation_successful')), $this->topMessages());
		$this->assertSame('extension/ka_extensions/ka_test/items?page=2', $url);
	}

	public function testDeleteWithoutSelection() {
		$this->runDelete('delete', array(), array(), true);
		$this->assertSame(array(), $this->rs->deleted);
		$this->assertSame(array(), $this->topMessages());
	}

	public function testDeleteRecordIsRefusedWhenDisabled() {
		$this->runDelete('deleteRecord', array('item_id' => '3'), array(), false);
		$this->assertSame(array(), $this->rs->deleted);
	}

	public function testDeleteRecord() {
		$url = $this->runDelete('deleteRecord', array('item_id' => '3', 'page' => '2'), array(), true);
		$this->assertSame(array('3'), $this->rs->deleted);
		$this->assertSame('extension/ka_extensions/ka_test/items?page=2', $url);
	}

	// --- form page

	protected function runForm($get, $post = null) {
		$this->request($get, $post);
		$controller = new TestFormController($this->registry);
		try {
			$controller->index();
		} catch (\TestRedirectException $e) {
			return array('redirect' => $e->url);
		}
		return $this->lastView();
	}

	public function testFormOfAnExistingRecord() {
		$this->rs->records = array(array('item_id' => 4, 'name' => 'A', 'status' => '0', 'groups' => array(2)));
		$view = $this->runForm(array('item_id' => '4'));
		$fields = $view['data']['fields'];
		$this->assertSame('A', $fields['name']['value']);
		$this->assertSame('0', $fields['status']['value']);
		$this->assertSame(array(2), $fields['groups']['value']);
		$this->assertSame('Item A', $this->registry->get('document')->getTitle());
		$this->assertSame('extension/ka_extensions/ka_test/item?item_id=4', $view['data']['action_save']);
		// the url parameters of the form (including the record id) are kept in the link back to the list
		$this->assertSame('extension/ka_extensions/ka_test/items?item_id=4', $view['data']['action_back']);
	}

	public function testFormOfANewRecordUsesDefaults() {
		$view = $this->runForm(array());
		$this->assertSame(1, $view['data']['fields']['status']['value']);
		$this->assertSame('', $view['data']['fields']['name']['value']);
		$this->assertSame('New', $view['data']['record']['record_title']);
	}

	public function testSaveRedirectsToTheList() {
		$result = $this->runForm(array('item_id' => '4'), array('name' => 'B', 'status' => '1'));
		$this->assertSame(array(array('name' => 'B', 'status' => '1', 'item_id' => '4')), $this->rs->saved);
		$this->assertSame('extension/ka_extensions/ka_test/items?item_id=4', $result['redirect']);
		$this->assertSame(array(array('type' => 'S', 'content' => 'txt_operation_successful')), $this->topMessages());
	}

	public function testSaveAndStay() {
		$result = $this->runForm(array('save_and_stay' => '1'), array('name' => 'B'));
		$this->assertSame('extension/ka_extensions/ka_test/item?item_id=77', $result['redirect']);
	}

	public function testInvalidPostIsNotSaved() {
		$this->registry->get('language')->set('error_field_is_empty', 'Field %s is empty');
		$this->rs->records = array(array('item_id' => 4, 'name' => 'A', 'status' => '1', 'groups' => array(2)));
		$view = $this->runForm(array('item_id' => '4'), array('name' => ''));
		$this->assertSame(array(), $this->rs->saved);
		$fields = $view['data']['fields'];
		$this->assertSame('', $fields['name']['value']);
		$this->assertSame('Field Name is empty', $fields['name']['error']);
		// the shown form has the posted state: unchecked boxes are unchecked
		$this->assertSame('', $fields['status']['value']);
		$this->assertSame(array(), $fields['groups']['value']);
	}

	public function testSaveErrorIsShown() {
		$this->rs->save_exception = new ExceptionData('Duplicate name');
		$view = $this->runForm(array(), array('name' => 'B'));
		$this->assertArrayHasKey('fields', $view['data']);
		$this->assertSame(array('E', 'E'), array_column($view['data']['top_messages'], 'type'));
		$this->assertSame(array('Duplicate name', 'txt_operation_failed'), array_column($view['data']['top_messages'], 'content'));
	}

	/**
	 * ControllerForm saves the raw POST: an unchecked checkbox and a 'checkboxes' field with all boxes
	 * unchecked are not in the saved data, so the old values stay in the record (the fix 5e83bff covers
	 * settings pages and the shown form only).
	 *
	 * @group known-bug
	 */
	public function testUncheckedBoxesAreSaved() {
		$this->runForm(array('item_id' => '4'), array('name' => 'B'));
		$this->assertSame('', $this->rs->saved[0]['status'] ?? null);
		$this->assertSame(array(), $this->rs->saved[0]['groups'] ?? null);
	}
}
