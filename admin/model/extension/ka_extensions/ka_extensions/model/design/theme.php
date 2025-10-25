<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

class ModelDesignTheme extends \ModelDesignTheme {

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