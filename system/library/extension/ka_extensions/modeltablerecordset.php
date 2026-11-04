<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	A recordset of an ADBTable class without a model file of its own, see ModelRecordset::forTable()
	and ControllerList::TABLE_CLASS.

	@package DB
*/
class ModelTableRecordset extends ModelRecordset {

	public function __construct($registry, $table_class) {

		parent::__construct($registry);

		$this->table_class = $table_class;
		$this->onLoad();
	}
}
