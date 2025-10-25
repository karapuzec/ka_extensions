<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

trait TraitDBFilter {

	protected $ka_dbproxy;

	public function __get($key) {
		if ($key == 'db') {
			if (empty($this->ka_dbproxy)) {
				$db = $this->registry->get('db');
				$this->ka_dbroxy = DBProxy::getInstance($db);
			}
			$this->ka_dbroxy->setCurrentClass(get_class());
			return $this->ka_dbroxy;
		}
		return parent::__get($key);
	}
	
}