<?php

namespace KaExtensionsTests\Unit;

/**
 * The language files of the library have the labels passed to the library JS (ka_extensions.setLabels()) by the
 * admin and storefront footers, in en-gb and ru-ru; the footers pass them all.
 *
 * @group smoke
 */
class JsLabelsTest extends \PHPUnit\Framework\TestCase {

	const KEYS = array('txt_warning', 'txt_error', 'txt_success', 'txt_info', 'txt_confirm', 'txt_field_validation_error');

	public function languageFiles() {
		$root = dirname(__DIR__, 2);
		$files = array();
		foreach (array('admin', 'catalog') as $side) {
			foreach (array('en-gb', 'ru-ru') as $lang) {
				$files["$side $lang"] = array("$root/$side/language/$lang/extension/ka_extensions/ka_extensions/common.php");
			}
		}
		return $files;
	}

	/**
	 * @dataProvider languageFiles
	 */
	public function testLanguageFilesHaveTheLabels($file) {
		$_ = array();
		include($file);
		foreach (self::KEYS as $key) {
			$this->assertArrayHasKey($key, $_, $key);
			$this->assertNotSame('', trim($_[$key]), $key);
		}
	}

	public function testFootersPassTheLabels() {
		$root = dirname(__DIR__, 2);
		foreach (array('admin/controller/extension/ka_extensions/ka_extensions/controller/common/footer.php', 'catalog/controller/extension/ka_extensions/ka_extensions/controller/common/footer.php') as $file) {
			$code = file_get_contents("$root/$file");
			foreach (self::KEYS as $key) {
				$this->assertStringContainsString("'$key'", $code, "$file: $key");
			}
			$this->assertStringContainsString('addKaJsLabels(', $code, $file);
			$this->assertStringNotContainsString('addKaJsLables', $code, $file);
			// the labels are not inside the form validation condition
			$this->assertLessThan(strpos($code, "KA_DISABLE_FORM_VALIDATION"), strpos($code, 'addKaJsLabels('), $file);
		}
	}
}
