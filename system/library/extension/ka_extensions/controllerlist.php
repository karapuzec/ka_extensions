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

	// true - the default list page of the library (pages/list.twig) built from the field metadata of the table
	//        (ADBTable::getListFields(), getFilterFields(), getSortFields()). false (default) - the template
	//        PAGE_ROUTE . '_list' of the module
	//
	protected $use_default_list = false;

	// columns of the default list, see getListColumns()
	protected $list_columns = array();

	const PAGE_ROUTE = ''; // = 'extension/ka_extensions/ka_licenses/licenses';
	const FORM_ROUTE = ''; // = 'extension/ka_extensions/ka_licenses/licenses';

	// the template of the default list page ($use_default_list = true), a module can set its own template, e.g.
	// one extending '@ka_common/pages/list.twig' with the blocks list_filter, list_table overridden
	const DEFAULT_LIST_TEMPLATE = 'extension/ka_extensions/common/pages/list';
	
	protected function onLoad() {

		if (empty(static::PAGE_ROUTE)) {
			throw new \Exception("Page route is not defined in a child class");
		}
	
		$this->load->language('extension/ka_extensions/common');

		foreach ($this->getLanguageRoutes() as $route) {
			$this->load->language($route);
		}
		$this->recordset = $this->load->kamodel(static::PAGE_ROUTE);
		
		parent::onLoad();
	}


	/*
		Returns the table object of the recordset (field metadata) or null.
	*/
	protected function getDBTable() {

		if (empty($this->recordset) || !method_exists($this->recordset, 'getTable')) {
			return null;
		}

		$tbl = $this->recordset->getTable();

		return $tbl;
	}


	protected function getFieldTitle($field) {

		if (!empty($field['title'])) {
			return $field['title'];
		}

		return $this->language->get('txt_title_' . $field['code']);
	}


	/*
		Returns the columns of the default list from the table metadata: code => [code, title, sort (code or ''),
		format, width, link].
	*/
	protected function getListColumns() {

		$tbl = $this->getDBTable();
		if (empty($tbl)) {
			return array();
		}

		$sort_fields = $this->getSortFields();

		$columns = array();
		foreach ($tbl->getListFields() as $code => $field) {
			$columns[$code] = array(
				'code'   => $code,
				'title'  => $this->getFieldTitle($field),
				'sort'   => isset($sort_fields[$code]) ? $code : '',
				'format' => isset($field['format']) ? $field['format'] : '',
				'width'  => isset($field['width']) ? $field['width'] : '',
				'link'   => isset($field['link']) ? $field['link'] : '',
				// value => text of the field options (ADBTable::getOptions()), the cell shows the text
				'options' => isset($field['options']) ? $tbl->getOptions($code) : array(),
			);
		}

		return $columns;
	}


	/*
		Returns the value of a list cell formatted by the column 'format'.
	*/
	protected function formatListValue($value, $column) {

		if (is_null($value) || !strlen((string)$value)) {
			return '';
		}

		if (!empty($column['options']) && isset($column['options'][$value])) {
			return $column['options'][$value];
		}

		if ($column['format'] == 'price') {
			return $this->currency->format($value, $this->config->get('config_currency'));

		} elseif ($column['format'] == 'date' || $column['format'] == 'datetime') {
			$time = strtotime($value);
			if (empty($time) || substr($value, 0, 4) == '0000') {
				return '';
			}
			$format = $this->language->get('date_format_short');
			if ($column['format'] == 'datetime') {
				$format .= ' ' . $this->language->get('time_format');
			}
			return date($format, $time);

		} elseif ($column['format'] == 'enabled') {
			return $this->language->get(!empty($value) ? 'text_enabled' : 'text_disabled');
		}

		return $value;
	}

	/*
		Returns language files of the page, they are loaded in onLoad() after the library common file.
		A module adds its own files, e.g. its common file:
			return array_merge(parent::getLanguageRoutes(), ['extension/ka_extensions/my_module/common']);
	*/
	protected function getLanguageRoutes() {
		return array(static::PAGE_ROUTE);
	}


	protected function getSortFields() {

		// the fields with the 'sort' metadata of the table
		//
		$tbl = $this->getDBTable();
		if (!empty($tbl) && $tbl->hasFieldMeta('sort')) {
			$alias = $this->recordset::TABLE_ALIAS;
			$fields = array();
			foreach ($tbl->getSortFields() as $code) {
				$fields[$code] = $alias . '.' . $code;
			}
			return $fields;
		}
	
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

		// filters of the table metadata
		//
		foreach (array_keys($this->getMetaFilterFields()) as $code) {
			$params[$code] = '';
		}
		
		return $params;
	}
	
	protected function validateDelete() {
		if (!$this->enable_delete) {
    		$this->addTopMessage($this->language->get('error_permission'), 'E');
			return false;
		}
			
		return true;
	}
	
	
	/*
		Returns the filter fields from the table metadata ('filter' of ADBTable::getFilterFields()).
		A date range gives two date fields 'filter_<field>_from' and 'filter_<field>_to'.
	*/
	protected function getMetaFilterFields() {

		$tbl = $this->getDBTable();
		if (empty($tbl)) {
			return array();
		}

		$fields = array();
		foreach ($tbl->getFilterFields() as $code => $field) {
			$title = $this->getFieldTitle($field);

			if ($field['filter'] == 'date_range') {
				$fields['filter_' . $code . '_from'] = array('type' => 'date', 'title' => sprintf($this->language->get('txt_filter_from'), $title));
				$fields['filter_' . $code . '_to']   = array('type' => 'date', 'title' => sprintf($this->language->get('txt_filter_to'), $title));

			} elseif ($field['filter'] == 'select') {
				$options = isset($field['options']) ? $tbl->getOptions($code) : array();
				if (empty($options)) {
					$options = array(1 => $this->language->get('text_enabled'), 0 => $this->language->get('text_disabled'));
				}
				$fields['filter_' . $code] = array(
					'type'            => 'select',
					'title'           => $title,
					'hide_first_line' => true,
					'options'         => array('' => '') + $options,
				);

			} else {
				$fields['filter_' . $code] = array('type' => 'text', 'title' => $title);
			}
		}

		return $fields;
	}


	/*
		Returns the filter fields of the page with the values from the url. Without $filter_fields
		(a child class passes its own) the filters of the table metadata are used.
	*/
	protected function getPageFilterFields($filter_fields = array()) {
	
		if (empty($filter_fields)) {
			$filter_fields = $this->getMetaFilterFields();
		}

		$fields = array();
	
		foreach ($filter_fields as $k => $v) {
			if (empty($v['code'])) {
				$v['code'] = $k;
			}
			
			// '0' is a value (e.g. store 0, status 0), an empty string is not
			if (isset($this->request->get[$v['code']])) {
				$value = $this->request->get[$v['code']];
				if (is_array($value) ? !empty($value) : strlen((string)$value)) {
					$v['value'] = $value;
				}
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
				'type'   => 'delete',
				'text'   => $this->language->get('button_delete'),
				// deleteRecord() accepts POST only: the link is posted by the macro button_row_delete()
				'method' => 'post',
				'href' => $this->url->link(static::PAGE_ROUTE . '/deleteRecord', 
					$this->url_params->getUrl([$primary_field => $record[$primary_field]])
				)
			);
		}
		
		$result['actions'] = $actions;			

		// cells of the default list
		//
		if (!empty($this->list_columns)) {
			$result['cells'] = array();
			foreach ($this->list_columns as $code => $column) {
				$result['cells'][$code] = $this->formatListValue(isset($record[$code]) ? $record[$code] : null, $column);
			}
		}
	
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
		if ($this->use_default_list) {
			$this->list_columns = $this->getListColumns();
			$this->data['list_columns'] = $this->list_columns;
		}
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

		// the current sort as a column code of getSortFields() (the url keeps the SQL value like 't.name')
		// for the macro sort_url(). Values set by a child class are kept
		//
		if (!isset($this->data['sort'])) {
			$sort_code = !empty($sort_fields) ? array_search($params['sort'], $sort_fields, true) : false;
			$this->data['sort'] = ($sort_code !== false) ? $sort_code : $params['sort'];
		}
		if (!isset($this->data['order'])) {
			$this->data['order'] = $params['order'];
		}
		
		// set title
		//
  		$this->document->setTitle($this->language->get('txt_list_page_title'));
		
		if ($this->use_default_list) {
			$this->data['enable_delete']   = $this->enable_delete;
			$this->data['primary_field']   = $primary_field;
			$this->data['action_filter']   = $this->url->linka_js(static::PAGE_ROUTE);
			$this->showPage(static::DEFAULT_LIST_TEMPLATE);
			return;
		}

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