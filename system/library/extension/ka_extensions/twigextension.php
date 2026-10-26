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
    
    	$extension = $this;
    
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
            // {{ include_jska('view/javascript/extension/ka_extensions/module/file.js') }}
            //
            new \Twig_SimpleFunction('include_jska', function() use ($extension) {
            	return $extension->includeJska(func_get_args());
            }, array('is_safe' => array('html'))),
            // {{ include_csska('view/stylesheet/extension/ka_extensions/module/file.css') }}
            //
            new \Twig_SimpleFunction('include_csska', function() use ($extension) {
            	return $extension->includeCsska(func_get_args());
            }, array('is_safe' => array('html'))),
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
    
    public function getFilters()
    {
    	$extension = $this;
    	
    	// Twig 1 and 2 have Twig_SimpleFilter, Twig 3 has \Twig\TwigFilter only
    	$filter_class = (class_exists('\Twig_SimpleFilter') ? '\Twig_SimpleFilter' : '\Twig\TwigFilter');
    	
        return array(
            // {{ field.value|ka_escape }} escapes a value for html (an attribute or a textarea). OpenCart 3 keeps
            // posted values html-escaped, the existing entities are not encoded again
            //
            new $filter_class('ka_escape', function($value) use ($extension) {
            	return $extension->escapeValue($value);
            }),
        );
    }
    
    
    protected function escapeValue($value) {
    	if (is_null($value)) {
    		return '';
    	}
    	if (!is_scalar($value)) {
    		return $value;
    	}
    	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8', false);
    }
    
    
    /**
    	Returns <script> tags of js files. $paths are urls as in the src attribute
    	(relative to the page base url).
    */
    protected function includeJska($paths) {
    	$html = '';
    	foreach ($paths as $path) {
    		$html .= '<script type="text/javascript" src="' . $this->getFileUrl($path) . '"></script>' . "\n";
    	}
    	return $html;
    }
    
    
    /**
    	Returns <link> tags of css files. $paths are urls as in the href attribute.
    */
    protected function includeCsska($paths) {
    	$html = '';
    	foreach ($paths as $path) {
    		$html .= '<link rel="stylesheet" type="text/css" href="' . $this->getFileUrl($path) . '" />' . "\n";
    	}
    	return $html;
    }
    
    
    // adds ?v=<file time> when the file is found
    protected function getFileUrl($path) {
    	$file = (\KaGlobal::isAdminArea() ? DIR_APPLICATION : dirname(DIR_APPLICATION) . '/') . $path;
    	return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
    
    
    public function getName()
    {
        return 'Ka Extensions';
    }
}