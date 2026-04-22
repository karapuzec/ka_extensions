<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\engine;

/* 
	Set our custom exception handler 
*/
$ka_previous_exception_handler = null;

$ka_previous_exception_handler = set_exception_handler(
    function($e) use (&$ka_previous_exception_handler) {

		$message = $e->getMessage();
		$file = $e->getFile();
		$line = $e->getLine();

		$msg = ' Exception: ' . $message . ' in ' . $file . ' on line ' . $line;

		echo "Unhandled exception occured. Check ka_errors.log for details. :" . $msg;
		
		$msg = "\n" . '[' . date("Y-m-d H:i:s") . '] ' . $msg . $e->getTraceAsString();

		file_put_contents(DIR_LOGS . 'ka_errors.log', $msg . "\n\n", FILE_APPEND);
		
        if ($ka_previous_exception_handler) {
            call_user_func($ka_previous_exception_handler, $e);
        }
    }
);

/**
	@internal
*/
class Action extends \Action {

	public function execute($registry, array $args = array()) {

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

				$is_class_found = false;				
				if (!class_exists($class)) {

					// check the route with action function
					//
					$last_slash_pos = strrpos($namespace, '/');
					if ($last_slash_pos !== false) {
						$namespace = substr($namespace, 0, $last_slash_pos);
						$class = '\\' . str_replace('/', '\\', $namespace) . '\\Controller\\' . str_replace('_', '', substr($clear_route, $last_slash_pos+1));

						if (class_exists($class)) {
							$is_class_found = true;
						}
					} 
				} else {
					$is_class_found = true;
				}
				
				if ($is_class_found) {
					$plain_class = 'Controller' . preg_replace('/[^a-zA-Z0-9]/', '', $this->route);
					if (!class_exists($plain_class)) {
						class_alias ( $class, $plain_class);
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