<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/model/extension/ka_extensions/ka_extensions/model/design/translation.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

require_once(__DIR__ . '/modeldesigntranslation.1.kamod.php');

class ModelDesignTranslation extends \ModelDesignTranslation_kamod  {

	public function addTranslation($data) {

		if (!empty($data['is_html'])) {
			$data['value'] = html_entity_decode($data['value'], ENT_QUOTES, 'UTF-8');;
		}
	
		parent::addTranslation($data);
		$translation = $this->db->query("SELECT * FROM `" . DB_PREFIX . "translation`
			WHERE `store_id` = '" . (int)$data['store_id'] . "' AND
			`language_id`    = '" . (int)$data['language_id'] . "' AND 
			`route` = '" . $this->db->escape($data['route']) . "' AND 
			`key`   = '" . $this->db->escape($data['key']) . "' 
		")->row;

		$this->db->query("UPDATE `" . DB_PREFIX . "translation`
			SET is_html = '" . (empty($data['is_html']) ? 0 : 1) . "'
			WHERE translation_id = '" . $translation['translation_id'] . "'
		");
	}
		
	public function editTranslation($translation_id, $data) {

		if (!empty($data['is_html'])) {
			$data['value'] = html_entity_decode($data['value'], ENT_QUOTES, 'UTF-8');;
		}

		parent::editTranslation($translation_id, $data);
		
		$this->db->query("UPDATE `" . DB_PREFIX . "translation`
			SET is_html = '" . (empty($data['is_html']) ? 0 : 1) . "'
			WHERE `translation_id` = '" . (int)$translation_id . "'
		");			
	}
	
	public function getTranslation($translation_id) {
	
		$translation = parent::getTranslation($translation_id);
		if (!empty($translation['is_html'])) {
			$translation['value'] = htmlspecialchars($translation['value'], ENT_COMPAT, 'UTF-8');
		}

		return $translation;
	}
	
	public function getTranslations($data = array()) {
	
		$translations = parent::getTranslations($data);
		
		if (empty($translations)) {
			return $translations;
		}
		
		foreach ($translations as &$trans) {
			if (!empty($trans['is_html'])) {
				$trans['value'] = htmlspecialchars($trans['value'], ENT_COMPAT, 'UTF-8');;
			}
		}
		
		return $translations;
	}
}