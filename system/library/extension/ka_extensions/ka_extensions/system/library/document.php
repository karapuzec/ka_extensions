<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	Ka Extensions methods of the OpenCart document ($this->document): meta tags of the page header
	and labels of the library JS (ka_extensions.setLabels() in the footer).
*/
class SystemLibraryDocument extends \Document {

	protected $ka_meta = array();
	protected $ka_labels = array();

	/**
		Adds a meta tag to the page header: <meta name="$name" content="$content" />
	*/
	public function addKaMeta($name, $content) {
		$this->ka_meta[$name] = $content;
	}

	public function getKaMeta() {
		return $this->ka_meta;
	}

	/**
		Adds labels to the library JS of the page: an array of key => text, later values override earlier ones.
	*/
	public function addKaJsLabels($data) {
		$this->ka_labels = array_merge($this->ka_labels, $data);
	}

	public function getKaJsLabels() {
		return $this->ka_labels;
	}
}
