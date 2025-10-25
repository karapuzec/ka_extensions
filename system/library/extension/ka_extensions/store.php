<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 269 $)
*/
	
namespace extension\ka_extensions;

class Store {

	public $data = array();
	public $registry;
	public $config;
	public $db;
	
	static $cache = array();

	protected function __construct($store_id = 0) {

		$this->registry = \KaGlobal::getRegistry();
		$this->db = $this->registry->get('db');
	
		// get store information by id
		//
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "store WHERE store_id = '" . (int)$store_id . "'");

		if (!empty($query->row)) {
			$this->data['store_name'] = $query->row['name'];
			$this->data['sender']     = $query->row['name'];
			$this->data['store_url']  = $query->row['url'];
			$this->data['store_login_url']  = $query->row['url'] . 'index.php?route=account/login';

			$query =  $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "setting WHERE 
				store_id = '" . (int)$store_id . "' AND `code` = 'config'"
			);
			$this->config = new \Config();
			
			foreach ($query->rows as $setting) {
				if (!$setting['serialized']) {
					$this->config->set($setting['key'], $setting['value']);
				} else {
					$this->config->set($setting['key'], json_decode($result['value'], true));
				}
			}
			
		} else {
			$this->config = $this->registry->get('config');
			$this->data['store_name'] = $this->config->get('config_name');
			$this->data['sender']     = $this->data['store_name'];
			$url = (defined('HTTP_CATALOG')) ? HTTP_CATALOG : HTTP_SERVER;
			$this->data['store_url']        = $url;
			$this->data['store_login_url']  = $url . 'index.php?route=account/login';
			$this->config->set('config_url', $url);
		}
	}

	
	static public function getStoreInfo($store_id) {

		if (!empty(self::$cache[$store_id])) {
			return self::$cache[$store_id]->data;
		}

		self::$cache[$store_id] = new Store($store_id);
		return self::$cache[$store_id]->data;
	}
	
	
	static public function getStoreConfig($store_id) {

		if (!empty(self::$cache[$store_id])) {
			return self::$cache[$store_id]->config;
		}
		
		self::$cache[$store_id] = new Store($store_id);		
		return self::$cache[$store_id]->config;
	}
}