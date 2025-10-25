<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/controller/extension/ka_extensions/mod1/controller/common/column_left.php
*/
/* mod 1 */
namespace extension\ka_extensions\mod1;

require_once(__DIR__ . '/controllercommoncolumnleft.1.kamod.php');

class ControllerCommonColumnLeft extends \ControllerCommonColumnLeft_kamod  {
	use \extension\ka_extensions\TraitController;

	public function index() {
	
		$this->disableRender('common/column_left');
		parent::index();
		$this->enableRender('common/column_left');
		$data = $this->getRenderData();
		
		$template = $this->getRenderTemplate();

		return $this->load->view($template, $data);
	}
}