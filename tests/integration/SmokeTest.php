<?php

namespace KaExtensionsTests\Integration;

/**
 * The store runs the library of this working copy and the test module is available.
 *
 * @group smoke
 */
class SmokeTest extends LibraryTestCase {

	public function testAdminBootsWithTheLibrary() {
		$this->bootAdmin();
		$this->assertTrue(class_exists('\extension\ka_extensions\ControllerList'));
		$this->assertTrue(class_exists('\KaGlobal'));
		$this->assertTrue(\KaGlobal::isAdminArea());
		$this->assertTrue(method_exists($this->db(), 'ka_insert'));
	}

	public function testTestModuleIsLoaded() {
		$this->bootAdmin();
		$model = $this->oc->get('load')->kamodel(static::ITEMS_ROUTE);
		$this->assertInstanceOf('\extension\ka_extensions\ModelRecordset', $model);
		$this->assertSame('item_id', $model->getPrimaryField());
	}
}
