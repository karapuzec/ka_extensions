<?php
/*
	The default form of the library tests: fields from the table metadata (ControllerForm::TABLE_CLASS).
*/

namespace extension\ka_extensions\ka_lib_test;

class ControllerDitem extends \extension\ka_extensions\ControllerForm {

	const PARENT_ROUTE = 'extension/ka_extensions/ka_lib_test/dlist';
	const PAGE_ROUTE   = 'extension/ka_extensions/ka_lib_test/ditem';
	const TABLE_CLASS  = '\\extension\\ka_extensions\\ka_lib_test\\dbtable\\MetaItem';

	protected function getLanguageRoutes() {
		return array('extension/ka_extensions/ka_lib_test/items');
	}
}

class_alias(__NAMESPACE__ . '\ControllerDitem', '\ControllerExtensionKaExtensionsKaLibTestDitem');
