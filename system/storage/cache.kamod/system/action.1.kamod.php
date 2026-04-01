<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_exam/system/engine/action.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_exam;

/**
* Action class
*/
require_once(__DIR__ . '/action.2.kamod.php');

class Action_kamod extends \Action_kamod  {
	
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
