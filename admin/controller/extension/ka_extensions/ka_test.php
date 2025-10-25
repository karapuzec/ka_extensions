<?php

namespace extension\ka_extensions;

class ControllerKaTest extends \KaInstaller {

	protected $extension_version = '1.0.0.1';
	protected $min_store_version = '3.0.0.0';
	protected $max_store_version = '3.0.3.9';
	protected $min_ka_extensions_version = '4.1.0.6';
	protected $max_ka_extensions_version = '4.1.1.9';
	
	protected $tables;

	//temporary variables
	protected $error;
	
	public function getTitle() {
		$str = str_replace('{{version}}', $this->extension_version, $this->language->get('full_extension_name'));
		return $str;
	}

	
	protected function onLoad() {

		$this->load->model('setting/setting');

 		$this->tables = array(
		);

		return true;
	}

	
	public function index() {

		$heading_title = $this->getTitle();
		$this->document->setTitle($heading_title);

		// handle autoinstall actions
		//
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			if (!empty($this->request->post)) {
				$this->model_setting_setting->editSetting('ka_test', $this->request->post);
			}
			
			$this->addTopMessage($this->language->get('Settings have been stored sucessfully.'));
			
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=ka_extensions', true));
			
		} elseif ($this->request->server['REQUEST_METHOD'] == 'POST') {
		
			$this->data = $this->request->post;

		} else {
		
		}

		$this->data['heading_title']     = $heading_title;
		$this->data['extension_version'] = $this->extension_version;
		$this->data['error']             = $this->error;
		
 		if (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}

		$this->data['breadcrumbs'] = array();
   		$this->data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], true),
		);

  		$this->data['breadcrumbs'][] = array(
	 		'text'      => $this->language->get('Ka Extensions'),
			'href'      => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true),
 		);
		
 		$this->data['breadcrumbs'][] = array(
	 		'text'      => $heading_title,
 		);
		
		$this->data['action'] = $this->url->link('extension/ka_extensions/ka_test', 'user_token=' . $this->session->data['user_token'] . '&type=ka_extensions', true);
		$this->data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=ka_extensions', true);
		
		$this->template = 'extension/ka_extensions/ka_test/settings';
		$this->children = array(
			'common/header',
			'common/column_left',
			'common/footer'
		);
		
		$this->setOutput();
	}

		
	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/ka_extensions/ka_test')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		
		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}
	
	public function install() {

		if (!parent::install()) {
			return false;
		}

		// import default settings to config
		//
		$settings = array(
		);
		$this->model_setting_setting->editSetting('ka_test', $settings);

		return true;
	}
	
	
	public function uninstall() {
		return true;
	}
}

class_alias(__NAMESPACE__ . '\ControllerKaTest', 'ControllerExtensionKaExtensionsKaTest');