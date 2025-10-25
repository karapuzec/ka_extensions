<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

namespace extension\ka_extensions\ka_exam;

/**
* Template class
*/
class Template extends \Template {
	public function render($template, $cache = false) {
		$start_time = microtime(true);

		$result = parent::render($template, $cache);
		
		$end_time = microtime(true);
		$total = $end_time - $start_time;
		if ($total > 0.01) {
			Logger::log(round($total, Config::$precision) ." - twig: " . $template);
		}
		return $result;
	}
}
