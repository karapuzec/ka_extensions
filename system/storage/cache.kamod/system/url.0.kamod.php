<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_extensions/system/library/url.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\library;

use \extension\ka_extensions\KaGlobal;

require_once(__DIR__ . '/url.1.kamod.php');

class Url extends \Url_kamod  {

	public function linka_js($route, $args = null, $secure = null) {
		return $this->linka($route, $args, $secure, true);
	}

	public function linka($route, $args = null, $secure = null, $is_js = false) {

		if (is_null($secure)) {
			if (empty($this->ssl)) {
				$secure = false;
			} else {
				$secure = true;
			}
		}
	
		$session = KaGlobal::getRegistry()->get('session');
		if (KaGlobal::isAdminArea() && !empty($session->data['user_token'])) {
			if (is_null($args)) {
				$args = ['user_token' => $session->data['user_token']];
			} else {
				if (is_array($args)) {
					if (!isset($args['user_token'])) {
						$args = array_merge($args, ['user_token' => $session->data['user_token']]);
					}
				} else {
					$args = $args . '&user_token=' . $session->data['user_token'];
				}
			}
		}
	
		if (is_null($args)) {
			$args = '';
		}
		
		$link = parent::link($route, $args, $secure);
		
		if ($is_js) {
			$link = str_replace('&amp;', '&', $link);
		}
		
		return $link;
	}
}