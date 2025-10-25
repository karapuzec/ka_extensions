<?php
/* 
 $Project: Ka Extensions $
 $Author: karapuz team <support@ka-station.com> $
 $Version: 4.1.1.22 $ ($Revision: 560 $) 
*/

class ControllerExtensionExtensionKaExtensions extends \extension\ka_extensions\ControllerPage {

	use \extension\ka_extensions\TraitControllerForm;

	protected $tables;
	protected $infolog;
	
	protected $vendor = 'ka_extensions';

	protected $kamodel_ka_extensions;
	
	protected function onLoad() {
	
		parent::onLoad();
	
		$this->infolog = new \Log('ka_extensions.log');
	
		$this->load->language('extension/extension/ka_extensions');
		
		if (defined('IS_KAMOD_SAFE_MODE')) {
			echo "The website is operating in kamod safe mode. It is not possible to access ka-extensions page under that mode.";
			die;

		} else if (!$this->load->kamodel('customer/customer')) {
			$modifications_link = $this->url->link('marketplace/modification', 'user_token=' . $this->session->data['user_token'], true);
			echo 'The modifications cache is not complete or empty. Refresh the modifications cache on the <a href="' . $modifications_link . '">Modifications</a> page. <br />If it does not help, 
			please install <a href="https://www.opencart.com/index.php?route=marketplace/extension/info&extension_id=31427">the latest ka-extensions library</a> from Opencart marketplace.';
			die;
		}
		$this->kamodel_ka_extensions = $this->load->kamodel('extension/ka_extensions');

		$this->load->model('setting/extension');
		$this->load->model('setting/setting');
		$this->load->model('setting/modification');

 		$this->tables = array(
 			'extension' => array(
 				'fields' => array(
 					'show_related' => array(
 						'type' => 'tinyint(1)',
 						'query' => "ALTER TABLE `" . DB_PREFIX . "extension` ADD `show_related` TINYINT(1) NOT NULL DEFAULT '0'"
 					),
 				),
 			),
 			'translation' => [
 				'fields' => [
 					'is_html' => [
 						'type'  => 'tinyint(1)',
 						'query' => "ALTER TABLE `" . DB_PREFIX . "translation` ADD `is_html` TINYINT(1) NOT NULL DEFAULT '0'"
 					]
 				]
 			]
		);
		
		$messages = array();
		if (!$this->model_extension_ka_extensions->checkDBCompatibility($this->tables, $messages)) {
			die('Sorry, the database is not compatible with Ka-Extensions.');
		}
		if (!$this->model_extension_ka_extensions->patchDB($this->tables, $messages)) {
			die('Sorry, the database cannot be patched for Ka-Extensions.');
		}
		
		if (isset($this->request->get['vendor'])) {
			if (preg_match("/^[0-9a-zA-Z_]*$/", $this->request->get['vendor'], $matches)) {
				$this->vendor = $this->request->get['vendor'];
			}
		}
	}
	

	public function index() {
		$this->getList();
	}
	
			
	protected function getList() {
		
		$this->updateInfoByDomain();

		if ($this->vendor == 'ka_extensions') {
			$heading_title = $this->language->get('heading_title');
		} else {
			$heading_title = $this->language->get('Vendor') . ': ' . $this->vendor;
		}
		$this->data['heading_title'] = $heading_title;
		
		$this->data['text_confirm']    = $this->language->get('text_confirm');

		$this->data['extension_version'] = \KaInstaller::$ka_extensions_version;
		
		$this->document->setTitle($this->data['heading_title']);
		
		$this->data['http_catalog'] = HTTP_CATALOG;
		$this->data['oc_version']   = VERSION;

		// get installed extensions registered in the database (table 'oc_extension')
		//
		$installed_extensions = $this->model_extension_ka_extensions->getKaInstalled($this->vendor);
		$installed_extension_codes = array_keys($installed_extensions);
		
		foreach ($installed_extensions as $key => $value) {
			if (!file_exists(DIR_APPLICATION . 'controller/extension/' . $this->vendor . '/' . $key . '.php')) {
				$this->model_setting_extension->uninstall($this->vendor, $key);
				unset($installed_extensions[$key]);
			}
		}
	
		// get a list of extensions uploaded to the store. Some of them can be inactive.
		//
		$this->data['extensions'] = array();
		$loaded_extensions = $this->model_extension_ka_extensions->getLoadedExtensions($this->vendor);
		
		if (!empty($loaded_extensions)) {
			foreach ($loaded_extensions as $extension_code) {
				
				$full_extension_code = $this->vendor . '/' . $extension_code;
			
				$this->load->language('extension/' . $full_extension_code);

				$class = 'ControllerExtension' . str_replace('_', '', $this->vendor . $extension_code);
				if (!class_exists($class)) {
					require_once(modification(DIR_APPLICATION . 'controller/extension/' . $full_extension_code . '.php'));
				}
				$class = new $class($this->registry);
				if (method_exists($class, 'getTitle')) {
					$heading_title = $class->getTitle();
				} else {
					$heading_title = $this->language->get('heading_title');
				}
				
				$modification = $this->model_setting_modification->getModificationByCode($full_extension_code);
				if (empty($modification)) {
					$modification = $this->model_setting_modification->getModificationByCode($extension_code);
				}
				
				$action = array();
				
				$ext = array(
					'name' => $heading_title,
				);
				if ($this->vendor == 'ka_extensions') {
					$ext['extension'] = $extension_code;
				} else {
					$ext['extension'] = $full_extension_code;
				}

				
				// get a link to an external extension page
				//
				if (!empty($modification['link'])) {
					$ext['ext_link'] = $modification['link'];
				}
				if (empty($ext['ext_link'])) {
					if (method_exists($class, 'getExtLink')) {
						$ext['ext_link'] = $class->getExtLink();
					}
				}
				if (method_exists($class, 'getDocsLink')) {
					$ext['docs_link'] = $class->getDocsLink();
				}
				
				if (!empty($installed_extensions[$extension_code])) {
					$ext['show_related'] = (!empty($installed_extensions[$extension_code]['show_related'])) ? true : false;
				}
				
				// get extension information from the extension class
				// mostly it calls methods like getVersion(), etc.
				//
				$extension_info = $this->model_extension_ka_extensions->getExtensionInfoByObject($class, $heading_title);
				$ext = array_merge($ext, $extension_info);
				$ext = array_merge($ext, $this->model_extension_ka_extensions->getExtensionInfo($extension_code));

				if (!empty($extension_info['is_lite'])) {
					$ext['is_registered'] = $this->model_extension_ka_extensions->isRegistered($extension_code . '_lite', $this->vendor);
				} else {
					$ext['is_registered'] = $this->model_extension_ka_extensions->isRegistered($extension_code, $this->vendor);
				}
				
				if (!empty($ext['expiry_date'])) {
					$ext['expiry_date'] = date($this->language->get('date_format_long'), strtotime($ext['expiry_date']));
				}
				
				$url_params = [
					'extension' => $extension_code,
					'vendor'    => $this->vendor,
				];
				
				if (!in_array($extension_code, $installed_extension_codes)) {
					$action['install'] = array(
						'text' => $this->language->get('button_install'),
						'href' => $this->url->linka('extension/extension/ka_extensions/install', $url_params)
					);
					
				} else {
					$ext['is_installed'] = true;
					$action['edit'] = array(
						'text' => $this->language->get('button_edit'),
						'href' => $this->url->linka('extension/' . $full_extension_code . '')
					);
					
					$action['uninstall'] = array(
						'text' => $this->language->get('button_uninstall'),
						'href' => $this->url->linka('extension/extension/ka_extensions/uninstall', $url_params)
					);
				}
				
				$ext['action'] = $action;

				$this->data['extensions'][] = $ext;
			}
		}

		$this->data['activate_action'] = $this->url->linka('extension/extension/ka_extensions/input_key');
		
		$this->data['breadcrumbs'] = array();
		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->linka('common/dashboard')
		);
		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->linka('extension/module')
		);

		$this->data['ka_station_url'] = \KaGlobal::getKaStoreURL();

		$this->data['link_settings'] = $this->url->linka('extension/extension/ka_extensions/settings', 'type=ka_extensions');
		
		$this->data['vendor'] = $this->vendor;
		
		$this->data['user_token'] = $this->session->data['user_token'];
		$this->showPage('extension/extension/ka_extensions');
	}

	
	public function install() {
	
		if ($this->validateInstall($this->vendor)) {
			$success = $this->load->controller('extension/' . $this->vendor . '/' . $this->request->get['extension'] . '/install');
			if ($success) {
				$this->model_setting_extension->install($this->vendor, $this->request->get['extension']);

				$this->load->model('user/user_group');
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/' . $this->vendor . '/' . $this->request->get['extension']);
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/' . $this->vendor . '/' . $this->request->get['extension']);
				
				$this->addTopMessage($this->language->get('installation_successful'));
			} else {
				$this->addTopMessage($this->language->get("installation_failed"), 'E');
			}
		} else {
			if (!empty($this->errors)) {
				$this->addTopMessage($this->errors, 'E');
			}
		}

		$this->children = array();
		$this->getList();			
	}

	
	public function uninstall() {

		if ($this->validateUninstall()) {
			$this->model_setting_extension->uninstall($this->vendor, $this->request->get['extension']);
			
			$success = $this->load->controller('extension/' . $this->vendor . '/' . $this->request->get['extension'] . '/uninstall');
			if ($success) {
				$this->addTopMessage($this->language->get('uninstallation_successful'));
			} else {
				$this->addTopMessage($this->language->get('uninstallation_failed'), 'E');
			}
		} else {
			$this->addTopMessage($this->errors, 'E');
		}
		
		$this->children = array();
		$this->getList();			
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/extension/ka_extensions')) {
			$this->addTopMessage($this->language->get('error_permission'));
			return false;
		}
		
		return true;
	}	
	
		
	/*
		Check if the user has permission to uninstall extension.
		
		Checks if there are installed modules depending on the module the user tries to uninstall.
	*/
	protected function validateInstall($vendor) {
	
		if (!$this->validate()) {
			return false;
		}

		$required = $this->kamodel_ka_extensions->getRequiredModules($this->request->get['extension'], $vendor);
		
		if (!empty($required)) {
			foreach ($required as $module) {
				$codes = explode('/', $module);
				if (!empty($codes[1])) {
					$codes[0] = $codes[1];
				}

				if (!\KaGlobal::isKaInstalled($codes[0])) {
					$this->errors = str_replace(
						'%module%',
						$module,
						$this->language->get('error_module_required')
					);

					return false;
				}
			}
		}
		
		return true;
	}
	

	/*
		Check if the user has permission to uninstall extension.
		
		Checks if there are installed modules depending on the module the user tries to uninstall.
	*/
	protected function validateUninstall() {
		if (!$this->validate()) {
			return false;
		}

		$dependent = $this->kamodel_ka_extensions->getDependentModules($this->request->get['extension']);
		if (!empty($dependent)) {
			foreach ($dependent as $module) {
				$codes = explode('/', $module['code']);
				if (!empty($codes[1])) {
					$codes[0] = $codes[1];
				}
				
				if (\KaGlobal::isKaInstalled($codes[0])) {
					$this->error = str_replace(
						'%module%',
						$module['name'],
						$this->language->get('error_module_dependence')
					);
					return false;
				}
			}
		}
		
		return true;
	}
	
	/*
		This function shows a 'License Registration' dialog
	*/	
	public function inputKey() {	
	
		$this->data['user_token'] = $this->session->data['user_token'];
		$this->data['extension'] = $this->request->get['extension']; // full extension code
	
		$this->template = 'extension/ka_extensions/ka_extensions/input_key';
		$this->response->setOutput($this->render());
	}
	

	/*
		This function processes input of 'Extension Registration' dialog
	*/
	public function activateKey() {

		$json = array();
	
		$key       = $this->request->post['license_key'];
		$extension = $this->request->post['extension']; // full extension code
		
		if ($this->model_extension_ka_extensions->registerKey($key, $extension)) {
			$json['redirect'] = $this->url->link('marketplace/extension', '', true) . '&type=ka_extensions&user_token=' . $this->session->data['user_token'];
			$this->addTopMessage('The license key was validated successfully.');
			$this->response->addHeader('Content-Type: application/json');	
			$this->response->setOutput(json_encode($json));
			return;
		}

		$json['error'] = $this->model_extension_ka_extensions->getLastError();
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	/*	
		Retrieve all extension information by domain.
		
		At this time it is supposed to return an array of registered extensions only. But maybe
		it will change in the future.
		
	*/
	protected function getInfoByDomain() {
	
		$kacurl = new \KaCurl();
		
		$request_url = \KaGlobal::getKaStoreURL() . "?route=extension/domain_info";
		
		$extensions = $this->kamodel_ka_extensions->getLoadedExtensions($this->vendor);
		
		$data = array(
			'url'        => HTTP_CATALOG,
			'version'    => 3,
			'vendor'     => $this->vendor,
			'extensions' => $extensions,
		);
		$result = $kacurl->request($request_url, $data);
		
		$info = var_export($request_url, true) . var_export($result, true) . var_export($data, true);
		
		// process the response from the remote server
		//
		if (empty($result)) {
			$this->lastError = 'A request to the license registration server failed with this error:'
				. $kacurl->getLastError();
				

			$this->infolog->write($this->lastError . ' extra:' . $info);
				
			return null;
		}
		
		$result = json_decode($result, true);
		if (!empty($result['error'])) {
			$this->lastError = $result['error'];
			return null;
		}

		if (empty($result['result']) || $result['result'] != 'ok') {
			$this->lastError = 'Server response does not contain a successful result.';
			return null;
		}
		
		if (!isset($result['extensions'])) {
			$this->lastError = 'Unknwon result format.';
			return null;
		}

		return $result['extensions'];
	}
	
	/*
		This function is called periodically to update the registration information of all extensions
		in the database. An updated info is retrieved from the ka-station server.
	*/
	protected function updateInfoByDomain() {
		$extensions = $this->getInfoByDomain();
		
		if (!$this->model_extension_ka_extensions->saveRegAll($extensions, $this->vendor)) {
			$this->infolog->write("saveReg failed." . $this->model_extension_ka_extensions->getLastError());
			return false;
		}
		
		return true;
	}
	
	
	public function updateRelated() {
	
		$related = $this->request->post;
		$this->model_extension_ka_extensions->updateRelated($related);
		
		$json = array();
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	
	protected function getFields() {
	
		$fields = array(
			'ka_extensions_modify_theme_files' => array(
				'type' => 'checkbox'
			),
			'ka_extensions_enable_data_lang' => array(
				'type' => 'checkbox'
			),
			'ka_extensions_mail_images_are_enclosed' => [
				'type'          => 'checkbox',
				'default_value' => false,
			],
			'safe_mode_code' => array(
				'type' => 'text',
			),
			'ka_extensions_log_emails' => [
				'type' => 'checkbox',
				'default_value' => false,
			],
		);
		
		$fields = $this->initFields($fields);
		
		return $fields;
	}
	
	
	protected function validateSettings() {

		if (!$this->validate()) {
			return false;
		}
	
		if (!empty($this->request->post['safe_mode_code'])) {
			if (!preg_match('/^[a-zA-Z0-9]*$/', $this->request->post['safe_mode_code'])) {
				$this->errors['safe_mode_code'] = $this->language->get("Only latin letters and numbers are allowed in the safe mode code");
			}		
		}
	
	
		if (!empty($this->errors)) {
			return false;
		}
	
		return true;
	}
	
	public function settings() {
	
		$this->load->language('extension/extension/ka_extensions');
		$this->load->language('extension/ka_extensions/common/settings');

		$fields = $this->getFields();
		
		// get original field values
		$values = array();
		foreach ($fields as $k => $v) {
			$values[$v['code']] = $this->config->get($v['code']);
		}
		$values['safe_mode_code'] = $this->kamodel_ka_extensions->getSafeModeCode();
		
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if ($this->validateSettings()) {
				
				$safe_mode_code = '';
				if (!empty($this->request->post['safe_mode_code'])) {
					$safe_mode_code = trim($this->request->post['safe_mode_code']);
				}
				$this->kamodel_ka_extensions->saveSafeModeCode($safe_mode_code);
				unset($this->request->post['safe_mode_code']);
			
				$this->model_setting_setting->editSetting('ka_extensions', $this->request->post);
				$this->addTopMessage($this->language->get("txt_operation_successful"));
				$this->response->redirect($this->url->linka('extension/extension/ka_extensions/settings', 'user_token=' . $this->session->data['user_token'] . '&type=ka_extensions', true));
			} else {
				$this->addTopMessage($this->language->get("txt_operation_failed"));
			}
			
			$fields = $this->getFieldsWithData($fields, $values, $this->request->post, $this->errors);
			
		} else {		
			$fields = $this->getFieldsWithData($fields, $values);
		}
	
		$this->addBreadcrumb('Ka Extensions', $this->url->linka('marketplace/extension', 'type=ka_extensions'));
		
		$this->data['vendor'] = $this->vendor;
		$this->data['fields'] = $fields;
		$this->data['extension_version'] = \extension\ka_extensions\ControllerInstaller::$ka_extensions_version;
		
		$this->data['action_save']   = $this->url->linka('extension/extension/ka_extensions/settings');
		$this->data['action_cancel'] = $this->url->linka('marketplace/extension', 'type=ka_extensions');
		
		$this->data['heading_title'] = $this->language->get('heading_settings_title');
		
		$this->showPage('extension/ka_extensions/common/pages/settings');
	}
}