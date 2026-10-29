<?php
/*
	Test module of the library tests (tests/fixtures/store, copied into the test store by tests/run.sh).
	A Lite version of a module: isLite() returns true, its license record is 'karegka_lib_lite_lite'.
*/

namespace extension\ka_extensions;

class ControllerKaLibLite extends ControllerSettings {

	protected $extension_version = '1.0.0.0';
	protected $min_ka_extensions_version = '4.1.1.0';
	protected $max_ka_extensions_version = '4.1.1.99';
	protected $vendor   = 'ka_extensions';
	protected $ext_code = 'ka_lib_lite';

	public function isLite() {
		return true;
	}

	protected function getFields() {
		return array();
	}
}

class_alias(__NAMESPACE__ . '\ControllerKaLibLite', '\ControllerExtensionKaExtensionsKaLibLite');
