<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	This class is used to save and get configuration from files located in DIR_CONFIG directory
	
	@package Service	
*/
class ConfigFile {

	protected $config_file;
	protected $config = [];
	
	/**
		$config_file - file name (without extension) within DIR_CONFIG directory
	*/
	public function __construct(string $config_file) {
	
		$this->config_file = $config_file;
		
		$file = DIR_CONFIG . $this->config_file . '.php';

		if (file_exists($file)) {
			include($file);
		}
		
		$this->config = $_ ?? [];
	}

	/**
		$config - array of configuration variables
	*/
	public function setConfig(array $config) {
		$this->config = $config;
	}
	
	/**
		$overwrite - if configuration should be completed overwritten
	*/
	public function makeConfigFile(bool $overwrite = false): bool {
	
		$file = DIR_CONFIG . $this->config_file . '.php';
	
		if ($overwrite) {
			unlink($file);
		}
		
		if (file_exists($file)) {
			return true;
		}

		return $this->saveConfigFile($this->config);
	}
	
	
	/**
		$config - array of configuration variables
	*/
	protected function saveConfigFile(array $config): bool {
	
$txt = <<<CONFIG
<?php
/* 
	This config file is generated automatically on installing the 'ka_messages' module. 
	You can update variables here manually.
*/

// this value is used for ref-id in emails
\$_['secret_key'] = "{$config['secret_key']}";
CONFIG;

		$file = DIR_CONFIG . $this->config_file . '.php';
	
		return file_put_contents($file, $txt);
	}
	
	/**
		$var - variable name
	*/
	public function get(string $var) {
		return $this->config[$var] ?? null;
	}
}