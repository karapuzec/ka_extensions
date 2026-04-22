<?php

namespace mycomp\product_field;

class ControllerProductField extends \extension\ka_extensions\ControllerSettings {

	protected $ext_code = 'mycomp/product_field';
	
	protected $extension_version = '1.0.0.0';
	protected $min_store_version = '3.0.0.0';
	protected $max_store_version = '3.0.9.9';
	protected $min_ka_extensions_version = '4.1.1.20';
	protected $max_ka_extensions_version = '4.1.1.99';
	
	
	protected function onLoad() {

		$this->tables = array();
		
		return parent::onLoad();
	}

	
	protected function getFields() {
	
		$fields = array();
	
		return $fields;
	}

	
	protected function getExtensionPages() {
		
		$pages = [];
	
		return $pages;
	}

	
	protected function isFree() {
		return true;
	}
}

class_alias(__NAMESPACE__ . '\ControllerProductField', 'ControllerExtensionMyCompProductField');