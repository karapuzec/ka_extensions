<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

class ControllerMarketplaceExtension extends \ControllerMarketplaceExtension {

	use \extension\ka_extensions\TraitController;

	public function index() {

		$this->load->language('extension/extension/ka_extensions');
	
		$this->disableRender();
		parent::index();
		$this->enableRender();
		
		$template = $this->getRenderTemplate();
		$data = $this->getRenderData();

		$files = glob(DIR_APPLICATION . 'controller/extension/*/*/kamod.ini', GLOB_BRACE);
		
		$vendors = [];
		foreach ($files as $file) {
			$vendor = basename(dirname(dirname($file)));
			if ($vendor == 'ka_extensions') {
				continue;
			}
			
			if (isset($vendors[$vendor])) {
				$vendors[$vendor]++;
			} else {
				$vendors[$vendor] = 1;
			}
		}
		
		if (!empty($vendors)) {
			foreach ($vendors as $vendor => $total) {
			
				$url_params = [
					'type'   => 'ka_extensions',
					'vendor' => $vendor,
				];
			
				$data['categories'][] = array(
					'code' => $vendor,
					'text' => $this->language->get('Vendor') . ': ' . $vendor . ' (' . $total .')',
					'href' => $this->url->linka('extension/extension/ka_extensions', $url_params)
				);
			}
		}
		
		$this->response->setOutput($this->load->view($template, $data));
	}
}