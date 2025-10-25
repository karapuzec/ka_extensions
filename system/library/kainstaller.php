<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.22 $ ($Revision: 560 $)
	
	This class is deprecated. Please use \extension\ka_extensions\Installer instead.
*/
require_once(modification(DIR_SYSTEM . 'library/extension/ka_extensions/controllerinstaller.php'));

class_alias('\extension\ka_extensions\ControllerInstaller', '\KaInstaller');