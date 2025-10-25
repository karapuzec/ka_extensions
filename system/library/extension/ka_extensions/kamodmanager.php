<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

include_once(__DIR__ . '/kamodbuilder.php');

class KamodManager {

	const ACTIVE_KAMOD_FILE = 'active.kamod';

	const MANIFEST_INI_FILE = 'kamod.ini';
	
	const KAMOD_CACHE_DIR     = 'cache.kamod';
	const KAMOD_TEMPLATES_DIR = 'templates';
	const THEME_CACHE_DIR     = 'theme.kamod';

	protected $kamod_builder;
	
	// basenames of real directories
	protected $admin_dir;
	protected $catalog_dir;
	protected $system_dir;
	protected $service_dirs = array();

	
	//full path to the store root dir. Ended with slash.
	protected $store_root_dir;
	
	// full path to the theme cache dir. Ended with slash.
	protected $theme_cache_dir;
	
	// full path to ka_cache dir. Ended with slash.
	protected $ka_cache_dir;
	
	// link to the kamod manager object
	protected static $instance;
	
	protected $is_admin_area = false;
	
	public static function getKamodCacheDir() {

		// OC3000 did not have this constant in front-end
		if (!defined('DIR_STORAGE')) {
			define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
		}
	
		return DIR_STORAGE . static::KAMOD_CACHE_DIR . '/';
	}
	
	
	public static function getKamodTemplatesDir($store_id = null) {
	
		if (is_null($store_id)) {
			return static::getKamodCacheDir() . static::KAMOD_TEMPLATES_DIR . '.default/';
		}
		
		return static::getKamodCacheDir() . static::KAMOD_TEMPLATES_DIR . '.' . $store_id . '/';
	}
	
	
	public static function getThemeCacheDir($store_id = 0) {
	
		return DIR_STORAGE . static::THEME_CACHE_DIR . '/' . $store_id . '/';
	}

	
	protected function __construct() {

		if (defined('DIR_CATALOG')) {
			$this->is_admin_area = true;
		}
	
		$this->store_root_dir    = dirname(DIR_SYSTEM) . '/';

		$this->theme_cache_dir = DIR_STORAGE . static::THEME_CACHE_DIR . '/';
		$this->ka_cache_dir    = DIR_STORAGE . static::KAMOD_CACHE_DIR . '/';

		$this->kamod_builder = new KamodBuilder($this->store_root_dir, 
			$this->ka_cache_dir,
			static::KAMOD_TEMPLATES_DIR,
			$this->theme_cache_dir
		);
		
		if (empty($this->kamod_builder)) {
			throw new \Exception("Kamod builder cannot be created");
		}

		$this->initMainStoreDirectoryNames();
	}
	
	public static function getInstance() {
		
		if (!is_null(static::$instance)) {
			return static::$instance;
		}

		static::$instance = new KamodManager();
		
		return static::$instance;
	}
	
	public function isKamodCacheValid() {
	
		$is_valid = $this->kamod_builder->isCacheValid();
		
		return $is_valid;
	}

	
	public function isKamodCacheEmpty() {

		$is_empty = $this->kamod_builder->isCacheEmpty();
		
		return $is_empty;
	}
	
	
	public function markKamodCacheInvalid() {
		$this->kamod_builder->markCacheInvalid();
	}
	

	protected function initMainStoreDirectoryNames() {

		$this->catalog_dir = 'catalog';
		$this->system_dir  = 'system';
	
		// get the admin directory name
		//
		if ($this->is_admin_area) {
		
			$this->admin_dir = basename(DIR_APPLICATION);
			
		} else {
			$config_file = DIR_CONFIG . 'kamod.php';
			if (!file_exists($config_file)) {
				throw new \Exception("The kamod config file was not found: $config_file");
			}
			
			$_ = [];
			@include($config_file);

			if (empty($_['admin_dir'])) {
				throw new \Exception("The admin directory was not found in config file ($config_file)");
			}
			
			$this->admin_dir = $_['admin_dir'];
		}

		$service_dirs = array(
			'admin'   => $this->admin_dir,
			'catalog' => $this->catalog_dir,
			'system'  => $this->system_dir,
		);
		
		$this->kamod_builder->setServiceDirs($service_dirs);
	}
	
	
	/*
		$extension_code - full code like this: ka_extensions/ka_multivendor
	*/
	protected function isExtensionActive($extension_code) {
	
		$active_marker = $this->admin_dir . '/controller/extension/' . $extension_code . '/' . static::ACTIVE_KAMOD_FILE;
		
		if (file_exists($active_marker)) {
			return true;
		}
		
		return false;
	}

	
	/*
		Return all possible directories of the module
		
		$module_code - "company_name/module_name". It is a part of the path actually.
		
	*/
	protected function getModuleDirs($module_code) {
	
		$dirs = array();
		
		// admin controller
		$dirs[] = $this->admin_dir . '/controller/extension/' . $module_code . '/';
		
		// admin model
		$dirs[] = $this->admin_dir . '/model/extension/' . $module_code . '/';
		
		// admin view
		$dirs[] = $this->admin_dir . '/view/template/extension/' . $module_code . '/';
		
		// catalog controller
		$dirs[] = $this->catalog_dir . '/controller/extension/' . $module_code . '/';
		
		// catalog model
		$dirs[] = $this->catalog_dir . '/model/extension/' . $module_code . '/';
		
		// catalog view
		$pattern = $this->store_root_dir . $this->catalog_dir . '/view/theme/*/template/extension/' . $module_code . '';
		
		$found = glob($pattern);
		if (!empty($found)) {
			foreach ($found as $f) {
				$dirs[] = substr($f, strlen($this->store_root_dir)) . '/';
			}
		}
		
		// system/library/extension (shared model/controller files)
		$dirs[] = $this->system_dir . '/library/extension/' . $module_code . '/';

		// catalog language directories
		//
		$pattern = $this->store_root_dir . $this->catalog_dir . '/language/*/extension/' . $module_code . '';
		$found = glob($pattern);
		if (!empty($found)) {
			foreach ($found as $f) {
				$dirs[] = substr($f, strlen($this->store_root_dir)) . '/';
			}
		}

		// admin language directories
		//
		$pattern = $this->store_root_dir . $this->admin_dir . '/language/*/extension/' . $module_code . '';
		$found = glob($pattern);
		if (!empty($found)) {
			foreach ($found as $f) {
				$dirs[] = substr($f, strlen($this->store_root_dir)) . '/';
			}
		}
		
		return $dirs;
	}
	

	protected function isModuleInstalled($module_code) {

		$marker = $this->store_root_dir . $this->admin_dir . '/controller/extension/' . $module_code . '/active.kamod';
		
		if (file_exists($marker)) {
			return true;
		}
		
		return false;
	}

	
	/*
		Returns a list of real directories within the store root where kamod modules are found.
		Kamod modules are detected by manifest.ini file 
		
		The module has to be enabled, i.e. it contains active.kamod in the admin module directory.		
	*/
	public function getAllModuleDirs() {

		$module_dirs = array();

		$admin_dir = $this->getFullAdminDir();
		
		$manifests = glob($admin_dir . 'controller/extension/*/*/' . static::MANIFEST_INI_FILE);
		$manifests = array_merge($manifests, glob($admin_dir . 'controller/extension/*/' . static::MANIFEST_INI_FILE));
		
		if (empty($manifests)) {
			return $module_dirs;
		}
		
		$module_codes = array();
		foreach ($manifests as $mf) {

			// get the module name and company name as 'module code'
			//
			$rel_file = substr($mf, strlen($admin_dir));
			$rel_file = preg_replace("/controller\/extension\//", "", $rel_file);
			$pathinfo = pathinfo($rel_file);
			$module_code = $pathinfo['dirname'];
			
			// check if the module is installed
			//
			if (!$this->isModuleInstalled($module_code)) {
				continue;
			}
			
			$module_codes[] = $module_code;
		}
		
		$module_codes[] = 'ka_extensions';
		
		foreach ($module_codes as $module_code) {

			// get the module directories
			//
			$dirs = $this->getModuleDirs($module_code);
			
			$module_dirs = array_merge($module_dirs, $dirs);
		}
		
		return $module_dirs;
	}
	
	
	public function readConfigFile($config_file) {
	
		$_ = [];
		if (file_exists($config_file)) {
			@include($config_file);
		}
		
		if (!is_array($_)) {
			return [];
		}
	
		return $_;
	}
	
	
	public function saveConfigFile($config_file, $config) {
	
			$contents = <<<CFGTXT
<?php			
/*
	This file is regenerated automatically on kamod rebuild. Avoid setting any configuration parameters here manually.
	More information on kamod can be found at https://www.ka-station.com/kamod
*/

CFGTXT;

		foreach ($config as $k => $v) {
			$contents .= "\$_['" . $k . "'] = '" . $v . "';\n";
		}
	
		file_put_contents(DIR_CONFIG . 'kamod.php', $contents);
	}
	
	
	/*
		Throws an exception on failure		
	*/
	public function rebuildKamodCache() {

		$module_dirs = $this->getAllModuleDirs();

		// add ka-extensions directories. They are constantly active
		$ka_extensions_dirs = $this->getModuleDirs('ka_extensions/ka_extensions');
		$module_dirs = array_merge($module_dirs, $ka_extensions_dirs);

		$this->kamod_builder->setModuleDirs($module_dirs);

		$this->kamod_builder->buildCache();

		// save the config file
		//
		if ($this->is_admin_area) {

			$config_file = DIR_CONFIG . 'kamod.php';
			
			// we read the old config file to preserve the safe_mode_code value
			//
			$_ = $this->readConfigFile($config_file);
			$_['admin_dir'] = $this->admin_dir;
			
			$this->saveConfigFile($config_file, $_);
		}
	}
	
	
	public function loadClass($class) {

		$file = strtolower(str_replace('\\', '/', $class)) . '.php';
		
		if ($this->is_admin_area) {
			$class_file = $this->ka_cache_dir . 'admin/' . $file;
		} else {
			$class_file = $this->ka_cache_dir . 'catalog/' . $file;
		}
		
		if (!file_exists($class_file)) {
			$class_file = $this->ka_cache_dir . 'system/' . $file;
		
			if (!file_exists($class_file))
				return false;
		}
		
		include_once($class_file);
		
		if (class_exists($class)) {
			return true;
		}
		
		return false;
	}
	
	
	public function emptyThemeCache() {

		$theme_dir = $this->theme_cache_dir;
		Directory::clearDirectory($theme_dir);
	
		$this->kamod_builder->log("Theme cache was emptied");

		$contents = <<<TXT
The directory contains copies of theme files modified by administrator in the theme editor. They are used for 
displaying and building kamod cache on top of them. Do not erase this directory otherwise user's changes may not show 
in template files.

The directory number specifies store id. 0 - default store.

More information on kamod can be found at https://www.ka-station.com/kamod		
TXT;

		$file = $theme_dir . 'readme.txt';
		
		Directory::checkDirectory($file);
		file_put_contents($file, $contents);
	}
	
	
	public function storeThemeFile($store_id, $theme, $route, $code) {

		$file = $this->theme_cache_dir . $store_id . '/catalog/view/theme/' . $theme . '/template/' . $route . '.twig';

		Directory::checkDirectory($file);		
		file_put_contents($file, $code);
		
		$this->kamod_builder->log("Saved a theme cache file: " . $file);
	}
	
	public function rebuildTwigCache() {
		$this->markKamodCacheInvalid();
	}	
	
	public function getLastErrorsTotal() {
	
		$file = DIR_LOGS . KamodBuilder::LOG_ERRORS_FILENAME;
		
		if (!file_exists($file)) {
			return 0;
		}
		
		$rows = file($file);
		
		return count($rows);
	}
	
	
	public function getLanguageFile($file) {

		if ($this->is_admin_area) {
			$lang_file = $this->ka_cache_dir . 'language/' . $this->admin_dir . '/' . $file;
		} else {
			$lang_file = $this->ka_cache_dir . 'language/catalog/' . $file;
		}
		
		if (is_file($lang_file)) {
			return $lang_file;
		}

		return \VQModKa::modCheck(modification(DIR_LANGUAGE . $file), DIR_LANGUAGE . $file);
	}	
	
	
	public function getFullAdminDir() {
	
		if ($this->is_admin_area) {
			$admin_dir = DIR_APPLICATION;
		} else {
			$admin_dir = dirname(DIR_APPLICATION) . '/' . $this->admin_dir . '/';
		}

		return $admin_dir;
	}	
}