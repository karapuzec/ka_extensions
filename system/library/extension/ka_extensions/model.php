<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 575 $)
*/
	
namespace extension\ka_extensions;

use extension\ka_extensions\Tables;

/**
	Base model class
*/
abstract class Model extends \Model {

	use TraitSession;

	protected $lastError;
	
	function __construct($registry) {
		parent::__construct($registry);

		if (!defined('KA_DEBUG_DEPRECATED')) {
			$this->kadb = new Db($this->db);
		}

		$this->onLoad();
	}
	
	public function getLastError() {
		return $this->lastError;
	}
	
	protected function onLoad() {
		return true;
	}
	
	public function __get($key) {
	
		// create a table
		//
		if (strncasecmp('tbl_', $key, 4) === 0) {

			$ns = $this->getNamespace();
			$tbl = str_replace('_', '', substr($key, 4));
			$tbl = $ns . 'table/' . $tbl;
		
			$table = Tables::getTable($tbl);
			return $table;
		}
	
		if (strncasecmp('kamodel_', $key, 8) === 0) {
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
	
	
	protected function getNamespace() {
		$class = get_class($this);
		$pos   = strripos($class, '\\');
		$ns    = '';
		if ($pos == 0) {
			$ns = $model;
		} else {
			$ns = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
		}
		return $ns;	
	}
		
	protected function kamodel($model) {
		$ns = $this->getNamespace();
		$this->load->kamodel($ns . $model);
	}
}