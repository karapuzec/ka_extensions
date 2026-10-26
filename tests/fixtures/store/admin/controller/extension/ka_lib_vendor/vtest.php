<?php
/*
	Test module of the library tests (tests/fixtures/store, copied into the test store by tests/run.sh).
	A settings page of a vendor other than 'ka_extensions': the vendor and the extension code are taken
	from the file path, the settings are stored under '<vendor>_<ext_code>' (ka_lib_vendor_vtest).
*/

namespace extension\ka_lib_vendor;

class ControllerVtest extends \extension\ka_extensions\ControllerSettings {

	protected $extension_version = '1.0.0.0';
	protected $min_ka_extensions_version = '4.1.1.0';
	protected $max_ka_extensions_version = '4.1.1.99';

	protected function getFields() {

		$fields = array(
			'ka_lib_vendor_vtest_title' => array(
				'code'          => 'ka_lib_vendor_vtest_title',
				'type'          => 'text',
				'required'      => true,
				'title'         => 'Title',
				'default_value' => 'Vendor default',
			),
			'ka_lib_vendor_vtest_enabled' => array(
				'code'          => 'ka_lib_vendor_vtest_enabled',
				'type'          => 'checkbox',
				'default_value' => 1,
			),
		);

		return $fields;
	}
}

class_alias(__NAMESPACE__ . '\ControllerVtest', '\ControllerExtensionKaLibVendorVtest');
