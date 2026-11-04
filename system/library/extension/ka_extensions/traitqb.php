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

	// callbacks of onQB(): [table key][sort order][subscription id] => callable
	protected $qb_hooks = array();

	// the last subscription id of onQB()
	protected $qb_hook_id = 0;

	/**
		Subscribes a callback to the QB objects of getQB() of this object: $table - the table key
		('product', the table name without the prefix) or '*' for all of them. The callback gets the QB
		object at the start of every getSql() call (also query(), subqueries, ModelRecordset::getRecordsTotal()),
		before the SQL is built, so it may add conditions, joins and so on. The callbacks of the table go
		first, then those of '*'; in each group by $sort_order, then in the order of subscription.
		Returns the subscription id for offQB().

		Usually another module subscribes for a while in a method of a kamod child of the class, to extend
		the queries made by the parent method, and removes the callback after it. getQB() copies the current
		callbacks into the QB object it creates: a QB made while the subscription was active keeps the
		callback after offQB(), QB objects made after offQB() do not get it.

		A callback may run several times for one QB object (on each getSql() call): when a condition must
		be added once, the callback keeps its own flag. It must not call getSql() / query() of that QB.

		Example (a kamod child of a model):
			public function getRecords($filter) {
				$id = $this->onQB('product', function($qb) {
					$qb->where('p.ka_hidden', 0);
				});
				$result = parent::getRecords($filter);
				$this->offQB($id);
				return $result;
			}
	*/
	protected function onQB($table, callable $callback, $sort_order = 0) {

		$id = ++$this->qb_hook_id;
		$this->qb_hooks[(string)$table][(int)$sort_order][$id] = $callback;
		ksort($this->qb_hooks[(string)$table]);

		return $id;
	}


	/**
		Removes the callback of onQB() by its subscription id. An unknown id is ignored. QB objects created
		before keep the callback.
	*/
	protected function offQB($id) {

		foreach ($this->qb_hooks as $table => $groups) {
			foreach ($groups as $sort_order => $callbacks) {
				if (isset($callbacks[$id])) {
					unset($this->qb_hooks[$table][$sort_order][$id]);
					return;
				}
			}
		}
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
