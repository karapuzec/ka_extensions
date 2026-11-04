<?php

namespace KaExtensionsTests\Integration;

/**
 * The labels of common.js (ka_js_labels) printed by the admin and storefront footers: the standard
 * labels with txt_confirm always (also with KA_DISABLE_FORM_VALIDATION), labels of a module added by
 * Document::addKaJsLabels(), JS escaping of the values.
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

	protected function labels($output) {
		$this->assertStringContainsString('var ka_js_labels = [];', $output);
		preg_match_all('/ka_js_labels\[\'([^\']*)\'\] = "((?:[^"\\\\]|\\\\.)*)";/', $output, $m, PREG_SET_ORDER);
		$labels = array();
		foreach ($m as $row) {
			// the JS string as Twig escapes it (\xHH, \uHHHH) decoded as a JSON string
			$json = preg_replace('/\\\\x([0-9A-Fa-f]{2})/', '\\\\u00$1', $row[2]);
			$labels[$row[1]] = json_decode('"' . $json . '"');
		}
		return $labels;
	}

	protected function assertStandard($labels) {
		foreach (self::STANDARD as $key => $value) {
			$this->assertArrayHasKey($key, $labels);
			$this->assertSame($value, $labels[$key], $key);
		}
	}

	protected function addTestLabel() {
		$this->oc->get('document')->addKaJsLabels(array(
			'txt_test' => "a \"b\" 'c' \\ </script> &amp; <b>",
		));
	}

	protected function checkTestLabel($output) {
		// no raw quote, backslash or closing tag in the JS string
		$this->assertStringNotContainsString('</script> &amp;', $output);
		$this->assertStringNotContainsString('&quot;b&quot;', $output);
		$this->assertSame("a \"b\" 'c' \\ </script> &amp; <b>", $this->labels($output)['txt_test']);
	}

	public function testAdminFooter() {
		$this->bootAdmin();
		$this->addTestLabel();
		$output = $this->oc->request('common/dashboard')['output'];
		$labels = $this->labels($output);
		$this->assertStandard($labels);
		$this->checkTestLabel($output);
		$this->assertStringContainsString("ka_js_labels['txt_field_validation_error']", $output);
	}

	public function testCatalogFooter() {
		$this->bootCatalog();
		$this->addTestLabel();
		$output = $this->oc->request('common/home')['output'];
		$this->assertStandard($this->labels($output));
		$this->checkTestLabel($output);
	}

	public function testLabelsWithoutFormValidation() {
		define('KA_DISABLE_FORM_VALIDATION', 1);
		$this->bootAdmin();
		$output = $this->oc->request('common/dashboard')['output'];
		$this->assertStandard($this->labels($output));
		// the validation script is not printed
		$this->assertStringNotContainsString("ka_js_labels['txt_field_validation_error'] + error", $output);
	}
}
