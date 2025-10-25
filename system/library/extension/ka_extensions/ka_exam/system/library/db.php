<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_exam;

class DB extends \DB {

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