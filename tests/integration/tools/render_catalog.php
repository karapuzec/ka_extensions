<?php
/*
	A separate storefront request rendering a template of the default theme, for the tests of compiled
	templates (a template class compiled once stays loaded in a process).

	php render_catalog.php <platform dir> <store dir> <template route>
*/

list(, $platform_dir, $store_dir, $route) = $argv;

require_once($platform_dir . '/vendor/autoload.php');

$oc = new \KaTesting\OcApp('catalog', $store_dir);
error_reporting(E_ALL & ~E_DEPRECATED);
echo $oc->get('load')->view($route);
