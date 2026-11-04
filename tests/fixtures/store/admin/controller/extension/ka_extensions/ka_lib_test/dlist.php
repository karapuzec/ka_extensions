<?php
/*
	The default list page of the library tests: no model file, no template (ControllerList::TABLE_CLASS,
	$use_default_list).
*/

namespace extension\ka_extensions\ka_lib_test;

class ControllerDlist extends \extension\ka_extensions\ControllerList {

	const PAGE_ROUTE  = 'extension/ka_extensions/ka_lib_test/dlist';
	const FORM_ROUTE  = 'extension/ka_extensions/ka_lib_test/ditem';
	const TABLE_CLASS = '\\extension\\ka_extensions\\ka_lib_test\\dbtable\\MetaItem';

	protected $enable_delete    = true;
	protected $use_default_list = true;
	protected $complete_records = false;

	protected function getLanguageRoutes() {
		return array('extension/ka_extensions/ka_lib_test/items');
	}
}

class_alias(__NAMESPACE__ . '\ControllerDlist', '\ControllerExtensionKaExtensionsKaLibTestDlist');
