<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/controller/extension/ka_extensions/ka_extensions/controller/common/footer.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

require_once(__DIR__ . '/controllercommonfooter.1.kamod.php');

class ControllerCommonFooter extends \ControllerCommonFooter_kamod  {

	use TraitController;

	public function index() {

		$this->disableRender();
		parent::index();
		$this->enableRender();

		$data     = $this->getRenderData();
		$template = $this->getRenderTemplate();
	
		if (!defined('KA_DISABLE_FORM_VALIDATION')) {
			$data['ka_enable_form_validation'] = true;
			
			$this->load->language('extension/ka_extensions/common');
			
			$labels = array(
				'txt_warning' => $this->language->get('txt_warning'),
				'txt_error'   => $this->language->get('txt_error'),
				'txt_success' => $this->language->get('txt_success'),
				'txt_info'    => $this->language->get('txt_info'),
				'txt_field_validation_error' => $this->language->get('txt_field_validation_error'),
			);
				
			$this->document->addKaJsLables($labels);
		}
		
		$data['ka_js_labels'] = $this->document->getKaJsLabels();
	
		return $this->load->view($template, $data);
	}
}