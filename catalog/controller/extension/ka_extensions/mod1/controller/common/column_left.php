<?php
/* mod 1 */
namespace extension\ka_extensions\mod1;

class ControllerCommonColumnLeft extends \ControllerCommonColumnLeft {
	use \extension\ka_extensions\TraitController;

	public function index() {
	
		$this->load->disableRender('common/column_left');
		parent::index();
		$this->load->enableRender('common/column_left');
		$data = $this->getRenderData();
		$template = $this->getRenderTemplate();

		return $this->load->view($template, $data);
	}
}