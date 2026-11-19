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
class ControllerCommonHeader extends \ControllerCommonHeader {

	use TraitController;

	public function index() {

		// the class KaExtensions, the object ka_extensions is created in the header template.
		// Scripts are added before the parent call, it reads the document scripts into the template data
		$this->document->addScript('catalog/view/javascript/extension/ka_extensions/kaextensions.js');
		$this->document->addStyle('catalog/view/javascript/extension/ka_extensions/stylesheet.css');

		$this->disableRender();
		parent::index();
		$this->enableRender();

		$data     = $this->getRenderData();
		$template = $this->getRenderTemplate();

		$data['ka_meta'] = $this->document->getKaMeta();

		return $this->load->view($template, $data);
	}
}
