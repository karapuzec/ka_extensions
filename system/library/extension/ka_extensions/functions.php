<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

if (!function_exists('parse_ini_file') && !is_callable('parse_ini_file')) {

	function parse_ini_file($filename, $process_sections = false, $scanner_mode = INI_SCANNER_NORMAL) {

		$contents = file_get_contents($filename);
		if (empty($contents)) {
			return [];
		}

		$result = parse_ini_string($contents, $process_sections, $scanner_mode);
		
		return $result;
	}

}