<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: admin/controller/extension/ka_extensions/ka_extensions/controller/tool/log.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

require_once(__DIR__ . '/controllertoollog.1.kamod.php');

class ControllerToolLog extends \ControllerToolLog_kamod  {

	use TraitController;

	public function kamod_log() {		
		$this->load->language('tool/log');
		
		$this->document->setTitle($this->language->get('Kamod Errors'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('Kamod Errors'),
			'href' => $this->url->link('tool/log/kamod_log', 'user_token=' . $this->session->data['user_token'], true)
		);

		$file = DIR_LOGS . KamodBuilder::LOG_ERRORS_FILENAME;
		if (file_exists($file)) {
			$data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		return $this->showPage('extension/ka_extensions/ka_extensions/tool/log', $data);
	}
}