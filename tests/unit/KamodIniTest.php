<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\KamodBuilder;
use extension\ka_extensions\KamodManager;

/*
	KamodBuilder with the module order taken from an array instead of kamod.ini files.
*/
class OrderKamodBuilder extends KamodBuilder {

	// module code => ['before' => [...], 'after' => [...]]
	public $orders = array();

	protected function getModulesOrder($code, $file, $order = '') {
		return isset($this->orders[$code][$order]) ? $this->orders[$code][$order] : array();
	}

	public function arrange($files) {
		return $this->arrangeChildren($files);
	}
}

/**
 * Module list fields of kamod.ini (depends_on, install_before, install_after) and the order
 * of module child classes in the kamod chain.
 *
 * @group kamod
 */
class KamodIniTest extends UnitTestCase {

	protected function modules($ini, $field = 'depends_on') {
		return KamodManager::getManifestModules(parse_ini_string($ini), $field);
	}

	public function testCommaSeparatedString() {
		$this->assertSame(
			['ka_extensions/a', 'other/b'],
			$this->modules('depends_on = "ka_extensions/a, other/b"')
		);
		$this->assertSame(
			['ka_extensions/a', 'ka_extensions/b'],
			$this->modules('install_after = ka_extensions/a,ka_extensions/b', 'install_after')
		);
	}

	public function testSingleValue() {
		$this->assertSame(['ka_extensions/a'], $this->modules('install_before = "ka_extensions/a"', 'install_before'));
	}

	public function testArray() {
		$this->assertSame(
			['ka_extensions/a', 'other/b'],
			$this->modules("install_after[] = \"ka_extensions/a\"\ninstall_after[] = 'other/b'", 'install_after')
		);
	}

	public function testArrayItemsWithCommas() {
		$this->assertSame(
			['ka_extensions/a', 'ka_extensions/b', 'other/c'],
			$this->modules("depends_on[] = \"a, b\"\ndepends_on[] = other/c")
		);
	}

	public function testBareCodeGetsKaExtensionsVendor() {
		$this->assertSame(['ka_extensions/a', 'other/b'], $this->modules('depends_on = " a , other/b "'));
	}

	public function testEmptyItemsAndDuplicatesAreDropped() {
		$this->assertSame(
			['ka_extensions/a', 'ka_extensions/b'],
			$this->modules("depends_on[] = \"a,, ,b\"\ndepends_on[] = \"\"\ndepends_on[] = ka_extensions/a")
		);
	}

	public function testMissingOrEmptyField() {
		$this->assertSame([], $this->modules('module = "Test"'));
		$this->assertSame([], $this->modules('depends_on = ""'));
		$this->assertSame([], KamodManager::getManifestModules(false, 'depends_on'));
	}

	public function testFullModuleCode() {
		$this->assertSame('ka_extensions/a', KamodManager::getFullModuleCode(' a '));
		$this->assertSame('vendor/a', KamodManager::getFullModuleCode('vendor/a'));
		$this->assertSame('', KamodManager::getFullModuleCode(' '));
	}

	protected function arrange($orders, $codes = ['v/a', 'v/b', 'v/c']) {
		$builder = (new \ReflectionClass(OrderKamodBuilder::class))->newInstanceWithoutConstructor();
		$builder->orders = $orders;

		$files = [];
		foreach ($codes as $code) {
			$files[] = 'catalog/controller/extension/' . $code . '/controller/common/header.php';
		}

		return array_keys($builder->arrange($files));
	}

	public function testInstallAfter() {
		$this->assertSame(['v/b', 'v/c', 'v/a'], $this->arrange(['v/a' => ['after' => ['v/c']]]));
		$this->assertSame(['v/b', 'v/a', 'v/c'], $this->arrange(['v/a' => ['after' => ['v/b', 'v/zzz']]]));
	}

	public function testInstallAfterWithoutListedModulesKeepsPosition() {
		$this->assertSame(['v/a', 'v/b', 'v/c'], $this->arrange(['v/a' => ['after' => ['v/zzz']]]));
		$this->assertSame(['v/a', 'v/b', 'v/c'], $this->arrange(['v/c' => ['after' => ['v/zzz']]]));
	}

	public function testInstallBefore() {
		$this->assertSame(['v/c', 'v/a', 'v/b'], $this->arrange(['v/c' => ['before' => ['v/a']]]));
		$this->assertSame(['v/a', 'v/c', 'v/b'], $this->arrange(['v/c' => ['before' => ['v/zzz', 'v/b']]]));
	}

	public function testInstallBeforeWithoutListedModulesKeepsTheFile() {
		$this->assertSame(['v/a', 'v/b', 'v/c'], $this->arrange(['v/c' => ['before' => ['v/zzz']]]));
		$this->assertSame(['v/a', 'v/b', 'v/c'], $this->arrange(['v/a' => ['before' => ['v/zzz']]]));
	}
}
