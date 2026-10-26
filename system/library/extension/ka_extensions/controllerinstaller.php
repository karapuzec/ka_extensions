<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.25 $ ($Revision: 642 $)
*/
	
namespace extension\ka_extensions;

/**
	@internal

	This class contains essential parts of the module settings page. All module setting pages should inherate
	from that class.
*/
abstract class ControllerInstaller extends Controller {

	// contstants
	//
	public static $ka_extensions_version = '4.1.1.28';
	
	protected $extension_version = '0.0.0';
	protected $min_store_version = '3.0.0.0';
	protected $max_store_version = '3.0.5.99';
	protected $min_ka_extensions_version = '4.1.0.0';
	protected $max_ka_extensions_version = '4.1.1.50';
	
	protected $tables;
	protected $xml_file = '';
	
	protected $ext_link  = '';
	protected $docs_link = '';
	
	protected $vendor   = 'ka_extensions';
	protected $ext_code = '';

	protected $ini;

	protected function onLoad() {
		$this->load->kamodel('extension/ka_extensions');
		$this->load->model('setting/setting');
		$this->load->model('user/user_group');

		parent::onLoad();

		if (empty($this->ext_code)) {
			$reflector = new \ReflectionClass($this);
			$filename = $reflector->getFileName();
		
			$this->ext_code = basename($filename, '.php');
			$this->vendor   = basename(dirname($filename));
		}
		
		$this->loadKamodIni();
		
		return true;	
	}
	
	protected function checkCompatibility(&$tables, &$messages) {

/* 
	store checking was disabled to allow module installation to 3.0.4.x without modifying their version compatibility code.

		// check store version 
		if (version_compare(VERSION, $this->min_store_version, '<')
			|| version_compare(VERSION, $this->max_store_version, '>'))
		{
			$messages[] = "Compatibility of this extension with your store version (" . VERSION . ") was not checked.
				Please contact ka-station team for update.";
			return false;
		}
*/
		// check ka_extensions version 
		if (version_compare(self::$ka_extensions_version, $this->min_ka_extensions_version, '<')) {
			$messages[] = "The module is not compatible with the installed Ka Extensions library.
				The minimum Ka Extensions library version is " . $this->min_ka_extensions_version .
				". Please update the Ka Extensions library up to the latest version.";
			return false;
		}
		
		if (version_compare(self::$ka_extensions_version, $this->max_ka_extensions_version, '>')) {
			$messages[] = "The module is not compatible with the installed Ka Extensions library.
				The maximum Ka Extensions library version is " . $this->max_ka_extensions_version . 
				". Please update the module up to the latest version.";
			return false;
		}
				
		//check database
		//
		if (!$this->model_extension_ka_extensions->checkDBCompatibility($tables, $messages)) {
			return false;
		}
    
		return true;
	}
	
	
	public function install() {

		if (!$this->checkCompatibility($this->tables, $messages)) {
			$this->addTopMessage($messages, 'E');
			return false;
		}
		
		if (!$this->model_extension_ka_extensions->patchDB($this->tables, $messages)) {
			$this->addTopMessage($messages, 'E');
			return false;
		}

		// grant permissions to extension pages
		//
		$routes = $this->getExtensionPages();
		if (!empty($routes)) {
			foreach($routes as $r) {
				$this->model_extension_ka_extensions->addPermission($this->user->getGroupId(), 'access', 'extension/' . $this->vendor . '/' . $this->ext_code . '/' . $r);
				$this->model_extension_ka_extensions->addPermission($this->user->getGroupId(), 'modify', 'extension/' . $this->vendor . '/' . $this->ext_code . '/' . $r);
			}
		}
		
		return true;
	}


	public function uninstall() {
		return true;
	}


	/*
		Upgrades the installed module to the version of the files. The Ka Extensions page calls it by
		the 'Refresh' button when $extension_version is greater than the installed version.

		By default it runs install() again: it patches the database, adds the default values of new
		settings and grants permissions to new pages. A module overrides it when install() cannot be
		repeated safely or when the data has to be migrated, e.g.:

			public function upgradeExtension($from_version, $to_version) {
				if (!parent::upgradeExtension($from_version, $to_version)) {
					return false;
				}
				if (version_compare($from_version, '1.0.1.0', '<')) {
					// migration to 1.0.1.0
				}
				return true;
			}

		$from_version - the installed version ('0.0.0.0' when it is not known)
		$to_version   - the version of the files ($extension_version)

		Returns true on success. On failure the installed version is not changed and the button stays.

		The method is not called upgrade() because older modules have their own upgrade() without
		parameters (e.g. Abandoned Cart Reminder 4.0.2.x), an incompatible declaration is a fatal error.
	*/
	public function upgradeExtension($from_version, $to_version) {
		return $this->install();
	}


	/*
		Returns the installed version of the module stored on installation or upgrade, '0.0.0.0' when
		it is not stored (modules installed by older versions of the library).
	*/
	public function getInstalledVersion() {

		if (!method_exists($this->model_setting_setting, 'getConfigHidden')) {
			return '0.0.0.0';
		}

		$version = $this->model_setting_setting->getConfigHidden($this->getSettingCode(), 'version');
		if (empty($version)) {
			return '0.0.0.0';
		}

		return $version;
	}


	/*
		Stores the installed version of the module (the hidden value 'version' of the module).
	*/
	public function saveInstalledVersion($version) {

		if (!method_exists($this->model_setting_setting, 'setConfigHidden')) {
			return false;
		}

		$this->model_setting_setting->setConfigHidden($this->getSettingCode(), 'version', $version);

		return true;
	}


	/*
		Returns the code (group) of the module settings in the OpenCart setting table. The settings page
		saves the values under this code, install() stores the default values there. Hidden values of
		the module (the installed version) are stored under '<code>_hidden'. Opencart editSetting()
		stores only keys starting with the code, so the field codes of the module should start with it.

		vendor 'ka_extensions' - the extension code ('ka_messages')
		other vendors          - vendor and extension code ('fluffy_opc_orders')
	*/
	protected function getSettingCode() {

		if ($this->vendor == 'ka_extensions') {
			return $this->ext_code;
		}

		return $this->vendor . '_' . $this->ext_code;
	}
	
	
	public function getTitle() {
		$str = str_replace('{{version}}', $this->extension_version, $this->language->get('heading_title_ver'));
		return $str;
	}	
	
	
	public function getVersion() {
		return $this->extension_version;
	}
	
	
	public function getExtLink() {
		return $this->ext_link;
	}
	
	public function getDocsLink() {
		return $this->docs_link;
	}	
	
	
	protected function loadKamodIni() {
	
		$ini_file = DIR_APPLICATION . '/controller/extension/ka_extensions/' . $this->ext_code . '/kamod.ini';
		
		if (!file_exists($ini_file)) {
			return false;
		}
		
		$this->ini = \parse_ini_file($ini_file);
		
		if (!empty($this->ini['documentation_url'])) {
			$this->docs_link = $this->ini['documentation_url'];
		}
		
		if (!empty($this->ini['extension_page_url'])) {
			$this->ext_link = $this->ini['extension_page_url'];
		}
		
		return true;
	}			

	/*
		This function returns a list of extension routes which should be enabled on extension installation.
	*/
	protected function getExtensionPages() {
		return array();
	}
}