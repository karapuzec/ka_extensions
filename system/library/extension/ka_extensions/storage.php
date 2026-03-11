<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	The storage id is used to save files for non-existing entities like new products. It was created
	for ka_multivendor module initially but it might be extended and used in other modules too.
*/
class Storage {
	protected const RANDOM_BYTES = 6;

	public static function generateId(): string {
	
		$time = (int) floor(microtime(true) * 1000);

		$timePart = str_pad(
			base_convert((string)$time, 10, 36),
			9,
			'0',
			STR_PAD_LEFT
		);

		$randomPart = substr(
			bin2hex(random_bytes(self::RANDOM_BYTES)),
			0,
			12
		);

		return $timePart . $randomPart;
	}
}