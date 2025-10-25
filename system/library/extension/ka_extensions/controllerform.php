<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	This is a controller class for a basic form page.
*/
namespace extension\ka_extensions;

abstract class ControllerForm extends ControllerPage {

	const PARENT_ROUTE  = ''; // example: 'extension/ka_extensions/ka_licenses/licenses';
	const PAGE_ROUTE    = ''; // example: 'extension/ka_extensions/ka_licenses/licenses_form';
	const PAGE_TEMPLATE = ''; // example: 'extension/ka_extensions/ka_licenses/licenses_form';

	// this field has to be initialized by a recordset class
	// example: $this->recordset = $this->load->kamodel('extension/ka_extensions/ka_licenses/licenses');
	//
	protected $recordset;
	
	use TraitControllerForm;
	
	protected function onLoad() {

		parent::onLoad();

		if (empty(static::PAGE_ROUTE)) {
			throw new \Exception("Page route is not defined in a child class");
		}
	
		if (!empty(static::PARENT_ROUTE)) {
			$this->load->language(static::PARENT_ROUTE);
		}
		
		$fields = $this->getFields();
		$this->fields = $this->initFields($fields);
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
  	
  		// save the data
  		//
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
		 	if ($this->validate()) {
		 		$data = $this->request->post;
		 		if (isset($this->request->get[$primary_field])) {
		 			$data[$primary_field] = $this->request->get[$primary_field];
		 		}

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
    	$this->document->setTitle($this->language->get('txt_form_page_title'));
		
    	if (!empty(static::PARENT_ROUTE)) {
	   		$this->addBreadcrumb($this->language->get('txt_list_page_title'),
	   			$this->url->link(static::PARENT_ROUTE, 
	   			$this->url_params->getUrl())
	   		);
	   	}
	   	
   		$this->addBreadcrumb($record['record_title']);
   		
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
}