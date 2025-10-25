<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/model/extension/ka_extensions/ka_extensions/model/design/theme.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

require_once(__DIR__ . '/modeldesigntheme.1.kamod.php');

class ModelDesignTheme extends \ModelDesignTheme_kamod  {

	public function editTheme($store_id, $theme, $route, $code) {
	
		parent::editTheme($store_id, $theme, $route, $code);
		
		$kamodel_kamod = $this->load->kamodel('extension/ka_extensions/ka_extensions/kamod');
		$kamodel_kamod->rebuildThemeCache();
	}

	public function deleteTheme($theme_id) {
	
		parent::deleteTheme($theme_id);
		
		$kamodel_kamod = $this->load->kamodel('extension/ka_extensions/ka_extensions/kamod');
		$kamodel_kamod->rebuildThemeCache();
	}
}