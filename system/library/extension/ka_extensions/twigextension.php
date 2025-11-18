<?php
/*
  Project : Ka Extensions
  Author  : karapuz <support@ka-station.com>

  Version : 4 ($Revision: 343 $)
  
*/

namespace extension\ka_extensions;

/**
	@internal
*/
class TwigExtension extends \Twig_Extension
{
    public function getFunctions()
    {
    	static $flags = [];
    
        return array(
            new \Twig_SimpleFunction('ka_config_get', function($name) {
            	return \KaGlobal::getRegistry()->get('config')->get($name);
            }),
            new \Twig_SimpleFunction('dir', function($param) {
            	if (is_object($param)) {
            		$text = $param->getTemplateName();
            	} else {
            		$text = $param;
            	}            	
         		return dirname($text) . '/';
            }),        
            new \Twig_SimpleFunction('has_t', function($text) {
            	return KaGlobal::getRegistry()->get('language')->has($text);
            }),
            new \Twig_SimpleFunction('ka_dump', function($value) {
            	return var_export($value, true);
            }),
            new \Twig_SimpleFunction('t', function($text, $args = []) {
            	if (class_exists('\KaGlobal')) {
            		return \KaGlobal::t($text, $args);
            	}            	
            	return $text;
            }),
            new \Twig_SimpleFunction('html_entity_decode', function($text) {
        		return html_entity_decode($text);
            }),
            // getting links inside a template
            //
            new \Twig_SimpleFunction('linka', function($route, $params = '', $is_js = false) {
            	$registry = \KaGlobal::getRegistry();
            	$link = $registry->get('url')->linka($route, $params, true, $is_js);
        		return $link;
            }),
            new \Twig_SimpleFunction('ka_flag', function($key, $value = null) use (&$flags) {
            	
            	if (is_null($value)) {
            		return !empty($flags[$key]);
            	}
            	
            	$flags[$key] = !empty($value);
            }),
            new \Twig_SimpleFunction('get_language_image', function($param) {
            	if (class_exists('\KaGlobal')) {
            		return \KaGlobal::getLanguageImage($param);
            	}
            	return '';
            }),
        );
    }
    
    public function getName()
    {
        return 'Ka Extensions';
    }
}