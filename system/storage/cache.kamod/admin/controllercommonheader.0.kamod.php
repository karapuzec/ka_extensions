<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/controller/extension/ka_extensions/ka_extensions/controller/common/header.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

require_once(__DIR__ . '/controllercommonheader.1.kamod.php');

class ControllerCommonHeader extends \ControllerCommonHeader_kamod  {

	use TraitController;

	public function index() {

		$this->disableRender();
		parent::index();
		$this->enableRender();
	
		$this->document->addScript('view/javascript/extension/ka_extensions/common.js');
		$this->document->addStyle('view/stylesheet/extension/ka_extensions/stylesheet.css');

		$data     = $this->getRenderData();
		$template = $this->getRenderTemplate();
	
		$kamodel_kamod = $this->load->kamodel('extension/ka_extensions/ka_extensions/kamod');
		
		$last_errors_total = $kamodel_kamod->getLastErrorsTotal();
		$data['kalog_errors_total'] = $last_errors_total;
		$data['kalog_errors_link'] = $this->url->linka('tool/log/kamod_log');
	
		return $this->load->view($template, $data);
	}
}
