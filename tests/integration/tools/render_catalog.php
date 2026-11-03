<?php
/*
	A separate storefront request rendering a template, for the tests of compiled templates (a template
	class compiled once stays loaded in a process) and of the theme cache.

	php render_catalog.php <platform dir> <store dir> <template route> [<theme>]

	Without a theme the theme of the store settings is used (the default theme of the test store).
	Several routes separated by commas are rendered one after another in this request (one Template object
	per view as in the storefront), the outputs are joined with '|'.
*/

list(, $platform_dir, $store_dir, $route) = $argv;
$theme = isset($argv[4]) ? $argv[4] : '';

require_once($platform_dir . '/vendor/autoload.php');

$oc = new \KaTesting\OcApp('catalog', $store_dir);
error_reporting(E_ALL & ~E_DEPRECATED);

if ($theme !== '') {
	// the active theme of the store (an enabled theme extension, see catalog/controller/event/theme.php)
	$oc->get('config')->set('config_theme', $theme);
	$oc->get('config')->set('theme_' . $theme . '_status', 1);
}

$output = array();
foreach (explode(',', $route) as $view_route) {
	$output[] = $oc->get('load')->view($view_route);
}
echo implode('|', $output);
