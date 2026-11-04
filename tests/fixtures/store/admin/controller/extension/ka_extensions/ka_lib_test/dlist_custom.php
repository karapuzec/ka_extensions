<?php
/*
	The default list page with a template of the module overriding the block list_filter only.
*/

namespace extension\ka_extensions\ka_lib_test;

require_once(__DIR__ . '/dlist.php');

class ControllerDlistCustom extends ControllerDlist {

	const PAGE_ROUTE      = 'extension/ka_extensions/ka_lib_test/dlist_custom';
	const DEFAULT_LIST_TEMPLATE = 'extension/ka_extensions/ka_lib_test/dlist_custom_list';
}

class_alias(__NAMESPACE__ . '\ControllerDlistCustom', '\ControllerExtensionKaExtensionsKaLibTestDlistCustom');
