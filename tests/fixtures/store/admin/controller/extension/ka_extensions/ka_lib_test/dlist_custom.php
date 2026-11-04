<?php
/*
	The default list page with a filter template of the module (only the filter is replaced).
*/

namespace extension\ka_extensions\ka_lib_test;

require_once(__DIR__ . '/dlist.php');

class ControllerDlistCustom extends ControllerDlist {

	const PAGE_ROUTE      = 'extension/ka_extensions/ka_lib_test/dlist_custom';
	const FILTER_TEMPLATE = 'extension/ka_extensions/ka_lib_test/dlist_custom/filter.twig';
}

class_alias(__NAMESPACE__ . '\ControllerDlistCustom', '\ControllerExtensionKaExtensionsKaLibTestDlistCustom');
