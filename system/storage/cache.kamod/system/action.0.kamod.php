<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_extensions/system/engine/action.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\engine;

require_once(__DIR__ . '/action.1.kamod.php');

class Action extends \Action_kamod  {

	public function execute($registry, array $args = array()) {

		// $this->route = 'extension/ka_extensions/ka_multivendor/vendor/product'
		
		if (!empty($this->route)) {
		
			$clear_route = preg_replace('/[^a-zA-Z0-9_\/]/', '', $this->route);
			$last_slash_pos = strrpos($clear_route, '/');
			
			/*
				Possible class paths
				\test           - root
				\test\test2     - index with namespace
				\test\test2\act - action with namespace
			*/
			if ($last_slash_pos !== false) {

				// check the route without action function
				//
				$namespace = substr($clear_route, 0, $last_slash_pos);
				$class = '\\' . str_replace('/', '\\', $namespace) . '\\Controller' . str_replace('_', '', substr($clear_route, $last_slash_pos+1));

				if (!class_exists($class)) {
				
					// check the route with action function
					//
					$last_slash_pos = strrpos($namespace, '/');
					if ($last_slash_pos !== false) {
						$namespace = substr($namespace, 0, $last_slash_pos);
						$class = '\\' . str_replace('/', '\\', $namespace) . '\\Controller\\' . str_replace('_', '', substr($clear_route, $last_slash_pos+1));

						class_exists($class);
					}
				}
			}
			
		} elseif (is_null($this->route)) {
			// we init the route to prevent a default warning in a default Opencart action class
			$this->route = '';
		}
	
		$loader = $registry->get('load');
		
		$loader->increaseRenderLevel();
		$result = parent::execute($registry, $args);
		$loader->decreaseRenderLevel();
		
		return $result;
	}
}