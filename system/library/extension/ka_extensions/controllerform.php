<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	A controller class for a basic form page.

	@package Controller
*/
abstract class ControllerForm extends ControllerPage {

	const PARENT_ROUTE  = ''; // example: 'extension/ka_extensions/ka_licenses/licenses';
	const PAGE_ROUTE    = ''; // example: 'extension/ka_extensions/ka_licenses/licenses_form';
	const PAGE_TEMPLATE = ''; // example: 'extension/ka_extensions/ka_licenses/licenses_form';

	// this field has to be initialized by a recordset class
	// example: $this->recordset = $this->load->kamodel('extension/ka_extensions/ka_licenses/licenses');
	//
	protected $recordset;

	// an ADBTable class used without a model file: when the recordset is not set by a child class, a plain
	// ModelRecordset of the table is used. Without getFields() of a child class the form fields come from
	// the table metadata (ADBTable::getFormFields())
	const TABLE_CLASS = ''; // = '\\extension\\ka_extensions\\ka_seo\\dbtable\\KaSeoRedirect';
	
	use TraitControllerForm;
	
	protected function onLoad() {

		parent::onLoad();

		if (empty(static::PAGE_ROUTE)) {
			throw new \Exception("Page route is not defined in a child class");
		}
	
		foreach ($this->getLanguageRoutes() as $route) {
			$this->load->language($route);
		}
		
		if (empty($this->recordset) && !empty(static::TABLE_CLASS)) {
			$this->recordset = ModelRecordset::forTable($this->registry, static::TABLE_CLASS);
		}

		$fields = $this->getFields();
		if (empty($fields)) {
			$fields = $this->getTableFormFields();
		}
		$this->fields = $this->initFields($fields);
	}


	/*
		Returns the form fields of the table metadata of the recordset (used when getFields() returns nothing).
	*/
	protected function getTableFormFields() {

		if (empty($this->recordset) || !method_exists($this->recordset, 'getTable')) {
			return array();
		}

		$fields = $this->recordset->getTable()->getFormFields();

		return $fields;
	}

	
	/*
		Returns language files of the page, they are loaded in onLoad() after the library common file.
		The list page file (PARENT_ROUTE) by default. A module adds its own files, e.g. its common file:
			return array_merge(parent::getLanguageRoutes(), ['extension/ka_extensions/my_module/common']);
	*/
	protected function getLanguageRoutes() {
		return !empty(static::PARENT_ROUTE) ? array(static::PARENT_ROUTE) : array();
	}


	/*
		Returns true when the record of the url exists. A form of a missing record is not shown and
		not saved: the user is redirected to the list (see index()).
	*/
	protected function isRecordFound($record_id) {
		$record = $this->recordset->getRecord($record_id);
		return !empty($record);
	}


	protected function validate() {
	
		if (!$this->validateModify()) {
			return false;
		}

		if (!$this->validateFields($this->fields, $this->request->post)) {
			return false;
		}
	
		return true;
	}	
	
	
  	protected function validateModify() {
  	
	  	return true;
  	}	
	
  	
  	public function index() {
  	
  		$primary_field = $this->recordset->getPrimaryField();

  		// a deleted or wrong record
  		//
  		if (!empty($this->request->get[$primary_field]) && !$this->isRecordFound($this->request->get[$primary_field])) {
  			$this->addTopMessage($this->language->get('error_record_not_found'), 'E');
  			$route = !empty(static::PARENT_ROUTE) ? static::PARENT_ROUTE : static::PAGE_ROUTE;
  			$this->response->redirect($this->url->link($route, $this->url_params->getUrl([$primary_field => null])));
  			return;
  		}
  	
  		// save the data
  		//
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
		 	if ($this->validate()) {
		 		$data = $this->getPostedRecord($primary_field);

		 		$record_id = null;
		 		try {
		      		$record_id = $this->recordset->saveRecord($data);
		      	} catch (ExceptionData $e) {
		      		$this->addTopMessage($e->getMessage(), 'E');
		      	}

				if (!empty($record_id)) {
					$this->addTopMessage($this->language->get("txt_operation_successful"), 'S');
					if (!empty($this->request->get['save_and_stay'])) {
						
		    	  		$this->response->redirect($this->url->link(static::PAGE_ROUTE, $this->url_params->getUrl(
		    	  			[
		    	  				'save_and_stay' => null,
		    	  				$primary_field => $record_id
		    	  			]
		    	  		)));
		    	  	} else {
		    	  		$this->response->redirect($this->url->link(static::PARENT_ROUTE, $this->url_params->getUrl()));
		    	  	}
	    	  	} else {
		      		$this->addTopMessage($this->language->get('txt_operation_failed'), 'E');
	    	  	}
			}
		}
		
	  	$record = $this->getRecord();
	  	
		// get fields for template and fill in data to them
		//
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$fields = $this->getFieldsWithData($this->fields, $record, $this->request->post, $this->errors);
		} else {
			$fields = $this->getFieldsWithData($this->fields, $record);
		}
		
		$this->data['fields'] = $fields;
		
		$this->data['record'] = $record;
		
		// breacrumbs and title
		//
		if (!empty($record['record_title'])) {
	   		$this->document->setTitle($record['record_title']);
		} else {
	    	$this->document->setTitle($this->language->get('txt_form_page_title'));
	    }
		
    	if (!empty(static::PARENT_ROUTE)) {
	   		$this->addBreadcrumb($this->language->get('txt_list_page_title'),
	   			$this->url->link(static::PARENT_ROUTE, 
	   			$this->url_params->getUrl())
	   		);
	   	}
	   	
   		
   		// define action links
   		//
		$this->data['action_save'] = $this->url->link(static::PAGE_ROUTE, 
			$this->url_params->getUrl(), true
		);

		if (!empty(static::PARENT_ROUTE)) {
			$this->data['action_back'] = $this->url->link(static::PARENT_ROUTE, 
				$this->url_params->getUrl(), true
			);
		}

		if (!empty(static::PAGE_TEMPLATE)) {
			$this->showPage(static::PAGE_TEMPLATE);
		} else {
			$this->showPage('extension/ka_extensions/common/pages/form');
		}
  	}
  	
  	
  	/*
  		Returns the record data to save from the POST request.
  		
  		- unchecked boxes are not posted: a 'checkbox' field gets '0', a 'checkboxes' field an empty list
  		  (the old values were kept);
  		- the primary key is taken from the url only. A posted key is ignored, a form of a new record
  		  (no key in the url) adds a record and never updates another one.
  	*/
  	protected function getPostedRecord($primary_field) {
  	
 		$data = $this->request->post;
 		
 		if (!empty($this->fields)) {
	 		foreach ($this->fields as $k => $f) {
	 			$code = (!empty($f['code']) ? $f['code'] : $k);
	 			if (empty($f['type']) || isset($data[$code])) {
	 				continue;
	 			}
	 			if ($f['type'] == 'checkbox') {
	 				$data[$code] = '0';
	 			} elseif ($f['type'] == 'checkboxes') {
	 				$data[$code] = array();
	 			}
	 		}
	 	}
 		
 		unset($data[$primary_field]);
 		if (isset($this->request->get[$primary_field])) {
 			$data[$primary_field] = $this->request->get[$primary_field];
 		}
 		
 		return $data;
  	}
  	
  	
  	protected function getRecord() {
  	
  		// get the record data
  		//
  		$primary_field = $this->recordset->getPrimaryField();
  		
  		$record_title  = '';
  		$record        = array();
  		
  		if (!empty($this->request->get[$primary_field])) {
 			$record = $this->recordset->getRecord($this->request->get[$primary_field]);
 			$result = $this->recordset->fillRecord($record);
 			$record = array_merge($result, $record);
 			
 			if (!empty($result['record_title'])) {
		    	$record_title = $result['record_title'];
		    }
  		} else {
	  		$record_title = $this->language->get('New');
  		}
  		
  		$record['record_title'] = $record_title;
  	
  	  	return $record;
  	}  	
  	
  	
  	protected function getPageUrlParams() {
	
		$primary_field = $this->recordset->getPrimaryField();
		
		$params = [
			$primary_field => '',
		];
		
		return $params;
	}
}