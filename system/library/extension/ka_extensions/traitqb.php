<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	getQB() / onQB(): QB objects of a model or controller that other modules can extend. The base Model and
	Controller of the library use it; a developer can use it in other classes too, e.g. in a kamod child
	of a standard OpenCart model:

		class ModelCatalogProduct extends \ModelCatalogProduct {
			use \extension\ka_extensions\TraitQB;
		}

	@package DB
*/
trait TraitQB {

	// callbacks of onQB(): [table key][sort order][] => callable
	protected $qb_hooks = array();

	/**
		Subscribes a callback to the QB objects of getQB() of this object: $table - the table key
		('product', the table name without the prefix) or '*' for all of them. The callback gets the QB
		object at the start of every getSql() call (also query(), subqueries, ModelRecordset::getRecordsTotal()),
		before the SQL is built, so it may add conditions, joins and so on. The callbacks of the table go
		first, then those of '*'; in each group by $sort_order, then in the order of subscription.
		Another module subscribes in a kamod child of the class (in the constructor or onLoad()), or
		overrides getQB() there.

		A callback may run several times for one QB object (on each getSql() call): when a condition must
		be added once, the callback keeps its own flag. It must not call getSql() / query() of that QB.

		Example (a kamod child of a model):
			protected function onLoad() {
				$this->onQB('product', function($qb) {
					$qb->where('p.status', 1);
				});
				return parent::onLoad();
			}
	*/
	protected function onQB($table, callable $callback, $sort_order = 0) {
		$this->qb_hooks[(string)$table][(int)$sort_order][] = $callback;
		ksort($this->qb_hooks[(string)$table]);
	}


	/**
		Returns a new QB object of the table: from($table, $alias) is set when the table is given, the
		callbacks of onQB() for the table and '*' are passed to it. Use it instead of 'new QB()' in models
		and controllers so the query can be extended (onQB() or an override of getQB() in a kamod child).
	*/
	protected function getQB($table = '', $alias = '') {

		$qb = new QB();
		if ((string)$table !== '') {
			$qb->from($table, $alias);
		}

		$keys = ((string)$table !== '') ? array((string)$table, '*') : array('*');
		foreach ($keys as $key) {
			if (empty($this->qb_hooks[$key])) {
				continue;
			}
			foreach ($this->qb_hooks[$key] as $callbacks) {
				foreach ($callbacks as $callback) {
					$qb->addHook($callback);
				}
			}
		}

		return $qb;
	}
}
