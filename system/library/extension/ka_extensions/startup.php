<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

// safe mode, disable all kamod features for the current admin session
//
if (defined('DIR_CATALOG')) {
	$ka_safe_mode_code = '';

	if (!empty($_COOKIE['ka_safe_mode_code'])) {
		$ka_safe_mode_code = $_COOKIE['ka_safe_mode_code'];
		
	} elseif (!empty($_GET['route']) && $_GET['route'] == 'ka_safe_mode' && !empty($_GET['code'])) {
		setcookie("ka_safe_mode_code", $_GET['code']);
		$ka_safe_mode_code = $_GET['code'];
	}	
	
	if (!empty($ka_safe_mode_code)) {

		$_ = [];
		@include(DIR_CONFIG . 'kamod.php');

		if (!empty($_['safe_mode_code']) && $_['safe_mode_code'] == $ka_safe_mode_code) {

			if (!defined('IS_KAMOD_SAFE_MODE')) {
				define('IS_KAMOD_SAFE_MODE', 1);
			}
		
;			include_once(DIR_SYSTEM . 'engine/action.php');
;			include_once(DIR_SYSTEM . 'engine/loader.php');

			function safeLibrary($class) {
				$file = DIR_SYSTEM . 'library/' . str_replace('\\', '/', strtolower($class)) . '.php';

				if (is_file($file)) {
;					include_once($file);

					return true;
				} else {
					return false;
				}
			}
			spl_autoload_register('\extension\ka_extensions\safeLibrary', true, true);

			return;
		}
	}
}

// if the extension was included twice for some reason
if (class_exists('KamodLockedException')) {
	return;
}

class KamodLockedException extends \Exception {};
class KamodFailedException extends \Exception {};

// replace the original Opencart loader with our loader supporting kamod cache
//
try {
	include_once(__DIR__ . '/autoloader.php');
	$kamod_autoloader = new Autoloader();

} catch (KamodLockedException $e) {

	if (defined('KAMOD_DEBUG')) {
		// it is ok to skip the locked cache in development
	} elseif (KaGlobal::isAdminArea()) {
		echo "Kamod cache is locked. Please reload the page.";
	} else {
//		die('We are rebuilding the store cache. Please try again in several minutes.');
	}

} catch (KamodFailedException $e) {

	if (APPLICATION == 'Admin') {
		echo "Kamod malfunction: " . $e->getMessage();
	} else {
//		die('Sorry, the store is not operable at this moment.');
	}
	
} catch (\Throwable $e) {

	// record the failure event to a log file
	file_put_contents(DIR_LOGS . "kamod.log", date('Y-m-d G:i:s') . ": Ka Extensions autoloader failed (Error: " . $e->getMessage() . " at line " . $e->getLine() . " in file " . $e->getFile() . ")\n", FILE_APPEND);

	if (defined('KAMOD_DEBUG')) {
	
		die("KAMOD DEBUG: " . $e->getMessage() . " at line " . $e->getLine() . " in file " . $e->getFile());
		
	} elseif (KaGlobal::isAdminArea()) {	
		echo ("WARNING: The store is not operating properly. Something wrong. Kamod error: " . $e->getMessage());	
	} else {
//		die('Sorry, the store is not operable at this moment.');
	}
}