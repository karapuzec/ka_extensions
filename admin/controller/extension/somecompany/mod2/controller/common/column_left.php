<?php

/* mod 2 */

namespace extension\somecompany\mod2;

class ControllerCommonColumnLeft extends \ControllerCommonColumnLeft {
	use \extension\ka_extensions\TraitController;

	public function index() {
	
		$this->load->kaDisableRender('common/column_left');
		parent::index();
		$this->load->kaEnableRender('common/column_left');
		$data = $this->getRenderData();
		$template = $this->getRenderTemplate();

		return $this->load->view($template, $data);
	}
}