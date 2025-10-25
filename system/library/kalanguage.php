<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.22 $ ($Revision: 560 $)
	
	This class is deprecated. Please use \extension\ka_extensions\Language instead.
*/
require_once(modification(DIR_SYSTEM . 'library/extension/ka_extensions/language.php'));

class_alias('\extension\ka_extensions\Language', '\KaLanguage');