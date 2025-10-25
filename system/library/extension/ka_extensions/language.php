<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 269 $)
*/
	
namespace extension\ka_extensions;

class Language {

	public $language = array();
	public $registry;
	public $config;
	static $cache = array();

	protected function __construct($language_id) {

		$this->registry = \KaGlobal::getRegistry();
		$this->db = $this->registry->get('db');

		$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "language`
		 	WHERE language_id = '" . intval($language_id) . "'"
		);

		if (empty($qry->row)) {
			return false;
		}
		
		$this->language_code      = $qry->row['code'];
		$this->language_filename  = $qry->row['code'];
		$this->language_directory = $qry->row['code'];

		$this->language = new \Language($this->language_directory);
		$this->language->load($this->language_filename);
	}

	
	static public function getLanguage($language_id) {
	
		if (!empty(self::$cache[$language_id])) {
			return self::$cache[$language_id]->language;
		}

		self::$cache[$language_id] = new Language($language_id);
		return self::$cache[$language_id]->language;
	}
	
	
	/*
		code - example: "en-gb"
		
		This function is supposed to fetch the langauge_id from setting variables which are defined in codes.
	*/
	static public function getLanguageIdByCode($code) {
	
		$registry = \KaGlobal::getRegistry();
		$db = $registry->get('db');
	
		$language = $db->query("SELECT * FROM " . DB_PREFIX . "language WHERE 
			code = '" . $db->escape($code) . "'
		")->row;
		if (empty($language)) {
			return false;
		}
		
		return $language['language_id'];
	}	
}