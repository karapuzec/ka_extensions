<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 575 $)
*/
	
namespace extension\ka_extensions;

/**
	Replaces standard opencart Controller class with extended functionality
*/
abstract class Controller extends \Controller {

	use TraitSession, TraitController;

	protected $kadb = null;
	
	function __construct($registry) {
		parent::__construct($registry);

		if (!defined('KA_DEBUG_DEPRECATED')) {			
			$this->kadb = new Db($this->db);
		}
		
		if (\KaGlobal::isAdminArea()) {
			$this->document->addStyle('view/stylesheet/extension/ka_extensions/stylesheet.css');
		}
		$this->onLoad();
	}

	protected function onLoad() {
		return true;
	}

	
	/**
		@deprecated Use the native response->setOutput or $this->showPage()
	*/
	protected function setOutput($param = null) {
	
		assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
	
		if (!is_null($param)) {
			$this->response->setOutput($param);
		} else {
			$this->response->setOutput($this->render());
		}
	}
	
	/**
		@deprecated 
		@internal
	*/
	protected function getNamespace() {
		
		assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
	
		$class = get_class($this);
		$pos   = strripos($class, '\\');
		$ns    = '';
		if ($pos) {
			$ns = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
		}
		return $ns;	
	}

	/**
		@deprecated

		The function loads a model within the module namespace
	*/
	protected function kamodel($model) {
	
		assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
	
		$ns = $this->getNamespace();
		return $this->load->kamodel($ns . $model);
	}
	
	
	/**
		@internal

		Do not use these 'magic' prefixes. Their behavior is not transparent in inherated classes
			tbl_
			kamodel_
		
	*/
	public function __get($key) {
	
		// create a table
		//
		if (strncasecmp('tbl_', $key, 4) === 0) {

			assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
		
			$ns = $this->getNamespace();
			$tbl = str_replace('_', '', substr($key, 4));
			$tbl = $ns . 'table/' . $tbl;
			
			$table = Tables::getTable($tbl);
			return $table;
		} 

		// create a model
		//
		if (strncasecmp('kamodel_', $key, 8) === 0) {
		
			assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
		
			$model_name = $new_key = substr($key, 8);
			
			$ns = $this->getNamespace();
			if (!empty($ns)) {
				$new_key = str_replace('/', '_', $ns) . $new_key;
			}
			
			$new_key = 'model_' . $new_key;
			
			// try to load the model on the fly
			if (!$this->registry->has($new_key)) {
				$this->kamodel($model_name);
			}
			
			$key = $new_key;
		}
		
		return parent::__get($key);
	}
}
