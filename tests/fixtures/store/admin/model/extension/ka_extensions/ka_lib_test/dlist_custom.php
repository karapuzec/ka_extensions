<?php
/*
	The recordset of the page dlist_custom (library tests), the same table.
*/

namespace extension\ka_extensions\ka_lib_test;

class ModelDlistCustom extends \extension\ka_extensions\ModelRecordset {

	protected $table_class = __NAMESPACE__ . '\\dbtable\\MetaItem';
}

class_alias(__NAMESPACE__ . '\ModelDlistCustom', '\ModelExtensionKaExtensionsKaLibTestDlistCustom');
