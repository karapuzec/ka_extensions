<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 417 $)
*/
	
namespace extension\ka_extensions;

/**
	Service class for getting User and UserType information regardless of the current area.

	It might be helpful in shared functions.
*/
class User {

	static public function getUserType() {
		
		$user_type = 'C';
		
		if (\KaGlobal::isAdminArea()) {
			$user_type = 'A';
		}
	
		return $user_type;
	}
	
	
	static public function getUserId() {
	
		$user_id = null;
	
		if (static::getUserType() == 'A') {
			$user_id = \KaGlobal::getRegistry()->get('user')->getId();
		} else {
			$user_id = \KaGlobal::getRegistry()->get('customer')->getId();
		}
			
		return $user_id;
	}	
}