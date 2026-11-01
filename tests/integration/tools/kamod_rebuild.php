<?php
/*
	A separate admin request rebuilding the kamod cache of the test store, for the storefront tests
	(one process boots only one OpenCart application, the cache is built in the admin area).

	php kamod_rebuild.php <platform dir> <store dir>
*/

list(, $platform_dir, $store_dir) = $argv;

require_once($platform_dir . '/vendor/autoload.php');

$oc = new \KaTesting\OcApp('admin', $store_dir);
// deprecation notices of the builder on new PHP versions are not errors of the tests
error_reporting(E_ALL & ~E_DEPRECATED);
\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
