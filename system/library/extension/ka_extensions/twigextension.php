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
	// keys of js/css files printed by include_jska() and include_csska() for the current response
	protected static $included_files = [];
	
	// the response object of $included_files. The list is started again for another response object
	// (several requests in one process, e.g. in tests)
	protected static $included_response = null;

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
            // printing <script> tags of js files from view/javascript/ in a template:
            //   {{ include_jska('ka_messages/attachments') }}
            //   {{ include_jska('module/a', 'module/b') }}
            //
            new \Twig_SimpleFunction('include_jska', function() use ($extension) {
            	return $extension->includeFiles('js', func_get_args());
            }, array('is_safe' => array('html'))),
            // printing <link> tags of css files from view/stylesheet/ (catalog: the theme stylesheet directory)
            //   {{ include_csska('ka_messages/attachments') }}
            //
            new \Twig_SimpleFunction('include_csska', function() use ($extension) {
            	return $extension->includeFiles('css', func_get_args());
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
    
    /**
    	Returns html tags of js or css files for include_jska() and include_csska().
    	
    	$type  - 'js' or 'css'
    	$paths - file paths without the extension, relative to the javascript (stylesheet) directory.
    	         Arrays of paths are accepted too.
    	
    	A file printed once for the page (response) is not printed again. A missing file is
    	written to the log and an html comment is returned instead of the tag.
    */
    public function includeFiles($type, $paths) {
    
    	$registry = \KaGlobal::getRegistry();
    	$response = (!empty($registry) && $registry->has('response')) ? $registry->get('response') : null;
    	if ($response !== static::$included_response) {
    		static::$included_response = $response;
    		static::$included_files    = [];
    	}
    
    	$html = '';
    	foreach ($this->flattenPaths($paths) as $path) {
    	
    		$path = $this->normalizePath($path, $type);
    		if (empty($path)) {
    			continue;
    		}
    		
    		$file = $this->findFile($type, $path);
    		if (empty($file)) {
    			$key = $type . ':' . $path;
    			if (empty(static::$included_files[$key])) {
    				static::$included_files[$key] = true;
    				$this->logMissingFile($type, $path);
    			}
    			$html .= '<!-- include_' . $type . 'ka: ' . $path . ' not found -->' . "\n";
    			continue;
    		}
    		
    		if (!empty(static::$included_files[$file['file']])) {
    			continue;
    		}
    		static::$included_files[$file['file']] = true;
    		
    		$html .= $this->getFileTag($type, $file['url'] . '?v=' . filemtime($file['file'])) . "\n";
    	}
    	
    	return $html;
    }
    
    
    protected function flattenPaths($paths) {
    	$result = [];
    	foreach ((array) $paths as $path) {
    		if (is_array($path)) {
    			$result = array_merge($result, $this->flattenPaths($path));
    		} else {
    			$result[] = (string) $path;
    		}
    	}
    	return $result;
    }
    
    
    /**
    	Returns the path without the leading slash and the file extension, or an empty string for
    	a wrong path (empty, with '..' or unsafe characters).
    */
    protected function normalizePath($path, $type) {
    
    	$path = trim(str_replace('\\', '/', $path), '/ ');
    	$path = preg_replace('/\.' . $type . '$/i', '', $path);
    	
    	if (!strlen($path) || strpos($path, '..') !== false || !preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $path)) {
    		if (strlen($path)) {
    			$this->logMissingFile($type, $path);
    		}
    		return '';
    	}
    	
    	return $path;
    }
    
    
    /**
    	Returns the directories where the file is searched, in the order of priority. Paths are relative
    	to the application directory (DIR_APPLICATION) and to the url of the area.
    	
    	admin:   view/javascript/, view/stylesheet/
    	catalog: view/theme/<theme>/javascript/, view/javascript/;
    	         view/theme/<theme>/stylesheet/, view/theme/default/stylesheet/
    */
    protected function getFileDirs($type) {
    
    	if (\KaGlobal::isAdminArea()) {
    		return array($type == 'js' ? 'view/javascript/' : 'view/stylesheet/');
    	}
    	
    	$theme = $this->getThemeDir();
    	
    	if ($type == 'js') {
    		$dirs = array('view/theme/' . $theme . '/javascript/', 'view/javascript/');
    	} else {
    		$dirs = array('view/theme/' . $theme . '/stylesheet/', 'view/theme/default/stylesheet/');
    	}
    	
    	return array_unique($dirs);
    }
    
    
    protected function getThemeDir() {
    	$config = \KaGlobal::getRegistry()->get('config');
    	
    	$theme = $config->get('config_theme');
    	$dir   = $config->get('theme_' . $theme . '_directory');
    	if (empty($dir)) {
    		$dir = ($theme == 'default' || empty($theme)) ? 'default' : $theme;
    	}
    	
    	return $dir;
    }
    
    
    /**
    	Returns the url prefix of files of the current area. The url is relative to the base url of
    	the page (the <base> tag of admin and catalog headers) like urls of the OpenCart scripts.
    */
    protected function getUrlPrefix() {
    	if (\KaGlobal::isAdminArea()) {
    		return '';
    	}
    	return 'catalog/';
    }
    
    
    /**
    	Returns array('file' => full file path, 'url' => file url) or null when the file is not found.
    */
    protected function findFile($type, $path) {
    
    	foreach ($this->getFileDirs($type) as $dir) {
    		$file = DIR_APPLICATION . $dir . $path . '.' . $type;
    		if (is_file($file)) {
    			return array(
    				'file' => $file,
    				'url'  => $this->getUrlPrefix() . $dir . $path . '.' . $type,
    			);
    		}
    	}
    	
    	return null;
    }
    
    
    protected function getFileTag($type, $url) {
    	$url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    	if ($type == 'js') {
    		return '<script type="text/javascript" src="' . $url . '"></script>';
    	}
    	return '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
    }
    
    
    protected function logMissingFile($type, $path) {
    	$registry = \KaGlobal::getRegistry();
    	if (!empty($registry) && $registry->has('log')) {
    		$registry->get('log')->write('WARNING: include_' . $type . 'ka: file is not found: ' . $path);
    	}
    }
    
    
    public function getName()
    {
        return 'Ka Extensions';
    }
}