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
class ControllerDesignTranslation extends \ControllerDesignTranslation {

	use \extension\ka_extensions\TraitController;

	protected function getForm() {
	
		$this->disableRender();
		parent::getForm();
		$this->enableRender();
		
		$template = $this->getRenderTemplate();
		$data = $this->getRenderData();

		if (isset($this->request->get['translation_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$translation_info = $this->model_design_translation->getTranslation($this->request->get['translation_id']);
			$data['is_html'] = $translation_info['is_html'];			
		}
		$this->response->setOutput($this->load->view($template, $data));
	}
	

	public function edit() {
	
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			if (!empty($this->request->get['translation_id'])) {
				$this->load->model('design/translation');
				$translation = $this->model_design_translation->getTranslation($this->request->get['translation_id']);
				
				$this->request->post = array_merge($translation, $this->request->post);
			}
		}

		parent::edit();
	}
	
}