<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/controller/extension/ka_extensions/ka_extensions/controller/startup/permission.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

require_once(__DIR__ . '/controllerstartuppermission.1.kamod.php');

class ControllerStartupPermission extends \ControllerStartupPermission_kamod  {

	public function index() {

		// leave handling of an empty route to the parent method
		//
		if (empty($this->request->get['route'])) {
			return parent::index();
		}
		
		// simple path splitting
		//
		$parts = explode('/', $this->request->get['route']);

		// give access to any controller under the extension directory
		//
		if ($parts[0] == 'extension') {
			$route = $this->request->get['route'];
			if (file_exists(DIR_APPLICATION . '/controller/' . $route . '.php')) {
				if ($this->user->hasPermission('access', $route)) {
					return;
				}
			} else {
				array_pop($parts);
				$route = implode('/', $parts);
				if (file_exists(DIR_APPLICATION . '/controller/' . $route . '.php')) {
					if ($this->user->hasPermission('access', $route)) {
						return;
					}
				}
			}
		}
		
		return parent::index();
	}
}
