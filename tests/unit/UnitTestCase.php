<?php

namespace KaExtensionsTests\Unit;

/*
	Base class of the unit tests: a fresh registry with stub services and a recording database
	for every test.
*/
abstract class UnitTestCase extends \PHPUnit\Framework\TestCase {

	/** @var \Registry */
	protected $registry;

	/** @var \FakeDbAdaptor */
	protected $adaptor;

	protected function setUp(): void {
		$this->registry = new \Registry();
		$this->adaptor  = new \FakeDbAdaptor();

		$this->registry->set('db',       new \extension\ka_extensions\SystemLibraryDB($this->adaptor));
		$this->registry->set('config',   new \Config());
		$this->registry->set('language', new \Language());
		$this->registry->set('session',  new \Session());
		$this->registry->set('request',  new \Request());
		$this->registry->set('load',     new \Loader());
		$this->registry->set('url',      new \Url());
		$this->registry->set('document', new \Document());
		$this->registry->set('response', new \Response());

		\KaGlobal::init($this->registry);
	}

	/*
		Calls a protected method of an object.
	*/
	protected function call($object, $method, ...$args) {
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($object, $args);
	}

	protected function getProperty($object, $name) {
		$reflection = new \ReflectionProperty($object, $name);
		$reflection->setAccessible(true);
		return $reflection->getValue($object);
	}

	protected function setProperty($object, $name, $value) {
		$reflection = new \ReflectionProperty($object, $name);
		$reflection->setAccessible(true);
		$reflection->setValue($object, $value);
	}

	protected function queries() {
		return $this->adaptor->queries;
	}

	protected function lastQuery() {
		return $this->adaptor->lastQuery();
	}

	/*
		Collapses white space for comparing generated SQL.
	*/
	protected function normalizeSql($sql) {
		return trim(preg_replace('/\s+/', ' ', $sql));
	}
}
