<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\library\Cart;

class Currency extends Cart\Currency {

	public function formatValue($number, $currency) {

		$decimal_place = $this->getDecimalPlace($currency);
		$amount = sprintf('%0.' . (int)$decimal_place . 'f', $number);
		
		return $amount;	
	}
}