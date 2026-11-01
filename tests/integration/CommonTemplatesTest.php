<?php

namespace KaExtensionsTests\Integration;

/**
 * The '@ka_common' templates on the storefront: the folder of the active theme first, then the folder
 * of the default theme (the template extension/ka_lib_test/ka_common and the test themes ka_lib_theme
 * and ka_lib_theme2 of tests/fixtures/store).
 *
 * @group ka-common
 */
class CommonTemplatesTest extends LibraryTestCase {

	const TEMPLATE = 'extension/ka_lib_test/ka_common';

	protected function setUp(): void {
		$this->bootCatalog();
	}

	protected function render($theme) {
		$this->oc->get('config')->set('config_theme', $theme);

		$data = array(
			'breadcrumbs' => array(
				array('text' => 'ka-lib-crumb-home', 'href' => 'index.php'),
				array('text' => 'ka-lib-crumb-page', 'href' => 'index.php?route=information/contact'),
			),
		);

		return $this->oc->get('load')->view(self::TEMPLATE, $data);
	}

	protected function assertDefaultBreadcrumbs($output) {
		$this->assertStringContainsString('<ul class="breadcrumb">', $output);
		$this->assertStringContainsString('ka-lib-crumb-page', $output);
		$this->assertStringNotContainsString('ka-lib-theme2-breadcrumbs', $output);
	}

	protected function assertDefaultWell($output) {
		$this->assertMatchesRegularExpression('/<div class="well well-md">\s*(<!-- ka-lib-kamod-well -->)?ka-lib-well-contents/', $output);
	}

	public function testDefaultTheme() {
		$output = $this->render('default');
		$this->assertDefaultBreadcrumbs($output);
		$this->assertDefaultWell($output);
	}

	/*
		A theme without the folder template/extension/ka_extensions/common uses the templates of the
		default theme.
	*/
	public function testThemeWithoutCommonFolder() {
		$this->assertDirectoryDoesNotExist(DIR_TEMPLATE . 'ka_lib_theme/template/extension/ka_extensions/common');

		$output = $this->render('ka_lib_theme');
		$this->assertDefaultBreadcrumbs($output);
		$this->assertDefaultWell($output);
	}

	/*
		A theme overrides only the templates it has, the other ones come from the default theme.
	*/
	public function testThemeOverridesOneTemplate() {
		$output = $this->render('ka_lib_theme2');

		$this->assertStringContainsString('<!-- ka-lib-theme2-breadcrumbs -->', $output);
		$this->assertStringContainsString('<span class="ka-lib-theme2-crumb">ka-lib-crumb-page</span>', $output);
		$this->assertStringNotContainsString('<ul class="breadcrumb">', $output);
		$this->assertDefaultWell($output);
	}

	/*
		A theme without its own folder does not break the namespace when the theme does not exist at all
		(a wrong config_theme value): the default templates are still used.
	*/
	public function testMissingThemeDirectory() {
		$output = $this->render('ka_lib_no_such_theme');
		$this->assertDefaultBreadcrumbs($output);
		$this->assertDefaultWell($output);
	}
}
