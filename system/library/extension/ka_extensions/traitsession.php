<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

trait TraitSession {

	protected function setSession($key, $value) {

		$class = get_class($this);
	
		if (!isset($this->session->data["ka_session_$class"])) {
			$this->session->data["ka_session_$class"] = array();
		}
		
		$this->session->data["ka_session_$class"][$key] = $value;
	}
	
	
	protected function &getSession($key) {
		$class = get_class($this);
		
		if (!isset($this->session->data["ka_session_$class"])) {
			$this->session->data["ka_session_$class"] = array();
		}
		
		if (!isset($this->session->data["ka_session_$class"][$key])) {
			$this->session->data["ka_session_$class"][$key] = null;
			return $this->session->data["ka_session_$class"][$key];
		}
		
		return $this->session->data["ka_session_$class"][$key];
	}

	
}