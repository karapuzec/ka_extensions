<?php
final class Registry {
	private $data = array();

	public function get($key) {
//karapuz: after:n get(
		if (strncasecmp('kamodel_', $key, 8) === 0) {
			$key = 'model_extension_ka_extensions_' . substr($key, 8);
		}
///karapuz	
		return (isset($this->data[$key]) ? $this->data[$key] : null);
	}

	public function set($key, $value) {
		$this->data[$key] = $value;
	}

	public function has($key) {
		return isset($this->data[$key]);
	}
}