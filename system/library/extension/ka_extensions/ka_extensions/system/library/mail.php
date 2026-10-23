<?php

namespace extension\ka_extensions\library;

/**
	@internal
*/
class Mail extends \Mail {

	protected $headers;
	
	// when true, exceptions of the send() method are logged and suppressed
	protected $suppress_exceptions = true;

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
			
			if (!$this->suppress_exceptions) {
				throw $e;
			}
			return;
		}	

		if ($log_emails) {
			$this->logEmail('success');
		}
	}
	
	
	public function setHeaders($headers) {
		$this->headers = $headers;
	}
	
	
	/*
		Defines if exceptions of the send() method are suppressed (default) or passed to the caller
		after logging. Returns the previous value.
	*/
	public function setSuppressExceptions($suppress) {
		$previous = $this->suppress_exceptions;
		$this->suppress_exceptions = (bool)$suppress;
		
		return $previous;
	}
}