<?php
/*
	Test module of the library tests (tests/fixtures/store, copied into the test store by tests/run.sh).
	A module without $ext_code whose onLoad() does not call parent::onLoad() (as several modules on
	ka-station): the code comes from the file name. Before 4.1.1.32 such modules had the code '' and shared
	the hidden values (the installed version), see InstallerExtCodeTest.
*/

namespace extension\ka_extensions;

class ControllerKaLibNoparentA extends \KaInstaller {

	protected $extension_version = '2.0.0.1';
	protected $min_ka_extensions_version = '4.1.1.0';
	protected $max_ka_extensions_version = '4.1.1.99';

	protected function onLoad() {
		$this->load->model('setting/setting');
		$this->tables = array();

		return true;
	}
}

class_alias(__NAMESPACE__ . '\ControllerKaLibNoparentA', '\ControllerExtensionKaExtensionsKaLibNoparentA');
