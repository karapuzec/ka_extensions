<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	@internal
*/
class Log extends \Log {

	/**
		$message - string or array 
		$type    - name of the log file without the extension. Usually it is the extension name.
		           Messages without the type are recorded to the standard log file.
	*/
	public function writeka($message, $type = null) {
	
		if (is_array($message)) {
			$message = var_export($message, true);
		}
	
		if (empty($type)) {
			$this->write($message);
			return;
		}
		
		$target_file = DIR_LOGS . $type . '.log';
		
		$log_record = date('Y-m-d G:i:s') . ' - ' . print_r($message, true) . "\n";
		
		file_put_contents($target_file, $log_record, FILE_APPEND);
	}
}