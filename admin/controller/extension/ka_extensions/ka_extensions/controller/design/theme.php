<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

/**
	@internal
*/
class ControllerDesignTheme extends \ControllerDesignTheme {

	public function index() {

		// texts of the Ka Extensions warning on the theme editor page (design/theme.twig)
		$this->load->language('extension/ka_extensions/ka_extensions/theme_editor');

		return parent::index();
	}
}
