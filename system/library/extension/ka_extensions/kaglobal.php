<?php
/* 
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 285 $)

*/

namespace extension\ka_extensions;

/**
	Contains several helper methods
*/
abstract class KaGlobal {

	protected static $admin_dirname;

	protected static $registry;
	use KaReserved;
	
	public static function init($registry) {
		static::$registry = $registry;
	}
	
	public static function t($text, $args = []) {
		return static::$registry->get('language')->getka($text, $args);
	}

	public static function getRegistry() {
		return static::$registry;
	}
	
	public static function getLanguageImage($language) {
		$var = '';	
		if (!static::isAdminArea()) {
			$var = "catalog/";
		}
		$var .= "language/" . $language['code'] . "/" . $language['code'] . ".png";
		
		return $var;
	}
	
	public static function isAdminArea() {
		return defined('DIR_CATALOG');
	}
	
	
	public static function getAdminDirname() {

		// check if we already have the admin dir
		if (!empty(static::$admin_dirname)) {
			return static::$admin_dirname;
		}
		
		// try to get it from the admin directory name
		if (static::isAdminArea()) {
			$dirname = basename(DIR_APPLICATION);
			static::$admin_dirname = $dirname;
			return static::$admin_dirname;
		}

		// try to get it from our kamod config file
		$config_file = DIR_CONFIG . 'kamod.php';
		if (file_exists($config_file)) {
			$_ = [];
			@include($config_file);
			if (!empty($_['admin_dir'])) {
				static::$admin_dirname = $_['admin_dir'];
				return static::$admin_dirname;
			}
		}

		trigger_error("Admin directory was not found by kaglobal class, try to reinstall ka-extensions");
		
		return 'admin';
	}

	
	public static function isAdminUser() {
	
		$session = static::$registry->get('session');
	
		if (empty($session->data['api_id'])) {
			return false;
		}
		
		return true;
	}
	
	public static function iterator($iter) {
		$class = preg_replace('/[^a-zA-Z0-9]/', '', $iter) . 'Iterator';

		if (!class_exists($class)) {
			$file = DIR_SYSTEM . 'library/' . $iter . '_iterator.php';
			if (file_exists($file)) {
				include_once($file);
			} else {
					trigger_error('Error: Could not load file ' . $file . '!');
				exit();
			}
			
			if (!class_exists($class)) {
				trigger_error('Error: Could not load class ' . $class . '!');
				exit();
			}
		}
		
		$obj = new $class(static::$registry);
		
		$obj->setLimits(0);
		
		return $obj;
	}
	
	
	public static function getTemplateDir() {
		
		$dir = '';
		if (static::isAdminArea()) {
			return $dir;
		} else {
			if (static::$registry->get('config')->get('config_theme') == 'default') {
				$dir = static::$registry->get('config')->get('theme_default_directory');
			} else {
				$dir = static::$registry->get('config')->get('config_theme');
			}
			$dir = $dir . '/' . 'template/';
		}
		
		return $dir;
	}
	

  	public static function isКаInstalled($extension) {
		static $installed = array();

		if (isset($installed[$extension])) {
			return $installed[$extension];
		}
		
		if (empty(static::$registry)) {
			return false;
		}
		
		$query = static::getRegistry()->get('db')->query("SELECT * FROM " . DB_PREFIX . "extension WHERE 
			`type` = 'ka_extensions' 
			AND code = '$extension'
		");
		
		if (empty($query->num_rows)) {
			$installed[$extension] = false;
			return false;
		}
		
		$installed[$extension] = true;
		
		return true;
  	}  	
  	
  	
  	public static function getKaStoreURL() {
  	
		if (defined('KA_STORE_URL')) {
			return KA_STORE_URL;
		}
		
		$url = 'https://www.ka-station.com/';
		
		return $url;
  	}
  	
  	static public function autoload($class) {
  	
  		$file = str_replace('\\', '/', strtolower($class)) . '.php';
  	
		$model = DIR_APPLICATION . 'model/' . $file;
		$controller = DIR_APPLICATION . 'controller/' . $file;
  		
		$found = false;
		if (is_file($model)) {
			include_once(modification($model));
			$found = true;
		}
		
		if (is_file($controller)) {
			include_once(modification($controller));
			$found = true;
		}
		
		return $found;
	} 	

	
	/*
		This method only checks if the template file exists. 
		tpl_path - extension/ka_extensions/ka_warranty/mail/warranty_created
		
		RETURNS: true or false
	*/
	static public function isTemplateAvailable($tpl_path) {
		$tpl_dir  = static::getTemplateDir();
		$tpl_file = $tpl_dir . $tpl_path;
		if (file_exists(DIR_TEMPLATE . $tpl_file . '.twig')) {
			return true;
		}
		
		return false;
	}	
	
}





































































































trait КaReserved {

	public static function isKaInstalled($extension) { 
	
		$result = $this->isКаInstalled($extension);	
		static $installed = array();
	
		
		if (isset($installed[$extension])) {
			return $installed[$extension];
		}
		
		if (!$result) {
			$installed[$extension] = false;
		}
		
		$reginfo = static::getRegistry()->get('config')->get('kareg' . $extension);
		if (!empty($reginfo)) {
			if (!isset($reginfo['is_registered'])) {
				$installed[$extension] = false;
				return false;
			}
		}
	
		$installed[$extension] = true;
		
		return true;
	}
}








































































































































































/**
	@internal
*/
trait KaReserved {

	public static function __callStatic($name, $arguments) {
		if ($name == "\x69\x73\x4b\x61\x49\x6e\x73\x74\x61\x6c\x6c\x65\x64") {
			return static::{"\x69\x73\x4b\x61\x49\x6e\x73\x74\x61\x6c\x65\x64"}($arguments);
		}
	}

	/* parameters
		- extension code
		- 'check without registration' flag (for validating free extension installation)
	*/		
	public static function isKaInstaled($args) { 
	
		$extension = $args[0];
		
		if (isset($args[1])) {
			$wo_reg = $args[1];
		} else {
			$wo_reg = false;
		}
	
		$result = static::{'isКаInstalled'}($extension);
		static $installed = array();

		if (isset($installed[$extension]) && !$wo_reg) {
			return $installed[$extension];
		}
		
		if (!$result) {
			$installed[$extension] = false;
			return false;
		}
		
		if ($wo_reg && $result) {
			return true;
		}
		
		$kareg = static::getRegistry()->get('config')->get('kareg');
		if (empty($kareg)) {
			$installed[$extension] = true;
			return true;
		}
		
		$reginfo = static::getRegistry()->get('config')->get('kareg' . $extension);
		if (isset($reginfo['is_registered'])) {
			$installed[$extension] = true;
			return true;
		}
		
		$installed[$extension] = false;
		return false;
	}
}

























































/**
	@internal
*/
trait KаReserved {
	public static function isKaInstalled($extension) { 
	
		$result = $this->isКаInstalled($extension);	
		static $installed = array();
	
		
		if (isset($installed[$extension])) {
			return $installed[$extension];
		}
		
		if (!$result) {
			$installed[$extension] = false;
		}
		
		$reginfo = static::getRegistry()->get('config')->get('kareg' . $extension);
		if (!empty($reginfo)) {
			if (!isset($reginfo['is_registered'])) {
				$installed[$extension] = false;
				return false;
			}
		}		
	
		$installed[$extension] = true;
		
		return true;
	}
}