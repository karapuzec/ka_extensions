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
	Arrays are stored as JSON as OpenCart does. The values are written by the library class
	\extension\ka_extensions\HiddenSettings, one key at a time.
*/
class ModelSettingSetting extends \ModelSettingSetting {

	/*
		Returns a hidden value of the module or null when it is not stored.
		A wrapper of \extension\ka_extensions\HiddenSettings::get() (it works in the catalog too).

		Example: getConfigHidden('ka_smart_search', 'version')
	*/
	public function getConfigHidden($ext_code, $key) {
		return \extension\ka_extensions\HiddenSettings::get($ext_code, $key);
	}


	/*
		Stores a hidden value of the module, other hidden values of the module are kept.
		A null value removes the key. A wrapper of \extension\ka_extensions\HiddenSettings::set().

		Example: setConfigHidden('ka_smart_search', 'version', '1.0.1.0')
	*/
	public function setConfigHidden($ext_code, $key, $value) {
		\extension\ka_extensions\HiddenSettings::set($ext_code, $key, $value);
	}
}
