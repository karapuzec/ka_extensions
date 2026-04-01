<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_exam/system/library/db.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_exam;

require_once(__DIR__ . '/db.2.kamod.php');

class DB_kamod extends \DB_kamod  {

	protected $number_of_queries;

	public function query($sql) {
		$start_time = microtime(true);
		$result = parent::query($sql);
		$end_time = microtime(true);
		$total = $end_time - $start_time;
		if ($total > 0.01) {
			Logger::log(round($total, Config::$precision) ." - SQL: " . $sql);
		}
		return $result;
	}

}