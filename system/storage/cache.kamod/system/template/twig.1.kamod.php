<?php
/*
	This file was patched by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file:
	system/library/template/twig.php
	
	The following patches were applied:
	system/library/extension/ka_extensions/ka_extensions/system/library/template/twig.php.xml
*/
?><?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/template/twig.php
*/
namespace Template;
class Twig_kamod  {
	private $twig;
	private $data = array();
	
	public function __construct() {
		// include and register Twig auto-loader
		include_once DIR_SYSTEM . 'library/template/Twig/Autoloader.php';
		
		\Twig_Autoloader::register();	
		
		// specify where to look for templates
		$loader = new \Twig_Loader_Filesystem(DIR_TEMPLATE);	
		
		// initialize Twig environment
		
			//ka-extensions: pass the twig to a child class to add a custom extension
			$loader = $this->replaceLoader($loader);
			$this->twig = new \Twig_Environment($loader, array('autoescape' => false));
			//ka-extensions: extend twig with our functions
			$this->extendTwig($this->twig);
	}
	
	public function set($key, $value) {
		$this->data[$key] = $value;
	}
	
	public function render($template) {
		try {
			// load template
			$template = $this->twig->loadTemplate($template . '.twig');
			
			return $template->render($this->data);
		} catch (Exception $e) {
			trigger_error('Error: Could not load template ' . $template . '!');
			exit();	
		}	
	}	
}
