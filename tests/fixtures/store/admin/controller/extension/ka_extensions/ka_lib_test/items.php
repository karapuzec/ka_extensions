<?php
/*
	Test list page of the library tests (ControllerList).
*/

namespace extension\ka_extensions\ka_lib_test;

class ControllerItems extends \extension\ka_extensions\ControllerList {

	const PAGE_ROUTE = 'extension/ka_extensions/ka_lib_test/items';
	const FORM_ROUTE = 'extension/ka_extensions/ka_lib_test/item';

	protected $enable_delete = true;

	protected function getPageUrlParams() {
		$params = parent::getPageUrlParams();
		$params['filter_name'] = '';
		return $params;
	}

	protected function getSortFields() {
		return array(
			'name' => 'name',
			'date' => 'date',
		);
	}
}

class_alias(__NAMESPACE__ . '\ControllerItems', '\ControllerExtensionKaExtensionsKaLibTestItems');
