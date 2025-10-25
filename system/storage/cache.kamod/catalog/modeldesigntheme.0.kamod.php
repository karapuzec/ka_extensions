<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: catalog/model/extension/ka_extensions/ka_extensions/model/design/theme.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

require_once(__DIR__ . '/modeldesigntheme.1.kamod.php');

class ModelDesignTheme extends \ModelDesignTheme_kamod  {

	/*
		we store theme modifications on a file system. No need to request them from db.
	*/
	public function getTheme($route, $theme) {
		return array();
	}
}