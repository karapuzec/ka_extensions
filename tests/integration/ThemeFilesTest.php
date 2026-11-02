<?php

namespace KaExtensionsTests\Integration;

/**
 * The setting 'Modify Theme Files' of Ka Extensions: OCMOD modifications are applied to the templates
 * changed by the administrator in Design > Theme Editor (table oc_theme, the default store only).
 *
 * The test modification changes the fixture templates extension/ka_lib_test/theme_file*.twig of all
 * themes (the default theme and ka_lib_theme2, which has its own copy of theme_file.twig; ka_lib_theme
 * has no copy and uses the default one). theme_file_kamod.twig is also patched by the kapatch of the
 * module ka_lib_kamod.
 *
 * The modifications are refreshed in this (admin) process, the storefront is rendered by a separate
 * request (tools/render_catalog.php) after the kamod cache is rebuilt.
 *
 * @group theme-files
 */
class ThemeFilesTest extends LibraryTestCase {

	const ROUTE       = 'extension/ka_lib_test/theme_file';
	const KAMOD_ROUTE = 'extension/ka_lib_test/theme_file_kamod';
	const OCMOD_CODE  = 'ka_lib_ocmod_theme_files';
	const SETTING     = 'ka_extensions_modify_theme_files';

	protected function setUp(): void {
		$this->bootAdmin();
		$this->cleanUp();
	}

	protected function tearDown(): void {
		$this->cleanUp();
		$this->refreshModifications();
	}

	protected function cleanUp() {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "theme` WHERE route LIKE 'extension/ka_lib_test/%'");
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE code = '" . $this->esc(static::OCMOD_CODE) . "'");
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $this->esc(static::SETTING) . "'");
		$this->oc->get('config')->set(static::SETTING, null);
	}

	protected function setOption($value) {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $this->esc(static::SETTING) . "'");
		$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'ka_extensions', `key` = '" . $this->esc(static::SETTING) . "', `value` = '" . (int)$value . "', serialized = '0'");
		$this->oc->get('config')->set(static::SETTING, (int)$value);
	}

	protected function addOcmod() {
		$xml = '<?xml version="1.0" encoding="utf-8"?>
<modification>
	<name>Ka Library theme files test</name>
	<code>' . static::OCMOD_CODE . '</code>
	<version>1.0</version>
	<author>ka_lib_test</author>
	<link></link>
	<file path="catalog/view/theme/*/template/extension/ka_lib_test/theme_file*.twig">
		<operation>
			<search><![CDATA[file-end]]></search>
			<add position="replace"><![CDATA[file-end [ka-lib-ocmod]]]></add>
		</operation>
	</file>
</modification>';

		$this->db()->query("INSERT INTO `" . DB_PREFIX . "modification` SET extension_install_id = '0', name = 'Ka Library theme files test', `code` = '" . $this->esc(static::OCMOD_CODE) . "', author = 'ka_lib_test', version = '1.0', link = '', xml = '" . $this->esc($xml) . "', status = '1', date_added = NOW()");
	}

	/*
		Saves a template as Design > Theme Editor does (the model rebuilds the theme cache of Ka Extensions).
	*/
	protected function editTheme($theme, $route, $code) {
		$this->oc->model('design/theme')->editTheme(0, $theme, $route, $code);
	}

	/*
		Extensions > Modifications > Refresh, then the kamod cache is rebuilt as on the next request.
	*/
	protected function refreshModifications() {
		$result = $this->oc->request('marketplace/modification/refresh');
		$this->assertNotEmpty($result['redirect'], $result['output']);

		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
	}

	protected function render($route, $theme = 'default') {
		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;
		$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';

		$process = proc_open(
			array($php, __DIR__ . '/tools/render_catalog.php', $platform_dir, \KaTesting\Env::getStoreDir(), $route, $theme),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		$this->assertSame(0, proc_close($process), $output . $errors);

		return trim($output);
	}

	protected function prepare($option) {
		$this->setOption($option);
		$this->addOcmod();
		$this->editTheme('default', static::ROUTE, 'ka-lib-theme-file: theme-editor file-end');
		$this->refreshModifications();
	}

	public function testOptionOnDefaultTheme() {
		$this->prepare(1);
		$this->assertSame('ka-lib-theme-file: theme-editor file-end [ka-lib-ocmod]', $this->render(static::ROUTE));
	}

	public function testOptionOffDefaultTheme() {
		$this->prepare(0);
		$this->assertSame('ka-lib-theme-file: theme-editor file-end', $this->render(static::ROUTE));
	}

	/*
		A theme without its own copy of the template uses the default one, the template changed in the theme
		editor for the default theme with the modification.
	*/
	public function testOptionOnThemeWithoutTemplateUsesDefault() {
		$this->prepare(1);
		$this->assertFileDoesNotExist(DIR_CATALOG . 'view/theme/ka_lib_theme/template/' . static::ROUTE . '.twig');
		$this->assertSame('ka-lib-theme-file: theme-editor file-end [ka-lib-ocmod]', $this->render(static::ROUTE, 'ka_lib_theme'));
	}

	public function testOptionOffThemeWithoutTemplateUsesDefault() {
		$this->prepare(0);
		$this->assertSame('ka-lib-theme-file: theme-editor file-end', $this->render(static::ROUTE, 'ka_lib_theme'));
	}

	/*
		A theme with its own copy of the template changed in the theme editor for that theme.
	*/
	public function testOptionOnThemeWithOwnTemplate() {
		$this->setOption(1);
		$this->addOcmod();
		$this->editTheme('ka_lib_theme2', static::ROUTE, 'ka-lib-theme-file: theme2 theme-editor file-end');
		$this->refreshModifications();

		$this->assertSame('ka-lib-theme-file: theme2 theme-editor file-end [ka-lib-ocmod]', $this->render(static::ROUTE, 'ka_lib_theme2'));
		// the default theme is not changed in the theme editor
		$this->assertSame('ka-lib-theme-file: original file-end [ka-lib-ocmod]', $this->render(static::ROUTE));
	}

	public function testOptionOffThemeWithOwnTemplate() {
		$this->setOption(0);
		$this->addOcmod();
		$this->editTheme('ka_lib_theme2', static::ROUTE, 'ka-lib-theme-file: theme2 theme-editor file-end');
		$this->refreshModifications();

		$this->assertSame('ka-lib-theme-file: theme2 theme-editor file-end', $this->render(static::ROUTE, 'ka_lib_theme2'));
	}

	/*
		Templates not changed in the theme editor get the modification with the option on and off.
	*/
	public function testTemplateWithoutThemeEditorChanges() {
		foreach (array(1, 0) as $option) {
			$this->setOption($option);
			$this->cleanUpOcmod();
			$this->addOcmod();
			$this->refreshModifications();

			$this->assertSame('ka-lib-theme-file: original file-end [ka-lib-ocmod]', $this->render(static::ROUTE), 'option ' . $option);
			$this->assertSame('ka-lib-theme-file: ka_lib_theme2 original file-end [ka-lib-ocmod]', $this->render(static::ROUTE, 'ka_lib_theme2'), 'option ' . $option);
		}
	}

	protected function cleanUpOcmod() {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE code = '" . $this->esc(static::OCMOD_CODE) . "'");
	}

	/*
		A template with an OCMOD modification and a kapatch: kapatches are applied to the theme editor
		version with the option on and off, the OCMOD modification only with the option on.
	*/
	public function testOcmodAndKapatchOptionOn() {
		$this->setOption(1);
		$this->addOcmod();
		$this->editTheme('default', static::KAMOD_ROUTE, 'ka-lib-theme-file-kamod: theme-editor file-end');
		$this->refreshModifications();

		$this->assertSame('ka-lib-theme-file-kamod: theme-editor file-end [ka-lib-kamod] [ka-lib-ocmod]', $this->stripKamodComment($this->render(static::KAMOD_ROUTE)));
	}

	public function testOcmodAndKapatchOptionOff() {
		$this->setOption(0);
		$this->addOcmod();
		$this->editTheme('default', static::KAMOD_ROUTE, 'ka-lib-theme-file-kamod: theme-editor file-end');
		$this->refreshModifications();

		$this->assertSame('ka-lib-theme-file-kamod: theme-editor file-end [ka-lib-kamod]', $this->stripKamodComment($this->render(static::KAMOD_ROUTE)));
	}

	/*
		The theme editor saves a template again after the refresh: the new text is shown (without the
		modification until the next refresh, as for any OCMOD change).
	*/
	public function testThemeEditorSaveAfterRefresh() {
		$this->prepare(1);
		$this->editTheme('default', static::ROUTE, 'ka-lib-theme-file: theme-editor v2 file-end');
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();

		$this->assertSame('ka-lib-theme-file: theme-editor v2 file-end', $this->render(static::ROUTE));

		$this->refreshModifications();
		$this->assertSame('ka-lib-theme-file: theme-editor v2 file-end [ka-lib-ocmod]', $this->render(static::ROUTE));
	}

	/**
	 * A template changed in the theme editor for a theme without its own copy of the template: core
	 * OpenCart shows the change (catalog/controller/event/theme.php), with Ka Extensions the storefront
	 * looks for the theme cache file of the default theme folder and the change is not shown (also
	 * without the option and in 4.1.1.30).
	 *
	 * @group known-bug
	 */
	public function testThemeEditorChangeOfInheritedTemplate() {
		$this->setOption(0);
		$this->editTheme('ka_lib_theme', static::ROUTE, 'ka-lib-theme-file: ka_lib_theme theme-editor file-end');
		$this->refreshModifications();

		$this->assertSame('ka-lib-theme-file: ka_lib_theme theme-editor file-end', $this->render(static::ROUTE, 'ka_lib_theme'));
	}

	protected function stripKamodComment($output) {
		return trim(preg_replace('/^\{#.*?#\}/s', '', $output));
	}
}
