<?php

namespace KaExtensionsTests\Integration;

/**
 * The library JS on the admin and storefront pages: the header adds kaextensions.js (not common.js)
 * and creates the object ka_extensions; the footer passes the labels of the document
 * (Document::addKaJsLabels(), the standard labels with txt_confirm always, also with
 * KA_DISABLE_FORM_VALIDATION) as ka_extensions.setLabels({...}), JSON safe in an inline script.
 * No global ka_js_labels.
 *
 * @group smoke
 */
class JsLabelsTest extends LibraryTestCase {

	const STANDARD = array(
		'txt_warning' => 'Warning',
		'txt_error'   => 'Error',
		'txt_success' => 'Success',
		'txt_info'    => 'Info',
		'txt_confirm' => 'Are you sure?',
		'txt_field_validation_error' => 'Some data is not filled in. Please check the form fields: ',
	);

	const TEST_LABEL = "a \"b\" 'c' \\ d\r\ne </script> &amp; <b>";

	protected function labels($output) {
		$this->assertStringNotContainsString('var ka_js_labels', $output);
		$this->assertSame(1, preg_match_all('/ka_extensions\.setLabels\((\{.*\})\);/', $output, $m));
		$labels = json_decode($m[1][0], true);
		$this->assertIsArray($labels, $m[1][0]);
		return $labels;
	}

	protected function assertStandard($labels) {
		foreach (self::STANDARD as $key => $value) {
			$this->assertArrayHasKey($key, $labels);
			$this->assertSame($value, $labels[$key], $key);
		}
	}

	protected function addTestLabel() {
		$this->oc->get('document')->addKaJsLabels(array('txt_test' => self::TEST_LABEL));
	}

	protected function checkTestLabel($output) {
		// no raw quote, tag, ampersand or line break of the value in the script
		$this->assertStringNotContainsString('</script> &amp;', $output);
		$this->assertStringNotContainsString('"b"', $output);
		$this->assertStringNotContainsString("d\r\ne", $output);
		$this->assertSame(self::TEST_LABEL, $this->labels($output)['txt_test']);
	}

	// kaextensions.js, then the object is created in the head; common.js is not included
	protected function assertScripts($output, $dir) {
		$class  = strpos($output, $dir . 'kaextensions.js');
		$object = strpos($output, 'var ka_extensions = new KaExtensions();');
		$this->assertNotFalse($class);
		$this->assertNotFalse($object);
		$this->assertLessThan($object, $class);
		$this->assertLessThan(strpos($output, '</head>'), $object);
		$this->assertStringNotContainsString($dir . 'common.js', $output);
		$this->assertLessThan(strpos($output, 'ka_extensions.setLabels('), $object);
	}

	public function testAdminPage() {
		$this->bootAdmin();
		$this->addTestLabel();
		$output = $this->oc->request('common/dashboard')['output'];
		$this->assertStandard($this->labels($output));
		$this->checkTestLabel($output);
		$this->assertStringContainsString("ka_extensions.getLabel('txt_field_validation_error')", $output);
		$this->assertScripts($output, 'view/javascript/extension/ka_extensions/');
	}

	public function testCatalogPage() {
		$this->bootCatalog();
		$this->addTestLabel();
		$output = $this->oc->request('common/home')['output'];
		$this->assertStandard($this->labels($output));
		$this->checkTestLabel($output);
		$this->assertScripts($output, 'catalog/view/javascript/extension/ka_extensions/');
	}

	public function testLabelsWithoutFormValidation() {
		define('KA_DISABLE_FORM_VALIDATION', 1);
		$this->bootAdmin();
		$output = $this->oc->request('common/dashboard')['output'];
		$this->assertStandard($this->labels($output));
		// the validation script is not printed
		$this->assertStringNotContainsString("getLabel('txt_field_validation_error') + error", $output);
	}
}
