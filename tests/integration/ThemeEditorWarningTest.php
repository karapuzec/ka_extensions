<?php

namespace KaExtensionsTests\Integration;

/**
 * The warning of Ka Extensions on the Design > Theme Editor page (the library ocmod of design/theme.twig):
 * texts from the language files of the library in English and Russian.
 *
 * @group theme-files
 */
class ThemeEditorWarningTest extends LibraryTestCase {

	protected function setUp(): void {
		$this->bootAdmin();
	}

	protected function renderPage($language_code) {
		$this->oc->get('config')->set('config_admin_language', $language_code);
		$language = new \Language($language_code);
		$language->load($language_code);
		$this->oc->getRegistry()->set('language', $language);

		$result = $this->oc->request('design/theme');

		return $result['output'];
	}

	protected function warning($output) {
		$this->assertMatchesRegularExpression('#<div class="alert alert-warning">\s*<p><b>Ka Extensions</b>: (.*?)</p>\s*<p>(.*?)</p>\s*</div>#s', $output);
		preg_match('#<div class="alert alert-warning">\s*<p><b>Ka Extensions</b>: (.*?)</p>\s*<p>(.*?)</p>\s*</div>#s', $output, $matches);

		return array($matches[1], $matches[2]);
	}

	public function testEnglish() {
		list($refresh, $store) = $this->warning($this->renderPage('en-gb'));

		$this->assertStringStartsWith('After making any changes in the theme editor', $refresh);
		$this->assertStringContainsString('Extensions > Modifications > Refresh', $refresh);
		$this->assertSame('The modifications are applied to the default store only. Avoid using the theme editor for additional stores.', $store);
	}

	public function testRussian() {
		$this->assertFileExists(DIR_LANGUAGE . 'ru-ru/extension/ka_extensions/ka_extensions/theme_editor.php');

		list($refresh, $store) = $this->warning($this->renderPage('ru-ru'));

		$this->assertStringStartsWith('После любых изменений в редакторе шаблона', $refresh);
		$this->assertSame('Модификации применяются только к основному магазину. Не используйте редактор шаблона для дополнительных магазинов.', $store);
	}
}
