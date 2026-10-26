<?php
/*
	Bootstrap of the unit tests. Library classes are loaded from this working copy
	(system/library/extension/ka_extensions), OpenCart classes are replaced with small stubs
	(stubs/opencart.php). No store, no database.
*/

error_reporting(E_ALL);
ini_set('display_errors', '1');

$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';
require_once($platform_dir . '/vendor/autoload.php');

define('KA_LIB_ROOT', dirname(__DIR__, 2) . '/');
define('KA_LIB_DIR', KA_LIB_ROOT . 'system/library/extension/ka_extensions/');

define('DB_PREFIX', 'oc_');
define('DIR_SYSTEM', KA_LIB_ROOT . 'system/');
define('DIR_IMAGE', sys_get_temp_dir() . '/ka_lib_unit_image/');
define('VERSION', '3.0.5.1');

require_once(__DIR__ . '/stubs/opencart.php');

// the library classes: extension\ka_extensions\ClassName => <lib>/classname.php
spl_autoload_register(function ($class) {
	$prefix = 'extension\\ka_extensions\\';
	if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
		return;
	}
	$file = KA_LIB_DIR . strtolower(str_replace('\\', '/', substr($class, strlen($prefix)))) . '.php';
	if (is_file($file)) {
		require_once($file);
	}
});

require_once(KA_LIB_DIR . 'kaglobal.php');
class_alias('\extension\ka_extensions\KaGlobal', '\KaGlobal');

// the DB class extended by Ka Extensions (kamod puts it in place of system/library/db.php)
require_once(KA_LIB_DIR . 'ka_extensions/system/library/db.php');

require_once(__DIR__ . '/UnitTestCase.php');
