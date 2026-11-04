<?php
/*
	A model of the library tests reading items with getQB() (QbHooksTest). ModelQbitemsEnabled is what a
	kamod child of another module would do: it subscribes to the queries of the table with onQB().
*/

namespace extension\ka_extensions\ka_lib_test;

class ModelQbitems extends \extension\ka_extensions\Model {

	public $last_sql = '';

	public function getNames() {
		$qb = $this->getQB('ka_lib_test_item', 'i');
		$qb->select('i.name');
		$qb->orderBy('i.name');
		$this->last_sql = $qb->getSql();
		return array_column($qb->query()->rows, 'name');
	}
}

class ModelQbitemsEnabled extends ModelQbitems {

	protected function onLoad() {
		$this->onQB('ka_lib_test_item', function($qb) {
			$qb->where('i.status', 1);
		});
		return parent::onLoad();
	}
}

class_alias(__NAMESPACE__ . '\ModelQbitems', '\ModelExtensionKaExtensionsKaLibTestQbitems');
