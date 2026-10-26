<?php

namespace KaExtensionsTests\Integration;

/**
 * Kamod: a module class extending an OpenCart controller, kapatch of a PHP file and of a template
 * (the module ka_lib_kamod of tests/fixtures/store).
 *
 * @group kamod
 */
class KamodTest extends LibraryTestCase {

	protected function setUp(): void {
		$this->bootAdmin();
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
	}

	public function testCacheIsValid() {
		$this->assertTrue(\extension\ka_extensions\KamodManager::getInstance()->isKamodCacheValid());
	}

	public function testChildClassAndPhpPatch() {
		$result = $this->oc->request('report/statistics/kaLibTestPing');
		$this->assertSame('kamod-override-ok:patched', $result['output']);
	}

	public function testTemplatePatch() {
		$result = $this->oc->request('report/statistics');
		$this->assertStringContainsString('<!-- ka-lib-kamod-twig -->', $result['output']);
		// the original page still works
		$this->assertStringContainsString('report/statistics', $result['output']);
	}
}
