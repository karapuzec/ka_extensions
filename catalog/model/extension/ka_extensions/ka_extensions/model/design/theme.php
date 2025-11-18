<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	@internal
*/
class ModelDesignTheme extends \ModelDesignTheme {

	/*
		we store theme modifications on a file system. No need to request them from db.
	*/
	public function getTheme($route, $theme) {
		return array();
	}
}