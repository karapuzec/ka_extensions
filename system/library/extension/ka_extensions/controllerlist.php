<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	This is a controller class for a basic module settings page.
*/
namespace extension\ka_extensions;

abstract class ControllerList extends ControllerPage {

	protected $enable_delete = false;
	protected $recordset;
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
		$this->addTopMessage($this->language->get('error_permission'));
		return false;
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

		if (!empty(static::PAGE_ROUTE)) {
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
				'href' => $this->url->link(static::PAGE_ROUTE . '/delete', 
					$this->url_params->getUrl([$primary_field => $record[$primary_field]])
				)
			);
		}
		
		$result['actions'] = $actions;			
	
		return $result;	
	}
	
	
	public function index() {
	
		$params = $this->url_params->getParams();

		$params['start'] = ($params['page'] - 1) * $this->config->get('config_limit_admin');
		$params['limit'] = $this->config->get('config_limit_admin');
		$this->data['params'] = $params;

		$this->data['records'] = [];
		if (!empty($this->recordset)) {
			$records_total = $this->recordset->getRecordsTotal($params);
			$records = $this->recordset->getRecords($params);
	    	foreach ($records as $record) {
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
		
		// breacrumbs and title
		//
  		$this->document->setTitle($this->language->get('txt_list_page_title'));
   		$this->addBreadcrumb($this->language->get('txt_list_page_title'));
		
		$this->showPage(static::PAGE_ROUTE . '_list');
	}
	
	
	public function delete() {

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

	
	public function deleteRecord() {

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