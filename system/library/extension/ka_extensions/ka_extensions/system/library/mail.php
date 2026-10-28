<?php

namespace extension\ka_extensions\library;

/**
	@internal
*/
class Mail extends \Mail {

	protected $headers;
	
	// when true, exceptions of the send() method are logged and suppressed
	protected $suppress_exceptions = true;

	// inline images added by addInlineImage(): [<content id> => ['mime' => <image mime type>]].
	// The patched mail adaptors (mail/mail.php.xml, mail/smtp.php.xml) send them as image/* parts with
	// 'Content-Disposition: inline' inside multipart/related. Without them the email is built as before.
	protected $ka_inline_images = [];

	public function addNamedAttachment($filename, $name) {
		$this->attachments[$name] = $filename;
	}

	public function addAttachment($filename) {
		$this->addNamedAttachment($filename, basename($filename));
	}

	/*
		Adds an image shown in the html part of the email by <img src="cid:<$cid>">.

		$filename - full path to the image file
		$cid      - content id and file name of the image, allowed characters: A-Z a-z 0-9 . _ -
		$mime     - image/png, image/jpeg, image/gif or image/webp; detected from the file when empty

		The file is always added as a named attachment. Returns true when it is marked as an inline image,
		false when it stays a regular attachment (not a supported image or a wrong content id).
	*/
	public function addInlineImage($filename, $cid, $mime = '') {

		$this->addNamedAttachment($filename, $cid);

		if (empty($mime) && is_file($filename)) {
			$info = @getimagesize($filename);
			if (!empty($info['mime'])) {
				$mime = $info['mime'];
			}
		}

		$allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
		if (!in_array($mime, $allowed, true) || !preg_match('/^[A-Za-z0-9._\-]+$/', $cid) || is_numeric($cid)) {
			unset($this->ka_inline_images[$cid]);
			return false;
		}

		$this->ka_inline_images[$cid] = ['mime' => $mime];

		return true;
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