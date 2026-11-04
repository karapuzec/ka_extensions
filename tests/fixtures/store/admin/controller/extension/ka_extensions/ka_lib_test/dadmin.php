<?php
/*
	The admin list page of the library tests: ControllerAdminList with its defaults only.
*/

namespace extension\ka_extensions\ka_lib_test;

class ControllerDadmin extends \extension\ka_extensions\ControllerAdminList {

	const PAGE_ROUTE  = 'extension/ka_extensions/ka_lib_test/dadmin';
	const FORM_ROUTE  = 'extension/ka_extensions/ka_lib_test/ditem';

	protected function getLanguageRoutes() {
		return array('extension/ka_extensions/ka_lib_test/items');
	}
}

class_alias(__NAMESPACE__ . '\ControllerDadmin', '\ControllerExtensionKaExtensionsKaLibTestDadmin');
