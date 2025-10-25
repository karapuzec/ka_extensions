<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

class ControllerCommonHeader extends \ControllerCommonHeader {

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
