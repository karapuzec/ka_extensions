<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_extensions/system/library/cart/currency.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\library\Cart;

require_once(__DIR__ . '/currency.1.kamod.php');

class Currency extends \Cart\Currency_kamod  {

	public function formatValue($number, $currency) {

		$decimal_place = $this->getDecimalPlace($currency);
		$amount = sprintf('%0.' . (int)$decimal_place . 'f', $number);
		
		return $amount;	
	}
}