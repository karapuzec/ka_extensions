<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	A base class for form pages in admin back-end. Optional.
*/
class ControllerAdminForm extends ControllerForm {

  	protected function validateModify() {
		$route = $this->request->get['route'];
		
    	if (!$this->user->hasPermission('modify', $route)) {
      		$this->addTopMessage($this->language->get('error_permission'), 'E');
      		return false;
    	}
    	
	  	return parent::validateModify();
  	}	
}
