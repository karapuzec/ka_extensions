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
		$this->rebuildKamodCache();
		$this->bootCatalog();
	}

	/*
		The storefront uses the template class of Ka Extensions from the kamod cache. The cache is built
		by a separate admin request (with the kapatch of the module ka_lib_kamod).
	*/
	protected function rebuildKamodCache() {
		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;
		$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';

		$process = proc_open(
			array($php, __DIR__ . '/tools/kamod_rebuild.php', $platform_dir, \KaTesting\Env::getStoreDir()),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		$this->assertSame(0, proc_close($process), $output);
	}

	protected function render($theme) {
		// the active theme of the store (an enabled theme extension, see catalog/controller/event/theme.php)
		$this->oc->get('config')->set('config_theme', $theme);
		$this->oc->get('config')->set('theme_' . $theme . '_status', 1);

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

	/*
		A kapatch of a default '@ka_common' template (the module ka_lib_kamod) is applied with another theme
		(the kamod cache of the default folder is registered for non-default themes).
	*/
	public function testKapatchOfCommonTemplateWithAnotherTheme() {
		$output = $this->render('ka_lib_theme');
		$this->assertStringContainsString('<!-- ka-lib-kamod-well -->ka-lib-well-contents', $output);
		$this->assertDefaultBreadcrumbs($output);
	}

	/*
		The same kapatch is applied with the default theme too (before 4.1.1.31 the kamod cache of the common
		folder was not registered for the default theme).
	*/
	public function testKapatchOfCommonTemplateWithDefaultTheme() {
		$output = $this->render('default');
		$this->assertStringContainsString('<!-- ka-lib-kamod-well -->ka-lib-well-contents', $output);
		$this->assertStringContainsString('<ul class="breadcrumb"><!-- ka-lib-kamod-breadcrumbs -->', $output);
	}

	/*
		A theme template overrides the default one also when the default one is patched (breadcrumbs.twig of
		the default folder is patched by ka_lib_kamod, ka_lib_theme2 has its own breadcrumbs.twig).
	*/
	public function testThemeTemplateWinsOverPatchedDefault() {
		$output = $this->render('ka_lib_theme2');
		$this->assertStringContainsString('<!-- ka-lib-theme2-breadcrumbs -->', $output);
		$this->assertStringNotContainsString('<!-- ka-lib-kamod-breadcrumbs -->', $output);
		$this->assertStringContainsString('<!-- ka-lib-kamod-well -->ka-lib-well-contents', $output);
	}
}
