<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/controller/extension/ka_extensions/ka_extensions/controller/user/user_permission.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	Here we hide kamod files and any files not matching *.php
*/

namespace extension\ka_extensions;

require_once(__DIR__ . '/controlleruseruserpermission.1.kamod.php');

class ControllerUserUserPermission extends \ControllerUserUserPermission_kamod  {

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