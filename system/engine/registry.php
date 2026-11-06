<?php
final class Registry {
	private $data = array();

	public function get($key) {
//karapuz: after:n get(
		if (strncasecmp('kamodel_', $key, 8) === 0) {
			/*
				@deprecated The 'kamodel_' shorthand ($this->kamodel_xxx in a standard OpenCart class, it gives
				model_extension_ka_extensions_xxx). Use $this->load->kamodel('...') and keep the returned model
				in a variable or a property. The shorthand still works, KA_DEBUG_DEPRECATED reports its use.
			*/
			assert(!defined('KA_DEBUG_DEPRECATED'), "The 'kamodel_' shorthand of the registry is deprecated, use \$this->load->kamodel().");
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