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
class ControllerCommonFooter extends \ControllerCommonFooter {

	use TraitController;

	public function index() {

		$this->disableRender();
		parent::index();
		$this->enableRender();

		$data     = $this->getRenderData();
		$template = $this->getRenderTemplate();

		// the standard labels of the library JS (ka_extensions.setLabels() in the footer) are passed always, form validation can be turned off
		//
		$this->load->language('extension/ka_extensions/common');

		$labels = array(
			'txt_warning' => $this->language->get('txt_warning'),
			'txt_error'   => $this->language->get('txt_error'),
			'txt_success' => $this->language->get('txt_success'),
			'txt_info'    => $this->language->get('txt_info'),
			'txt_confirm' => $this->language->get('txt_confirm'),
			'txt_field_validation_error' => $this->language->get('txt_field_validation_error'),
		);
		$this->document->addKaJsLabels($labels);

		if (!defined('KA_DISABLE_FORM_VALIDATION')) {
			$data['ka_enable_form_validation'] = true;
		}

		$data['ka_js_labels'] = $this->document->getKaJsLabels();
		// a JSON object for an inline script: no raw quotes, '<', '>', '&' (JSON_HEX_*), '/' escaped
		$data['ka_js_labels_json'] = json_encode((object)$data['ka_js_labels'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

		return $this->load->view($template, $data);
	}
}
