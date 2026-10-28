<?php

namespace extension\ka_extensions\library;

/**
	@internal
*/
class Mail extends \Mail {

	protected $headers;
	
	// when true, exceptions of the send() method are logged and suppressed
	protected $suppress_exceptions = true;

	// inline images added by addInlineImage(): [<content id> => ['mime' => <image mime type>, 'name' => <file name>]].
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
		Returns the content id of an inline image file, see \extension\ka_extensions\Mail::generateCid().
	*/
	public static function generateCid($filename) {
		return \extension\ka_extensions\Mail::generateCid($filename);
	}

	/*
		Adds an image shown in the html part of the email by <img src="cid:<generateCid($filename)>">.

		$filename - full path to the image file
		$name     - file name of the image in the email, basename($filename) when empty

		The type (image/png, image/jpeg, image/gif, image/webp) is detected from the file. Returns false
		when the file is not a supported image, it is added as a regular attachment then.
	*/
	public function addInlineImage($filename, $name = '') {

		if (empty($name)) {
			$name = basename($filename);
		}
		$name = str_replace(["\r", "\n", '"'], '', $name);

		$mime = '';
		if (is_file($filename)) {
			$info = @getimagesize($filename);
			if (!empty($info['mime'])) {
				$mime = $info['mime'];
			}
		}

		$allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
		if (!in_array($mime, $allowed, true)) {
			$this->addNamedAttachment($filename, $name);
			return false;
		}

		// the attachment key is the Content-ID of the part, the name is used in the name/filename headers
		$cid = static::generateCid($filename);
		$this->addNamedAttachment($filename, $cid);
		$this->ka_inline_images[$cid] = ['mime' => $mime, 'name' => $name];

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