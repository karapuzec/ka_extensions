<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\library;

class Language extends \Language {

	protected $kamod_manager;
	
	
	public function __construct($directory = '') {
		parent::__construct($directory);
		$this->kamod_manager = \extension\ka_extensions\KamodManager::getInstance();
	}
	

	public function has($key) {
		return isset($this->data[$key]);
	}
	
	public function get($key) {
	
		if (defined('KALOG_MISSED_LABELS')) {
			if (!isset($this->data[$key])) {
				$route = '';
				if (!empty($_GET['route'])) {
					$route = $_GET['route'];
				}
				$str = "Missed label '$key' at route '" . $route . "'\n";
				file_put_contents(DIR_LOGS . 'missed_labels.log', $str, FILE_APPEND);
			}
		}
		
		return parent::get($key);
	}
	
	
	public function getka($key, $params) {
		
		$text = $this->get($key);
	
		$text = str_replace(array_keys($params), array_values($params), $text);
	
		return $text;
	}	
	
	
	public function load($filename, $key = '') {
	
		if (!empty($key)) {
			return parent::load($filename, $key);
						
		} else {
		
			$_ = array();
			
			$lang_file = $this->kamod_manager->getLanguageFile($this->default . '/' . $filename . '.php');
			if (is_file($lang_file)) {
;				include($lang_file);
			}
			
			$lang_file = $this->kamod_manager->getLanguageFile($this->directory . '/' . $filename . '.php');
			if (is_file($lang_file)) {
;				include($lang_file);
			}
			
			$this->data = array_merge($this->data, $_);
		}
		
		return $this->data;
	}
	
}