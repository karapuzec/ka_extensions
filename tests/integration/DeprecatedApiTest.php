<?php

namespace KaExtensionsTests\Integration;

/**
 * Deprecated Ka API on a store with KA_DEBUG_DEPRECATED and active PHP assertions (zend.assertions = 1,
 * set in php.ini, it cannot be changed at runtime; the tests are skipped without it): the admin and storefront pages changed by the library
 * (header, footer, theme editor) do not use deprecated code, the legacy 'kamodel_' shorthand of the registry
 * is reported. Without the constant the shorthand still gives the model.
 *
 * Also the parts moved from OCMOD to kamod and kapatch: one copy of kaextensions.js and of the stylesheet,
 * the object ka_extensions in the head, meta tags of Document::addKaMeta(), labels and form validation
 * in the footer, the theme editor warning.
 *
 * @group deprecated
 */
class DeprecatedApiTest extends LibraryTestCase {

	protected function enableDeprecated() {
		// asserts are not compiled with zend.assertions = -1 (the production value), the checks would pass silently
		if (ini_get('zend.assertions') !== '1') {
			$this->markTestSkipped('PHP assertions are not active: run the group with zend.assertions = 1 (see tests/README.md)');
		}
		define('KA_DEBUG_DEPRECATED', 1);
	}

	protected function assertLibraryPage($output, $dir, $css_dir) {
		$this->assertSame(1, substr_count($output, $dir . 'kaextensions.js'), 'kaextensions.js once');
		$this->assertSame(1, substr_count($output, $css_dir . 'stylesheet.css'), 'stylesheet.css once');

		$object = strpos($output, 'var ka_extensions = new KaExtensions();');
		$this->assertNotFalse($object);
		$this->assertLessThan(strpos($output, '</head>'), $object);
		$this->assertLessThan($object, strpos($output, $dir . 'kaextensions.js'));

		$this->assertSame(1, substr_count($output, 'ka_extensions.setLabels('));
		$this->assertStringContainsString("\$('button[form]').each(", $output);
	}

	public function testAssertionsAreActive() {
		$this->enableDeprecated();
		$this->expectException(\AssertionError::class);
		assert(!defined('KA_DEBUG_DEPRECATED'), 'test');
	}

	public function testAdminPages() {
		$this->enableDeprecated();
		$this->bootAdmin();

		foreach (array('common/dashboard', 'catalog/product/add') as $route) {
			$output = $this->oc->request($route)['output'];
			$this->assertLibraryPage($output, 'view/javascript/extension/ka_extensions/', 'view/stylesheet/extension/ka_extensions/');
			// the footer script is printed before the footer of the page
			$this->assertLessThan(strpos($output, '<footer id="footer">'), strpos($output, 'ka_extensions.setLabels('), $route);
		}

		$output = $this->oc->request('design/theme')['output'];
		$this->assertSame(1, substr_count($output, '<p><b>Ka Extensions</b>: '));
		$this->assertLessThan(strpos($output, '<div id="recent">'), strpos($output, '<p><b>Ka Extensions</b>: '));
	}

	public function testCatalogPages() {
		$this->enableDeprecated();
		$this->bootCatalog();
		$this->oc->get('document')->addKaMeta('ka-test-meta', 'a "b" <c>');

		foreach (array('common/home', 'information/contact') as $route) {
			$output = $this->oc->request($route)['output'];
			$this->assertLibraryPage($output, 'catalog/view/javascript/extension/ka_extensions/', 'catalog/view/javascript/extension/ka_extensions/');
			$this->assertSame(1, substr_count($output, '<meta name="ka-test-meta" content="a &quot;b&quot; &lt;c&gt;" />'), $route);
			$this->assertLessThan(strpos($output, '</head>'), strpos($output, 'name="ka-test-meta"'));
			$this->assertLessThan(strpos($output, 'ka_extensions.setLabels('), strpos($output, '</footer>'), $route);
		}
	}

	public function testRegistryShorthandIsReported() {
		$this->enableDeprecated();
		$this->bootAdmin();
		$this->oc->get('load')->kamodel('extension/ka_extensions/ka_extensions/kamod');

		$this->expectException(\AssertionError::class);
		$this->expectExceptionMessage("'kamodel_' shorthand");
		$this->oc->getRegistry()->get('kamodel_ka_extensions_kamod');
	}

	public function testRegistryShorthandWithoutTheConstant() {
		$this->bootAdmin();
		$model = $this->oc->get('load')->kamodel('extension/ka_extensions/ka_extensions/kamod');

		$this->assertIsObject($model);
		$this->assertSame($model, $this->oc->getRegistry()->get('kamodel_ka_extensions_kamod'));
		// other keys are not changed
		$this->assertSame($model, $this->oc->getRegistry()->get('model_extension_ka_extensions_ka_extensions_kamod'));
	}
}
