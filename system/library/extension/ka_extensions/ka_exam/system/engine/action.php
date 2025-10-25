<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_exam;

/**
* Action class
*/
class Action extends \Action {
	
	/**
	 * 
	 *
	 * @param	object	$registry
	 * @param	array	$args
 	*/	
	public function execute($registry, array $args = array()) {
		$start_time = microtime(true);
		$result = parent::execute($registry, $args);
		$end_time = microtime(true);
		$total = $end_time - $start_time;
		if ($total > 0.01) {
			Logger::log(round($total, Config::$precision) . " route (" . $this->route . ")");
		}
		return $result;
	}
}
