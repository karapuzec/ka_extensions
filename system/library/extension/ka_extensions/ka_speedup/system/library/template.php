<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_speedup;

class Template extends \Template {

	public function setData($data) {
		$this->adaptor->setData($data);
	}
}