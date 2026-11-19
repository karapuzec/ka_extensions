<?php

namespace KaExtensionsTests\Integration;

/**
 * The warning of Ka Extensions on the Design > Theme Editor page (the library kapatch of design/theme.twig):
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

	/*
		The warning has one paragraph: since 4.1.1.32 the modifications are applied to the theme editor changes of
		all stores, the paragraph 'applied to the default store only' is removed.
	*/
	protected function warning($output) {
		$this->assertMatchesRegularExpression('#<div class="alert alert-warning">\s*<p><b>Ka Extensions</b>: (.*?)</p>\s*</div>#s', $output);
		preg_match('#<div class="alert alert-warning">\s*<p><b>Ka Extensions</b>: (.*?)</p>\s*</div>#s', $output, $matches);

		return $matches[1];
	}

	public function testEnglish() {
		$output = $this->renderPage('en-gb');
		$refresh = $this->warning($output);

		$this->assertStringStartsWith('After making any changes in the theme editor', $refresh);
		$this->assertStringContainsString('Extensions > Modifications > Refresh', $refresh);
		$this->assertStringNotContainsString('default store only', $output);
	}

	public function testRussian() {
		$this->assertFileExists(DIR_LANGUAGE . 'ru-ru/extension/ka_extensions/ka_extensions/theme_editor.php');

		$output = $this->renderPage('ru-ru');
		$refresh = $this->warning($output);

		$this->assertStringStartsWith('После любых изменений в редакторе шаблона', $refresh);
		$this->assertStringNotContainsString('только к основному магазину', $output);
	}
}
