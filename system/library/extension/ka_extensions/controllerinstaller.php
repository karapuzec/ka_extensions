<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.22 $ ($Revision: 575 $)
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
	public static $ka_extensions_version = '4.1.1.22';
	
	protected $extension_version = '0.0.0';
	protected $min_store_version = '3.0.0.0';
	protected $max_store_version = '3.0.3.33';
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
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/' . $this->vendor . '/' . $this->ext_code . '/' . $r);
				$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/' . $this->vendor . '/' . $this->ext_code . '/' . $r);
			}
		}
		
		return true;
	}


	public function uninstall() {
		return true;
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