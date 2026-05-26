<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

class Response extends \Response {

	protected $ka_redirect_disable_level = 0;
	protected $ka_last_redirect_url;
	protected $ka_last_redirect_status;

	/**
		Prevent immediate redirects from the page.
		Can be called multiple times to increase the redirect suppression level.
	*/
	public function disableRedirect() {
		$this->ka_redirect_disable_level++;
	}
	
	/**
		Re-enable immediate redirects when the suppression level reaches zero.
		Otherwise, decreases the suppression level by one.
	*/
	public function enableRedirect($url = null, $status = 302) {
		$this->ka_redirect_disable_level = max(0, $this->ka_redirect_disable_level - 1);
		
		if (!is_null($url)) {
			$this->ka_last_redirect_url    = $url;
			$this->ka_last_redirect_status = $status;
		}
		
		if ($this->ka_redirect_disable_level == 0) {
			if (!empty($this->ka_last_redirect_url)) {
				$url    = $this->ka_last_redirect_url;
				$status = $this->ka_last_redirect_status;

				$this->ka_last_redirect_url = null;
				$this->ka_last_redirect_status = null;
				
				parent::redirect($url, $status);
			}
		}
	}

	/**
		When the redirect is disabled, the code throws the 'ExceptionResponse' exception that should be catched
		by a caller who requested disabling the redirect
	*/
	public function redirect($url, $status = 302) {
		if ($this->ka_redirect_disable_level <= 0) {
			parent::redirect($url, $status);
		} else {
			$this->ka_last_redirect_url    = $url;
			$this->ka_last_redirect_status = $status;
			throw new ExceptionRedirect("Requested redirect to $url with status $status");
		}
	}
}