<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: catalog/model/design/theme.php
*/
class ModelDesignTheme_kamod extends Model  {
	public function getTheme($route, $theme) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "theme WHERE store_id = '" . (int)$this->config->get('config_store_id') . "' AND theme = '" . $this->db->escape($theme) . "' AND route = '" . $this->db->escape($route) . "'");

		return $query->row;
	}
}