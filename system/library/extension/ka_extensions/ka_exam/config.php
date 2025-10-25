<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_exam;

class Config {

	static public $precision = 3;
	
	static public function isLoggerEnabled() {
		if (defined('KA_EXAM_LOG_ENABED')) {
			return true;
		}
		
		return false;			
	}
}