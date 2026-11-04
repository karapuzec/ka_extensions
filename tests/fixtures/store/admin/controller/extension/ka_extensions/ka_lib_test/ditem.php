<?php
/*
	The default form of the library tests: fields from the table metadata of the recordset (no getFields()).
*/

namespace extension\ka_extensions\ka_lib_test;

class ControllerDitem extends \extension\ka_extensions\ControllerForm {

	const PARENT_ROUTE = 'extension/ka_extensions/ka_lib_test/dlist';
	const PAGE_ROUTE   = 'extension/ka_extensions/ka_lib_test/ditem';

	protected function onLoad() {
		// the recordset is set before parent::onLoad(), the form fields come from its table
		$this->recordset = $this->load->kamodel('extension/ka_extensions/ka_lib_test/dlist');
		return parent::onLoad();
	}

	protected function getLanguageRoutes() {
		return array('extension/ka_extensions/ka_lib_test/items');
	}
}

class_alias(__NAMESPACE__ . '\ControllerDitem', '\ControllerExtensionKaExtensionsKaLibTestDitem');
