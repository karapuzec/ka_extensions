<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

class Log extends \Log {

	public function writeka($message, $type = null) {
	
		if (empty($type)) {
			$this->write($message);
			return;
		}
		
		$target_file = DIR_LOGS . $type . '.log';
		
		$log_record = date('Y-m-d G:i:s') . ' - ' . print_r($message, true) . "\n";
		
		file_put_contents($target_file, $log_record, FILE_APPEND);
	}
}