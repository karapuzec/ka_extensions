<?php
/*
	An empty recordset of the admin list page (library tests).
*/

namespace extension\ka_extensions\ka_lib_test;

class ModelDadmin extends \extension\ka_extensions\ModelRecordset {

	protected $table_class = __NAMESPACE__ . '\\dbtable\\MetaAdminItem';
}

class_alias(__NAMESPACE__ . '\ModelDadmin', '\ModelExtensionKaExtensionsKaLibTestDadmin');
