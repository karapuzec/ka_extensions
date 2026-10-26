<?php
/*
	Test form page of the library tests (ControllerForm).
*/

namespace extension\ka_extensions\ka_lib_test;

class ControllerItem extends \extension\ka_extensions\ControllerForm {

	const PARENT_ROUTE = 'extension/ka_extensions/ka_lib_test/items';
	const PAGE_ROUTE   = 'extension/ka_extensions/ka_lib_test/item';

	protected function onLoad() {
		$this->recordset = $this->load->kamodel('extension/ka_extensions/ka_lib_test/items');
		parent::onLoad();
	}

	protected function getFields() {
		return array(
			'name'   => array('type' => 'text', 'required' => true, 'title' => 'Name'),
			'status' => array('type' => 'checkbox', 'default_value' => 1),
		);
	}
}

class_alias(__NAMESPACE__ . '\ControllerItem', '\ControllerExtensionKaExtensionsKaLibTestItem');
