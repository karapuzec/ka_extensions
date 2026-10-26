<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

/**
	Hidden values of a module: the installed version, counters and other values that are not
	fields of the module settings page.

	They are stored in the separate group '<ext_code>_hidden' of the OpenCart setting table under the
	keys '<ext_code>_hidden_<key>', so saving the settings page of the module does not remove them.
	Arrays are stored as JSON by the native OpenCart methods.
*/
class ModelSettingSetting extends \ModelSettingSetting {

	/*
		Returns a hidden value of the module or null when it is not stored.

		Example: getConfigHidden('ka_smart_search', 'version')
	*/
	public function getConfigHidden($ext_code, $key) {

		$code = $ext_code . '_hidden';
		$settings = $this->getSetting($code);

		if (!array_key_exists($code . '_' . $key, $settings)) {
			return null;
		}

		return $settings[$code . '_' . $key];
	}


	/*
		Stores a hidden value of the module, other hidden values of the module are kept.
		A null value removes the key.

		Example: setConfigHidden('ka_smart_search', 'version', '1.0.1.0')
	*/
	public function setConfigHidden($ext_code, $key, $value) {

		$code = $ext_code . '_hidden';

		// editSetting() replaces the whole group, the other keys are written back
		$settings = $this->getSetting($code);

		if (is_null($value)) {
			unset($settings[$code . '_' . $key]);
		} else {
			$settings[$code . '_' . $key] = is_array($value) ? $value : (string)$value;
		}

		$this->editSetting($code, $settings);
	}
}
