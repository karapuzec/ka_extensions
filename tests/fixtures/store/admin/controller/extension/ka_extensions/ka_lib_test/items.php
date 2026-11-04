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
		$params['filter_status'] = '';
		return $params;
	}

	// a select filter with the option 0 (the selected option of '' and '0' values)
	protected function getPageFilterFields($filter_fields = array()) {
		return parent::getPageFilterFields(array(
			'filter_status' => array(
				'type'            => 'select',
				'title'           => 'Status',
				'hide_first_line' => true,
				'options'         => array('' => 'Any', 0 => 'Disabled', 1 => 'Enabled'),
			),
		));
	}

	protected function getSortFields() {
		return array(
			'name' => 'name',
			'date' => 'date',
		);
	}
}

class_alias(__NAMESPACE__ . '\ControllerItems', '\ControllerExtensionKaExtensionsKaLibTestItems');
