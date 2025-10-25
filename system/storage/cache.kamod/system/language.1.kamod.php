<?php
/*
	This file was patched by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file:
	system/library/language.php
	
	The following patches were applied:
	system/library/extension/ka_extensions/ka_extensions/system/library/language.php.xml
*/
?><?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/language.php
*/
class Language_kamod  {
	protected $default = 'en-gb';
	protected $directory;
	public $data = array();

	public function __construct($directory = '') {
		$this->directory = $directory;
	}

	public function get($key) {
		return (isset($this->data[$key]) ? $this->data[$key] : $key);
	}
	
	public function set($key, $value) {
		$this->data[$key] = $value;
	}
	
	public function all() {
		return $this->data;
	}
	
	public function load($filename, $key = '') {
		if (!$key) {
			$_ = array();
	
			$file = DIR_LANGUAGE . $this->default . '/' . $filename . '.php';
	
			if (is_file($file)) {
				require($file);
			}
	
			$file = DIR_LANGUAGE . $this->directory . '/' . $filename . '.php';
			
			if (is_file($file)) {
				require($file);
			} 
	
			$this->data = array_merge($this->data, $_);
		} else {
			// Put the language into a sub key
			$this->data[$key] = new Language($this->directory);
			$this->data[$key]->load($filename);
		}
		
		return $this->data;
	}
}
