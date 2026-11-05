<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.25 $ ($Revision: 640 $)
*/

use \extension\ka_extensions\Curl;

/**
	@internal
*/
class ModelExtensionKaExtensions extends \extension\ka_extensions\Model {
	protected $settings;
	protected $kacurl;

	// the API of the license module (ka_licenses) on the license server
	const LICENSE_API_ROUTE = 'extension/ka_extensions/ka_licenses/api/';

	// the version of the API
	protected $license_api_version = 1;

	
	protected function onLoad() {

		$this->load->model('setting/setting');
		$this->settings = $this->model_setting_setting->getSetting('ka_extensions');
		
		$this->kacurl = new Curl();
	}
	
	/*
		Compatible db may be fully patched or not patched at all. Partial changes are
		treated as a corrupted db.

		The method extends tables information with an 'exists' flag for existing elements.
		
		Returns
			true  - db is compatible
			false - db is not compatible

	*/
	public function checkDBCompatibility(&$tables, &$messages) {
		$messages = array();

		if (empty($tables)) {
			return true;
		}

		foreach ($tables as $tk => $tv) {

			$tbl = DB_PREFIX . $tk;
			$res = $this->db->ka_safeQuery("SHOW TABLES LIKE '$tbl'");

			if (!empty($res->rows)) {
				$tables[$tk]['exists'] = true;
			} else {
				continue;
			}

			$fields = $this->db->ka_safeQuery("DESCRIBE `$tbl`");
			if (empty($fields->rows)) {
				$messages[] = "Table '$tbl' exists in the database but it is empty.";
				return false;
			}

			// check fields 

			$db_fields = array();
			foreach ($fields->rows as $v) {
				$db_fields[$v['Field']] = array(
					'type'  => $v['Type']
				);
			}

			foreach ($tv['fields'] as $fk => $field) {
			
				if (empty($db_fields[$fk])) {
					continue;
				}

				// if the field is found we validate its type

				$db_field = $db_fields[$fk];
				
/* we disable type checking for now because integer types do not have numbers anymore
				if ($field['type'] != $db_field['type']) {
					if (empty($field['query_change'])) {
						$messages[] = "Field type '$db_field[type]' for '$fk' in the table '$tbl' does not match the required field type '$field[type]'.";
						return false;
					} else {
						$tables[$tk]['fields'][$fk]['exists_different'] = true;
					}
				} else {
					$tables[$tk]['fields'][$fk]['exists'] = true;
				}
*/				
				$tables[$tk]['fields'][$fk]['exists'] = true;
			}

			// check indexes
			/*
				We do not compare index fields yet, just ensure that the index with the appropriate
				name exists for the table.
			*/
			if (!empty($tv['indexes'])) {

				$rec = $this->db->ka_safeQuery("SHOW INDEXES FROM `$tbl`");
				$db_indexes = array();
				foreach ($rec->rows as $v) {
					$db_indexes[$v['Key_name']]['columns'][] = $v['Column_name'];
				}

				foreach ($tv['indexes'] as $ik => $index) {
					if (!empty($db_indexes[$ik])) {
						$tables[$tk]['indexes'][$ik]['exists'] = true;
					}
				}
			}
		}

		return true;
	}

			
	public function patchDB($tables, &$messages) {
		$messages = array();
		
		if (empty($tables)) {
			return true;
		}

		$this->db->query("SET sql_mode = ''");
		
		foreach ($tables as $tk => $tv) {
			if (empty($tv['exists'])) {
				$this->db->ka_safeQuery($tv['query']);
				continue;
			}

			if (!empty($tv['fields'])) {
				foreach ($tv['fields'] as $fk => $fv) {
					if (empty($fv['exists'])) {
					
						if (!empty($fv['exists_different'])) {

							if (!empty($fv['query_change'])) {
								$this->db->ka_safeQuery($fv['query_change']);
							} else {
								$messages[] = "Installation error. The field with a different type cannot be changed: " . $tk . "." . $fk;
								return false;
							}
							continue;
							
						} else if (!empty($fv['query'])) {
							$this->db->ka_safeQuery($fv['query']);
							continue;
						}
						
						$messages[] = "Installation error. Cannot create '$tk.$fk' field.";
						return false;
					}
				}
			}

			if (!empty($tv['indexes'])) {
				foreach ($tv['indexes'] as $ik => $iv) {
					if (empty($iv['exists']) && !empty($iv['query'])) {
						$this->db->ka_safeQuery($iv['query']);
					}
				}
			}
		}
	
		return true;
	}
	
	
	public function getExtensionInfo($extension) {
	
		$return = array();
	
		$ka_reg = $this->model_setting_setting->getSetting('kareg');

		$idx = 'kareg' . $extension;
		
		if (empty($ka_reg) || empty($ka_reg[$idx])) {
			return $return;
		}
		
		return $ka_reg[$idx];
	}
	
	
	public function getExtensionInfoByObject($class, $heading_title = '') {

		$ext_info = array(
			'name'    => '',
			'version' => '',
		);

		// retrieve version and name from old headers
		//
		if (preg_match("/(.*)[\(]*ver\.(.*)[\)]*$/U", $heading_title, $matches)) {
			$ext_info['name']    = trim(preg_replace("/ver$/", "", preg_replace("/\(.*/", "", trim($matches[1]))));
			$ext_info['version'] = trim(preg_replace("/\(\)/", "", trim($matches[2])), '.');
		} else {
			$ext_info['name'] = $heading_title;
			$ext_info['version'] = '';
		}
			
		// get version with a method when available
		//
		if (method_exists($class, 'getVersion')) {
			if (empty($ext_info['name'])) {
				$ext_info['name'] = $heading_title;
			}
			$ext_info['version'] = $class->getVersion();
		}
		
		
		if (method_exists($class, 'isLite')) {
			$ext_info['is_lite'] = $class->isLite();
		}
		
		return $ext_info;
	}
	
	
	public function updateRelated($related) {
	
		if (empty($related)) {
			return false;
		}
		
		foreach ($related as $k => $v) {
			$this->db->query("UPDATE " . DB_PREFIX . "extension SET show_related = " . (int)$v . " 
				WHERE code = '" . $this->db->escape($k) . "'"
			);
		}
	}
	
	public function getKaInstalled($type = 'ka_extensions') {
		$extension_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "extension 
			WHERE `type` = '" . $this->db->escape($type) . "'
		");
		
		if (empty($query->rows)) {
			return $extension_data;
		}
		
		foreach ($query->rows as $row) {
			$extension_data[$row['code']] = $row;
		}
		
		return $extension_data;
	}
	
	
/*

	$key       - a license number
	$extension - full extension code

	Returns

		$ka_st_data = array(
			'result' => 'OK'
		);

		$ka_st_data = array(
			'result'  => 'ERROR',
			'message' => 'The code is not valid.'
		);
*/	
	public function registerKey($key, $extension) {

		$module = $this->getModuleParts($extension);
		$data = array(
			'api_version'      => $this->license_api_version,
			'license_key'      => $key,
			'surface_uri'      => HTTP_CATALOG,
			'vendor'           => $module['vendor'],
			'code'             => $module['code'],
			'edition'          => $module['edition'],
			'opencart_version' => VERSION,
		);

		$result = $this->requestLicenseServer(static::LICENSE_API_ROUTE . 'linkLicense', $data);
		if (is_null($result) || $result['result'] == '') {
			$this->lastError = 'A request to the license registration server failed. ' . $this->lastError
				. '<br />Please try again later.<br /><br />If you cannot activate the license key within 3 '
				. 'days please contact us at support@ka-station.com.';
			return false;
		}

		if ($result['result'] != 'ok') {
			$this->lastError = empty($result['message']) ? 'The license key was not accepted.' : $result['message'];
			return false;
		}

		$ext_code = ($module['vendor'] == 'ka_extensions') ? $module['code'] : $module['vendor'] . '/' . $module['code'];
		if (!$this->saveReg($ext_code, array('is_registered' => true))) {
			$this->lastError = 'saveReg failed';
			return false;
		}

		return true;
	}


	/*
		Returns the edition of license codes for this store: 'oc3' for Opencart 3.x
	*/
	public function getStoreEdition() {
		return 'oc' . (int)VERSION;
	}


	/*
		Splits a full extension code: 'vendor/code@edition', 'code' => [vendor, code, edition].
		The vendor is 'ka_extensions' by default, the edition is the edition of the store.
	*/
	public function getModuleParts($extension, $vendor = 'ka_extensions') {

		$edition = $this->getStoreEdition();
		if (strpos($extension, '@') !== false) {
			list($extension, $edition) = explode('@', $extension, 2);
		}
		if (strpos($extension, '/') !== false) {
			list($vendor, $extension) = explode('/', $extension, 2);
		}

		return array(
			'vendor'  => $vendor,
			'code'    => $extension,
			'edition' => $edition,
		);
	}


	/*
		Requests the state of the modules of the vendor from the API of the license module
		(api/checkModules, api_version 1): vendor, code and edition of every module, the Opencart version.

		Returns the extensions in the format of the old domain_info response (see saveRegAll()):
			[license_code => ['is_registered' => 1, ...] or ['is_free' => 1, ...]]
		or null (lastError is set) when the server is not available or its answer is not valid (also 404 and
		a page instead of json) or it returned an error. Then the saved states are not changed.
	*/
	public function checkModules($vendor = 'ka_extensions') {

		$modules = array();
		foreach ($this->getLoadedExtensions($vendor) as $ext_code => $license_code) {
			$modules[$license_code] = $this->getModuleParts($license_code, $vendor);
		}

		$data = array(
			'api_version'      => $this->license_api_version,
			'url'              => HTTP_CATALOG,
			'opencart_version' => VERSION,
			'modules'          => array_values($modules),
		);

		$result = $this->requestLicenseServer(static::LICENSE_API_ROUTE . 'checkModules', $data);
		if (is_null($result)) {
			return null;
		}
		if ($result['result'] != 'ok' || !isset($result['modules']) || !is_array($result['modules'])) {
			$this->lastError = empty($result['message']) ? 'The license server returned an error.' : $result['message'];
			return null;
		}

		return $this->getExtensionsByModules($result['modules'], $modules);
	}


	/*
		Converts modules of the checkModules response to the old extensions format. Unregistered and
		expired modules are not in the list (as in the old format).
	*/
	protected function getExtensionsByModules($response_modules, $modules) {

		$license_codes = array_keys($modules);

		$extensions = array();
		foreach (array_values($response_modules) as $idx => $module) {
			if (!isset($license_codes[$idx]) || empty($module['status'])) {
				continue;
			}
			$license_code = $license_codes[$idx];

			if ($module['status'] == 'registered') {
				$masked_key = isset($module['masked_key']) ? $module['masked_key'] : '';
				$extensions[$license_code] = array(
					'masked_key'    => $masked_key,
					'license_key'   => $masked_key,
					'is_registered' => 1,
				);
				if (!empty($module['expiry_at'])) {
					$extensions[$license_code]['expiry_date'] = substr($module['expiry_at'], 0, 10);
				}
			} elseif ($module['status'] == 'free') {
				$extensions[$license_code] = array(
					'license_key' => '',
					'masked_key'  => '',
					'expiry_date' => '',
					'is_free'     => 1,
				);
			}
		}

		return $extensions;
	}


	/*
		Sends a POST request to the API of the license server. Returns the decoded json answer of the API
		(with api_version and result) or null (lastError is set): the server is not available, HTTP errors
		(404 too), an empty answer, not json, no api_version.
	*/
	protected function requestLicenseServer($route, $data) {

		$result = $this->kacurl->request(\KaGlobal::getKaStoreURL() . '?route=' . $route, $data);
		if (empty($result)) {
			$this->lastError = 'A request to the license registration server failed with this error:' . $this->kacurl->getLastError();
			return null;
		}

		$result = json_decode($result, true);
		if (!is_array($result) || !isset($result['api_version']) || !isset($result['result'])) {
			$this->lastError = 'The license server returned an invalid answer.';
			return null;
		}

		return $result;
	}


	public function isRegistered($extension_code, $vendor) {
		if ($vendor == 'ka_extensions') {
			$ka_reg = $this->model_setting_setting->getSetting('kareg');
		} else {
			$ka_reg = $this->model_setting_setting->getSetting('kareg_' . $vendor);
			$extension_code = '_' . $vendor . '/' . $extension_code;
		}
		
		if (empty($ka_reg)) {
			return false;
		}

		if (isset($ka_reg['kareg' . $extension_code]['is_registered'])) {
			return true;
		}
		
		return false;
	}

		
	public function saveRegAll($data = array(), $vendor = null) {

		if ($data === null) {
			return true;
		}

		$kareg_key = 'kareg';
		if ($vendor != 'ka_extensions') {
			$kareg_key .= '_' . $vendor;
		}
		
		// get installed extensions
		//
		$installed_extensions = $this->getKaInstalled($vendor);
		$installed_extension_codes = array_keys($installed_extensions);
		
		$url = HTTP_CATALOG;
		$kareg = array(
			$kareg_key => 1
		);
		
		// get existing registrations
		//
		$existing = $this->model_setting_setting->getSetting($kareg_key);
		
		if (!empty($data)) {
			foreach ($data as $k => $v) {
			
				$v['url']           = $url;
				if (!empty($v['is_free'])) {
					$v['is_free'] = 1;
				}
				$v['is_registered'] = 1;

				if ($vendor != 'ka_extensions') {
					$key = $kareg_key . '/' . $k;
				} else {
					$key = $kareg_key . $k;
				}
				
				$kareg[$key] = $v;

				if (!empty($existing[$key])) {
					unset($existing[$key]);
				}
			}
		}
		
		// copy installed licenses to the new array
		//
		if (!empty($existing[$kareg_key])) {
			unset($existing[$kareg_key]);
		}
		
		if (!empty($existing)) {
			foreach ($existing as $ek => $ev) {
				$ext_code = substr($ek, 5);
				if (in_array($ext_code, $installed_extension_codes)) {
					$ev['is_wrong_license'] = true;
					$kareg[$ek] = $ev;
				}
			}
		}

		$this->model_setting_setting->editSetting($kareg_key, $kareg);
		
		return true;
	}

	/*
		saveReg - full extension code (with slashes)
	*/
	public function saveReg($full_ext_code, $data = array()) {

		if (strpos($full_ext_code, '/')) {
			list($vendor, $ext_code) = explode('/', $full_ext_code);
		} else {
			$vendor   = 'ka_extensions';
			$ext_code = $full_ext_code;
		}
	
		$url = HTTP_CATALOG;

		if ($vendor == 'ka_extensions') {
			$kareg_key = 'kareg';
		} else {
			$kareg_key = 'kareg_' . $vendor;
		}
		$ka_reg = $this->model_setting_setting->getSetting($kareg_key);
	
		$ka_reg[$kareg_key] = array();
		
		if (isset($data['is_registered'])) {

			if ($vendor == 'ka_extensions') {
				$full_kareg_key = $kareg_key . $ext_code;
			} else {
				$full_kareg_key = $kareg_key . '/' . $ext_code;
			}
			$ka_reg[$full_kareg_key]['is_registered'] = 1;
			$ka_reg[$full_kareg_key]['url'] = $url;
		}
		
		$this->model_setting_setting->editSetting($kareg_key, $ka_reg);
		
		return true;
	}	

	
	/*
		Service function for updating or creating a new Opencart setting
	*/
	public function saveSetting($code, $key, $value) {
		$this->load->model('setting/setting');
		
		$setting = $this->model_setting_setting->getSetting($code);
		$setting[$key] = $value;
		$this->model_setting_setting->editSetting($code, $setting);
	}
	
	
	/*
		Adds a permission ('access' or 'modify') for the route to the user group if the group
		does not have it yet. Opencart addPermission() adds the route on every call, so repeated
		installations created duplicates.
	*/
	public function addPermission($user_group_id, $type, $route) {
		$this->load->model('user/user_group');

		$user_group = $this->model_user_user_group->getUserGroup($user_group_id);
		if (!empty($user_group['permission'][$type]) && in_array($route, $user_group['permission'][$type])) {
			return;
		}

		$this->model_user_user_group->addPermission($user_group_id, $type, $route);
	}


	public function getLoadedExtensions($vendor = 'ka_extensions') {
		$extensions = array();

		$files = glob(DIR_APPLICATION . 'controller/extension/' . $vendor . '/*.php');
		
		foreach ($files as $file) {
		
			$ext_code = basename($file, '.php');
			
			$manifest = $this->getManifest($vendor, $ext_code);
			if (!empty($manifest) && !empty($manifest['license_code'])) {
				$license_code = $manifest['license_code'];
			} else {
				$license_code = $ext_code;
			}
			$extensions[$ext_code] = $license_code;
		}

		return $extensions;
	}

	
	public function getSafeModeCode() {
	
		$kamod_manager = \extension\ka_extensions\KamodManager::getInstance();
		$config_file = DIR_CONFIG . 'kamod.php';
		
		$config = $kamod_manager->readConfigFile($config_file);
		
		if (empty($config['safe_mode_code'])) {
			return '';
		}

		return $config['safe_mode_code'];
	}
	
	
	public function saveSafeModeCode($code) {

		$kamod_manager = \extension\ka_extensions\KamodManager::getInstance();
		$config_file = DIR_CONFIG . 'kamod.php';
		
		$config = $kamod_manager->readConfigFile($config_file);
		$config['safe_mode_code'] = $code;
		
		$kamod_manager->saveConfigFile($config_file, $config);
	}
	
	
	protected function getManifests() {
		$manifests = glob(DIR_APPLICATION . 'controller/extension/*/*/' . \extension\ka_extensions\KamodManager::MANIFEST_INI_FILE);
		if (empty($manifests)) {
			return [];
		}

		return $manifests;
	}

	
	public function getRequiredModules($module, $vendor) {
	
		$ini = $this->getManifest($vendor, $module);

		return \extension\ka_extensions\KamodManager::getManifestModules($ini, 'depends_on');
	}


	/*
		Checks if a module is installed by its full code 'vendor/code'.

		Karapuz modules are checked with \KaGlobal::isKaInstalled() (it also checks the registration).
		Modules of other vendors are checked by their records in the 'extension' table (type = vendor).
	*/
	public function isModuleInstalled($full_code) {

		list($vendor, $code) = explode('/', $this->getFullModuleCode($full_code), 2);

		if ($vendor == 'ka_extensions') {
			return \KaGlobal::isKaInstalled($code);
		}

		$installed = $this->getKaInstalled($vendor);

		return isset($installed[$code]);
	}
	
	
	/*
		Returns a full module code 'vendor/code'. A code without a vendor is
		treated as a Karapuz module, i.e. 'ka_extensions/<code>'.
	*/
	protected function getFullModuleCode($code) {
		return \extension\ka_extensions\KamodManager::getFullModuleCode($code);
	}


	/*
		$module - code of the module being uninstalled
		$vendor - vendor of that module
	*/
	public function getDependentModules($module, $vendor = 'ka_extensions') {

		$manifests = $this->getManifests();
		if (empty($manifests)) {
			return $manifests;
		}

		$full_module_code = $vendor . '/' . $module;
		
		$dependent = [];
		
		foreach ($manifests as $m) {

			// parse ini settings
			$ini = \parse_ini_file($m);
			
			$depends_on = \extension\ka_extensions\KamodManager::getManifestModules($ini, 'depends_on');
			if (!in_array($full_module_code, $depends_on)) {
				continue;
			}
			
			// the dependent module code is taken from the manifest path: <vendor>/<code>/kamod.ini
			$dependent_code = basename(dirname(dirname($m))) . '/' . basename(dirname($m));

			$dependent[] = [
				'code' => $dependent_code,
				'name' => !empty($ini['module']) ? $ini['module'] : $dependent_code
			];
		}			
		
		return $dependent;
	}
	
	
	public function getManifest($vendor, $ext_code) {

		$ext_code = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$ext_code);
	
		$file = DIR_APPLICATION . 'controller/extension/' . $vendor . '/' . $ext_code . '/' . \extension\ka_extensions\KamodManager::MANIFEST_INI_FILE;
		if (!file_exists($file)) {
			return [];
		}

		// parse ini settings
		$ini = \parse_ini_file($file);
		
		return $ini;
	}
	
}