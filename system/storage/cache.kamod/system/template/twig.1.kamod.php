<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_speedup/system/library/template/twig.php
*/
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/
namespace extension\ka_extensions\ka_speedup\template;

require_once(__DIR__ . '/twig.2.kamod.php');

class Twig_kamod extends \Template\Twig_kamod  {

	public function setData($data) {
		$this->data = $data;
	}
}