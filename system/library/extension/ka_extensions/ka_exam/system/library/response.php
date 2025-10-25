<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_exam;

class Response extends \Response {

	public function output() {
		$start_time = microtime(true);
		parent::output();
		$end_time = microtime(true);

		$total = $end_time - $start_time;
		Logger::log(round($total, Config::$precision) ." - final output");
	}
}
