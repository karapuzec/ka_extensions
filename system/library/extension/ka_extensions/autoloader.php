<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	This file adds an automatic class loader for kamod cached files.
*/

namespace extension\ka_extensions;

include_once(__DIR__ . '/functions.php');
include_once(__DIR__ . '/kamodmanager.php');

class Autoloader {

	protected $kamod_manager;

	/*
		Install our loader
	*/
	public function __construct() {

		$this->kamod_manager = KamodManager::getInstance();
		
		if (defined('KAMOD_DEBUG')) {

			$this->kamod_manager->rebuildKamodCache();
			
		} elseif (!$this->kamod_manager->isKamodCacheValid()) {
	
			// the cache directory may contain diferent files: 
			// locked.kamod  - another process is rebuilding the cache
			// valid.kamod   - the cache is valid and up to date
			// invalid.kamod - just for user's information, do not rely on it
			//
			$this->kamod_manager->rebuildKamodCache();
			
			if (!$this->kamod_manager->isKamodCacheValid()) {
				throw new \Exception("Kamod cache is not valid");
			}
		}
		
		spl_autoload_register([$this, 'load'], true, true);
	}

	/*
		Main autoload function
	*/
	public function load($class) {

		// we try to load the class from ka_cache directory
		//
		if ($this->kamod_manager->loadClass($class)) {
			return true;
		}

		return false;
	}
}