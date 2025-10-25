<?php

class ControllerTest extends \KaController {

	function index() {

		$data = array(
			'quantity' => 100,
		);
		
		$this->kadb->queryUpdate('product', $data, 'product_id = 40');
		return;
		
		/*
	
		$ka_mail = new \KaMail($this->registry);
		
		$ka_mail->data['var1'] = 'this is var1';
		$ka_mail->data['var2'] = 'this is var2';
		
		$ka_mail->send($this->config->get('config_email'), $this->config->get('config_email'),
			$this->language->get('Test Email'), 'tests/mail/mail1'
		);
		
		die;
	
//		echo strrev("\x69\x73\x4b\x61\x49\x6e\x73\x74\x61\x6c\x6c\x65\x64"); die;
/*	
		for($i=0; $i< strlen('isKaInstalled'); $i++) {
			echo dechex(ord(substr('isKaInstalled', $i, 1))) . ' ';
		}
		
		die;
*/	
		$x = \KaGlobal::isKaInstalled('csv_product_import');
		var_dump($x);
		die('end');
	}
	
}