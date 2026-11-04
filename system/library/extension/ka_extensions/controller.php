<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 593 $)
*/
	
namespace extension\ka_extensions;

/**
	Replaces standard opencart Controller class with extended functionality. Recommended as a base
	class for all controllers.
	
	@package Core
*/
abstract class Controller extends \Controller {

	// callbacks of onQB(): [table key][sort order][] => callable
	protected $qb_hooks = array();


	use TraitSession, TraitController;

	/**
		Earlier it was used as alternative to the standard db class. Latest 'Ka Extensions' library intends to
		move all extended DB functions to the standard DB class.
		
		@deprecated
	*/
	protected $kadb = null;
	
	function __construct($registry) {
		parent::__construct($registry);

		if (!defined('KA_DEBUG_DEPRECATED')) {			
			$this->kadb = new Db($this->db);
		}
		
		if (\KaGlobal::isAdminArea()) {
			$this->document->addStyle('view/stylesheet/extension/ka_extensions/stylesheet.css');
		}
		$this->onLoad();
	}

	protected function onLoad() {
		return true;
	}


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
		$qb->setTable($table, $alias);

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

	
	/**
		@deprecated Use the native response->setOutput or $this->showPage()
	*/
	protected function setOutput($param = null) {
	
		assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
	
		if (!is_null($param)) {
			$this->response->setOutput($param);
		} else {
			$this->response->setOutput($this->render());
		}
	}
	
	/**
		@deprecated 
		@internal
	*/
	protected function getNamespace() {
		
		assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
	
		$class = get_class($this);
		$pos   = strripos($class, '\\');
		$ns    = '';
		if ($pos) {
			$ns = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
		}
		return $ns;	
	}

	/**
		@deprecated

		The function loads a model within the module namespace
	*/
	protected function kamodel($model) {
	
		assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
	
		$ns = $this->getNamespace();
		return $this->load->kamodel($ns . $model);
	}
	
	
	/**
		@internal

		Do not use these 'magic' prefixes. Their behavior is not transparent in inherated classes
			tbl_
			kamodel_
		
	*/
	public function __get($key) {
	
		// create a table
		//
		if (strncasecmp('tbl_', $key, 4) === 0) {

			assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
		
			$ns = $this->getNamespace();
			$tbl = str_replace('_', '', substr($key, 4));
			$tbl = $ns . 'table/' . $tbl;
			
			$table = Tables::getTable($tbl);
			return $table;
		} 

		// create a model
		//
		if (strncasecmp('kamodel_', $key, 8) === 0) {
		
			assert(!defined('KA_DEBUG_DEPRECATED'), "This functionality should not be used.");
		
			$model_name = $new_key = substr($key, 8);
			
			$ns = $this->getNamespace();
			if (!empty($ns)) {
				$new_key = str_replace('/', '_', $ns) . $new_key;
			}
			
			$new_key = 'model_' . $new_key;
			
			// try to load the model on the fly
			if (!$this->registry->has($new_key)) {
				$this->kamodel($model_name);
			}
			
			$key = $new_key;
		}
		
		return parent::__get($key);
	}
}
