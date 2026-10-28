<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)

*/
namespace extension\ka_extensions;

/**
	This class provides basic entity list functionality. It can be used for creating a new entity management
	in a store back-end.
	
	@package Controller
*/
abstract class ControllerList extends ControllerPage {

	protected $enable_delete = false;
	protected $recordset;

	// true (default) - every record returned by getRecords() is completed with getRecord() before fillRecord(),
	//                  one more query per row (short records of the page first, then the full record of each row)
	// false          - the records of getRecords() are used as they are, without extra queries. A child class can
	//                  set it when getRecordsQB() already selects all fields used by fillRecord() and the template
	//
	protected $complete_records = true;
	const PAGE_ROUTE = ''; // = 'extension/ka_extensions/ka_licenses/licenses';
	const FORM_ROUTE = ''; // = 'extension/ka_extensions/ka_licenses/licenses';
	
	protected function onLoad() {

		if (empty(static::PAGE_ROUTE)) {
			throw new \Exception("Page route is not defined in a child class");
		}
	
		$this->load->language('extension/ka_extensions/common');

		$this->load->language(static::PAGE_ROUTE);
		$this->recordset = $this->load->kamodel(static::PAGE_ROUTE);
		
		parent::onLoad();
	}

	protected function getSortFields() {
	
		$fields = array(
			'date_added'  => 't.date_added',
			'expiry_at'   => 't.expiry_at',
		);
		
		return $fields;
	}
	
	protected function getPageUrlParams() {
	
		$params = parent::getPageUrlParams();
	
		$params = array_merge($params, array(
			'sort'  => '',
			'order' => 'ASC',
			'page'  => 1,
		));
		
		return $params;
	}
	
	protected function validateDelete() {
		if (!$this->enable_delete) {
    		$this->addTopMessage($this->language->get('error_permission'), 'E');
			return false;
		}
			
		return true;
	}
	
	
	protected function getPageFilterFields($filter_fields = array()) {
	
		$fields = array();
	
		foreach ($filter_fields as $k => $v) {
			if (empty($v['code'])) {
				$v['code'] = $k;
			}
			
			if (!empty($this->request->get[$v['code']])) {
				$v['value'] = $this->request->get[$v['code']];
			}
			
			$fields[$k] = $v;
		}
		
		return $fields;
	}
	
	
	protected function fillRecord($record) {
	
		$primary_field = $this->recordset->getPrimaryField();

		$actions = array();
		
		$result = $this->recordset->fillRecord($record);

		if (!empty(static::FORM_ROUTE)) {
			$actions[] = array(
				'type' => 'edit',
				'text' => $this->language->get('button_edit'),
				'href' => $this->url->link(static::FORM_ROUTE, 
					$this->url_params->getUrl([$primary_field => $record[$primary_field]])
				)
			);
		}
		
		if ($this->enable_delete) {
			$actions[] = array(
				'type' => 'delete',
				'text' => $this->language->get('button_delete'),
				'href' => $this->url->link(static::PAGE_ROUTE . '/deleteRecord', 
					$this->url_params->getUrl([$primary_field => $record[$primary_field]])
				)
			);
		}
		
		$result['actions'] = $actions;			
	
		return $result;	
	}
	
	
	public function index() {

  		$primary_field = $this->recordset->getPrimaryField();

  		// we get all parameters here including default parameters (not passed in URL)
  		// parameters with NULL value should be ignored
  		//
		$params = $this->url_params->getParams();

		// the page comes from the URL, a negative or non-numeric value would give a negative start
		//
		$params['page'] = isset($params['page']) ? max(1, (int)$params['page']) : 1;

		$params['start'] = ($params['page'] - 1) * $this->config->get('config_limit_admin');
		$params['limit'] = $this->config->get('config_limit_admin');
		$this->data['params'] = $params;

		$this->data['records'] = [];
		if (!empty($this->recordset)) {
			$records_total = $this->recordset->getRecordsTotal($params);
			$records = $this->recordset->getRecords($params);
	    	foreach ($records as $record) {
				if ($this->complete_records) {
					$record = $this->recordset->getRecord($record[$primary_field]);
				}
				$result = $this->fillRecord($record);
				$this->data['records'][] = $result;
			}
		}		
		
		if (!empty(static::FORM_ROUTE)) {
			$this->data['action_add'] = $this->url->linka(static::FORM_ROUTE, $this->url_params->getUrlParams());
		}
		$this->data['action_delete'] = $this->url->linka(static::PAGE_ROUTE . '/delete', $this->url_params->getUrlParams());
		
		$this->pagination = new \extension\ka_extensions\Pagination(array(
			'total' => $records_total,
			'page'  => $params['page'],
			'limit' => $this->config->get('config_limit_admin'),
			'url'   => $this->url->link(static::PAGE_ROUTE, $this->url_params->getUrl(['page' => '{page}']))
		));
		
 		$this->data['filter_fields'] = $this->getPageFilterFields();

		if (!empty($this->session->data['user_token'])) {
			$this->data['user_token'] = $this->session->data['user_token'];
		}
		
		// define sort links
		//
		$sort_fields = $this->getSortFields();
		if (!empty($sort_fields)) {
			foreach ($sort_fields as $sfk => $sfv) {
				$this->data['sort_' . $sfk] = $this->url->linka(static::PAGE_ROUTE, $this->url_params->getUrlSortParams($sfv));
			}
		}
		
		// set title
		//
  		$this->document->setTitle($this->language->get('txt_list_page_title'));
		
		$this->showPage(static::PAGE_ROUTE . '_list');
	}
	
	
	public function delete() {

		// records are deleted by POST requests only
		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->addTopMessage($this->language->get('error_permission'), 'E');
			$this->response->redirect($this->url->linka(static::PAGE_ROUTE, $this->url_params->getUrlParams()));
			return;
		}

		if (!$this->validateDelete() || empty($this->request->post['selected'])) {
			$this->response->redirect($this->url->linka(static::PAGE_ROUTE, $this->url_params->getUrlParams()));
			return;
		}
		
		foreach ($this->request->post['selected'] as $record_id) {
			$this->recordset->deleteRecord($record_id);
		}
		
		$this->addTopMessage($this->language->get('txt_operation_successful'), 'S');
		$this->response->redirect($this->url->linka(static::PAGE_ROUTE, $this->url_params->getUrlParams()));
	}

	
	// the id is passed in the url (the 'delete' action of fillRecord()), the request must be POST
	//
	public function deleteRecord() {

		if ($this->request->server['REQUEST_METHOD'] != 'POST') {
			$this->addTopMessage($this->language->get('error_permission'), 'E');
			$this->response->redirect($this->url->linka(static::PAGE_ROUTE, $this->url_params->getUrlParams()));
			return;
		}

		if (!$this->validateDelete()) {
			$this->response->redirect($this->url->linka(static::PAGE_ROUTE, $this->url_params->getUrlParams()));
			return;
		}

		$primary_field = $this->recordset->getPrimaryField();

		if (isset($this->request->get[$primary_field])) {
			$record_id = $this->request->get[$primary_field];

			$this->recordset->deleteRecord($record_id);

			$this->addTopMessage($this->language->get('txt_operation_successful'), 'S');
		}
		
  		$this->response->redirect($this->url->link(static::PAGE_ROUTE, $this->url_params->getUrl(
  			[
  				$primary_field => null
  			]
  		)));
	}
}