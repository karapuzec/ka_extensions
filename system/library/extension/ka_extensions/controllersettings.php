<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)

*/
namespace extension\ka_extensions;

/**
	A base class for a module settings page. It incapsulates all required fields and methods.
	
	@package Controller
*/
abstract class ControllerSettings extends ControllerInstaller {

	use TraitControllerForm;

	/*
		Template of the settings page. A module can set its own template here.
	*/
	protected $settings_template = 'extension/ka_extensions/common/pages/settings';

	protected function onLoad() {
		parent::onLoad();

		$this->load->language('extension/ka_extensions/ka_extensions/settings');
		$this->load->language('extension/' . $this->vendor . '/' . $this->ext_code);
	}

	
	public function index() {

		$heading_title = $this->getTitle();
		$this->document->setTitle($heading_title);

		$fields = $this->getFields();
		
		// get original field values
		$old_data = array();
		foreach ($fields as $k => $v) {
			$old_data[$v['code']] = $this->config->get($v['code']);
		}		
		
		// handle autoinstall actions
		//
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$fields = $this->getFieldsWithData($fields, $old_data, $this->request->post);
			$values = $this->getFieldValues($fields);
			if ($this->vendor == 'ka_extensions') {
				$this->model_setting_setting->editSetting($this->ext_code, $values);
			} else {
				$this->model_setting_setting->editSetting($this->vendor . '_' . $this->ext_code, $values);
			}
			$this->addSettingsMessage($this->language->get('Settings have been stored sucessfully.'), 'S');
			$params = [
				'type' => $this-> vendor,
			];
			$this->response->redirect($this->url->linka('marketplace/extension', $params));
			
		} elseif ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!empty($this->errors)) {
				$this->addSettingsMessage($this->language->get('error_warning'), 'E');
			}
			$fields = $this->getFieldsWithData($fields, $old_data, $this->request->post, $this->errors);
		} else {
			$fields = $this->getFieldsWithData($fields, $old_data);
		}
		
		$this->data['fields'] = $fields;
		
		$this->data['heading_title']   = $heading_title;

		$this->data['extension_version'] = $this->extension_version;
		// the template shows the version and author line for vendor 'ka_extensions'
		$this->data['vendor'] = $this->vendor;
		
		$this->data['breadcrumbs'] = array();
   		$this->data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->url->linka('common/dashboard'),
			'separator' => false
		);

  		$this->data['breadcrumbs'][] = array(
	 		'text'      => $this->language->get('Ka Extensions'),
			'href'      => $this->url->linka('marketplace/extension', ['type' => 'ka_extensions', 'vendor' => $this->vendor]),
 		);
		
 		$this->data['breadcrumbs'][] = array(
	 		'text'      => $heading_title,
 		);

		$params = [
			'type' => $this->vendor,
		];
		
		$this->data['action_save'] = $this->url->linka('extension/' . $this->vendor . '/' . $this->ext_code);
		$this->data['action_cancel'] = $this->url->linka('marketplace/extension', $params);

		$this->showPage($this->settings_template);
	}


	/*
		Adds a top message unless the same message has been added already (modules adding the messages
		themselves do not get them twice).
	*/
	protected function addSettingsMessage($text, $type) {

		if (!empty($this->session->data['ka_top_messages'])) {
			foreach ($this->session->data['ka_top_messages'] as $message) {
				if (isset($message['content']) && $message['content'] == $text) {
					return;
				}
			}
		}

		$this->addTopMessage($text, $type);
	}

		
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/' . $this->vendor . '/' . $this->ext_code)) {
			$this->addTopMessage($this->language->get('error_permission'), 'E');
			return false;
		}

		$fields = $this->getFields();
		if (!$this->validateFields($fields, $this->request->post)) {
			return false;
		}

		return true;
	}
	

	public function install() {

		if (!parent::install()) {
			return false;
		} 

		$fields = $this->getFields();
		
		$default = array();
		foreach ($fields as $k => $v) {
			if (isset($v['default_value'])) {
				$default[$v['code']] = $v['default_value'];
			}
		}
		
		$settings = $this->model_setting_setting->getSetting($this->ext_code);
		
		$settings = array_merge($default, $settings);
		
		$this->model_setting_setting->editSetting($this->ext_code, $settings);
		
		return true;
 	}

		
	public function uninstall() {
		$this->model_setting_setting->deleteSetting($this->ext_code);
		return parent::uninstall();
	}
}