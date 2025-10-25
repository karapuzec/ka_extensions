<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	Here we hide kamod files and any files not matching *.php
*/

namespace extension\ka_extensions;

class ControllerUserUserPermission extends \ControllerUserUserPermission {

	use \extension\ka_extensions\TraitController;

	protected function getForm() {
	
		$this->disableRender();
		parent::getForm();
		$this->enableRender();
		
		$template = $this->getRenderTemplate();
		$data = $this->getRenderData();

		$remove_controllers = true;
		
		foreach ($data['permissions'] as $kp => $kv) {
			if ($remove_controllers) {
				if (strpos($kv, '/controller/')) {
					unset($data['permissions'][$kp]);
				}
			}
			if (!file_exists(DIR_APPLICATION . 'controller/' . $kv . '.php')) {
				unset($data['permissions'][$kp]);
			}
		}
		
		$this->response->setOutput($this->load->view($template, $data));
	}
}