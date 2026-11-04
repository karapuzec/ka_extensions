<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	A base class for list pages in admin back-end. Optional, it mirrors ControllerAdminForm:
	- the default list page of the library ($use_default_list = true), deletion is on ($enable_delete = true);
	- deletion (delete(), deleteRecord()) needs the 'modify' permission of the page (PAGE_ROUTE).
	The 'access' permission of the page is checked by OpenCart before the controller is called.

	@package Controller
*/
abstract class ControllerAdminList extends ControllerList {

	protected $enable_delete = true;
	protected $use_default_list = true;

	protected function validateDelete() {

		if (!$this->user->hasPermission('modify', static::PAGE_ROUTE)) {
			$this->addTopMessage($this->language->get('error_permission'), 'E');
			return false;
		}

		return parent::validateDelete();
	}
}
