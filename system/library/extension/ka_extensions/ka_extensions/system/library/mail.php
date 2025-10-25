<?php

namespace extension\ka_extensions\library;

class Mail extends \Mail {

	public function addNamedAttachment($filename, $name) {
		$this->attachments[$name] = $filename;
	}

	public function addAttachment($filename) {
		$this->addNamedAttachment($filename, basename($filename));
	}
	
	
	protected function logEmail($msg) {
		static $log = null;
	
		if (is_null($log)) {
			$log = new \Log('ka_mail_log.log');
		}
		$log->write($msg);
	}
	
	
	public function send() {

		$config = \KaGlobal::getRegistry()->get('config');
		$log_emails = $config->get('ka_extensions_log_emails');
		
		if ($log_emails) {
			
			$msg = "Email is being sent from (" . $this->from . ") to (" . var_export($this->to, true) . ") with subject '" . $this->subject . "'";
			$this->logEmail($msg);
		}
		
		try {
			parent::send();
		} catch (\Exception $e) {

			$error = "Email submission failed with error: " . $e->getMessage();
		
			if ($log_emails) {
				$this->logEmail($error);
			}
		
			$log = \KaGlobal::getRegistry()->get('log');
			$log->write($error);
			return;
		}	

		if ($log_emails) {
			$this->logEmail('success');
		}
	}
}