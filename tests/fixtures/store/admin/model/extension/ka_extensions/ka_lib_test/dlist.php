<?php
/*
	An empty recordset of the default list page (library tests): all from the table metadata.
*/

namespace extension\ka_extensions\ka_lib_test;

class ModelDlist extends \extension\ka_extensions\ModelRecordset {

	protected $table_class = __NAMESPACE__ . '\\dbtable\\MetaItem';
}

class_alias(__NAMESPACE__ . '\ModelDlist', '\ModelExtensionKaExtensionsKaLibTestDlist');
