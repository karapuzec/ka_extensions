<?php
/*
	$Project: Multivendor $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 1.0.0.0 $ ($Revision: 101 $)

*/
namespace extension\ka_extensions;

class ControllerMod1 extends ControllerSettings {

	protected $ext_code = 'mod1';
	
	protected $extension_version = '1.0.0.0';
	protected $min_store_version = '3.0.0.0';
	protected $max_store_version = '3.0.9.9';
	protected $min_ka_extensions_version = '4.1.1.0';
	protected $max_ka_extensions_version = '4.1.1.50';
	
	protected function onLoad() {
		return parent::onLoad();
	}
	
	protected function getFields() {
		return array();
	}
	
	public function isFree() {
		return true;
	}
}

class_alias(__NAMESPACE__ . '\ControllerMod1', 'ControllerExtensionKaExtensionsMod1');