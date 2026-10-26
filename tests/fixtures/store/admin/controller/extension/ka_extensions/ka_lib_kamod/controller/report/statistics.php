<?php
/*
	Kamod test of the library tests: a child class of an OpenCart controller.
*/

namespace extension\ka_extensions\ka_lib_kamod;

class ControllerReportStatistics extends \ControllerReportStatistics {

	public function kaLibTestPing() {
		$this->response->setOutput('kamod-override-ok:' . $this->kaLibTestPatched());
	}
}
