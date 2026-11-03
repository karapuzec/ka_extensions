<?php

namespace KaExtensionsTests\Integration;

/**
 * Storefront templates changed by an OCMOD modification (DIR_MODIFICATION/catalog/view/theme) are found by
 * the Twig loader of the library, not only the template whose code is passed to Template\Twig::render():
 * - a template included by another template ({% include %} is resolved by the loader);
 * - templates rendered with a re-used Twig environment (ka_speedup keeps the environment of the first
 *   render() of the request in a static variable, the next templates are resolved by its loader).
 * Before the fix both were read from the original theme folder without the modification.
 * The priority of the theme editor copies and of the kamod cache over the modification folder is checked
 * by ThemeFilesTest (option 'Modify Theme Files' off, kapatch with an OCMOD modification).
 *
 * The templates, the modification and the module emulating ka_speedup (ka_lib_speedup, its kapatch of
 * system/library/template/twig.php is the one of ka_speedup) are written by the test.
 *
 * @group kamod
 */
class TwigModificationTest extends LibraryTestCase {

	const OCMOD_CODE = 'ka_lib_ocmod_twig_loader';
	const DIR        = 'extension/ka_lib_test/';

	protected function setUp(): void {
		$this->bootAdmin();
		$this->cleanUp();
		$this->writeTemplate('twig_mod_a', 'twig-mod-a file-end');
		$this->writeTemplate('twig_mod_b', 'twig-mod-b file-end');
		$this->writeTemplate('twig_mod_outer', "outer: {% include 'default/template/" . static::DIR . "twig_mod_a.twig' %}");
		$this->addOcmod();
	}

	protected function tearDown(): void {
		$this->cleanUp();
		$this->refreshModifications();
	}

	protected function cleanUp() {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE code = '" . $this->esc(static::OCMOD_CODE) . "'");
		foreach (array('twig_mod_a', 'twig_mod_b', 'twig_mod_outer') as $name) {
			@unlink($this->themeDir() . static::DIR . $name . '.twig');
		}
		$this->removeSpeedupModule();
	}

	protected function themeDir() {
		return DIR_CATALOG . 'view/theme/default/template/';
	}

	protected function writeTemplate($name, $code) {
		@mkdir($this->themeDir() . static::DIR, 0777, true);
		file_put_contents($this->themeDir() . static::DIR . $name . '.twig', $code);
	}

	protected function addOcmod() {
		$xml = '<?xml version="1.0" encoding="utf-8"?>
<modification>
	<name>Ka Library twig loader test</name>
	<code>' . static::OCMOD_CODE . '</code>
	<version>1.0</version>
	<author>ka_lib_test</author>
	<link></link>
	<file path="catalog/view/theme/default/template/extension/ka_lib_test/twig_mod_{a,b}.twig">
		<operation>
			<search><![CDATA[file-end]]></search>
			<add position="replace"><![CDATA[file-end [ka-lib-ocmod]]]></add>
		</operation>
	</file>
</modification>';

		$this->db()->query("INSERT INTO `" . DB_PREFIX . "modification` SET extension_install_id = '0', name = 'Ka Library twig loader test', `code` = '" . $this->esc(static::OCMOD_CODE) . "', author = 'ka_lib_test', version = '1.0', link = '', xml = '" . $this->esc($xml) . "', status = '1', date_added = NOW()");
	}

	/*
		Extensions > Modifications > Refresh, then the kamod cache is rebuilt as on the next request.
	*/
	protected function refreshModifications() {
		$result = $this->oc->request('marketplace/modification/refresh');
		$this->assertNotEmpty($result['redirect'], $result['output']);

		$this->rebuild();
	}

	protected function rebuild() {
		$level = error_reporting(E_ALL & ~E_DEPRECATED);
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
		error_reporting($level);
	}

	/*
		The module ka_lib_speedup with the kapatch of ka_speedup: Template\Twig::render() keeps the first Twig
		environment of the request in a static variable and renders the next templates with it.
	*/
	protected function addSpeedupModule() {
		$dir = DIR_APPLICATION . 'controller/extension/ka_extensions/ka_lib_speedup/';
		@mkdir($dir, 0777, true);
		file_put_contents($dir . 'kamod.ini', "module = \"ka_lib_speedup\"\n");
		file_put_contents($dir . 'active.kamod', '');

		$dir = DIR_SYSTEM . 'library/extension/ka_extensions/ka_lib_speedup/system/library/template/';
		@mkdir($dir, 0777, true);
		file_put_contents($dir . 'twig.php.xml', '<kapatch version="1.0">
	<changes>
		<operation skip="true" type="replace">
			<search><![CDATA[final class Twig ]]></search>
			<text><![CDATA[class Twig ]]></text>
		</operation>
		<operation type="replace">
			<search regexp="true"><![CDATA[n render\((.*)\n]]></search>
			<text><![CDATA[n render(\1
			static $ka_twig_engine = null;
			if (!empty($ka_twig_engine)) {
				return $ka_twig_engine->render($filename . \'.twig\', $this->data);
			}
]]></text>
		</operation>
		<operation skip="true" type="replace">
			<search><![CDATA[private $]]></search>
			<text><![CDATA[protected $]]></text>
		</operation>
	</changes>
</kapatch>');
	}

	protected function removeSpeedupModule() {
		foreach (array(DIR_APPLICATION . 'controller/extension/ka_extensions/ka_lib_speedup', DIR_SYSTEM . 'library/extension/ka_extensions/ka_lib_speedup') as $dir) {
			if (is_dir($dir)) {
				\extension\ka_extensions\Directory::clearDirectory($dir);
				@rmdir($dir);
			}
		}
	}

	/*
		Renders the routes (comma separated) in one storefront request, see tools/render_catalog.php.
	*/
	protected function render($routes) {
		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;
		$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';

		$process = proc_open(
			array($php, __DIR__ . '/tools/render_catalog.php', $platform_dir, \KaTesting\Env::getStoreDir(), $routes),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		$this->assertSame(0, proc_close($process), $output . $errors);

		return trim($output);
	}

	public function testModifiedTemplate() {
		$this->refreshModifications();

		$this->assertFileExists(DIR_MODIFICATION . 'catalog/view/theme/default/template/' . static::DIR . 'twig_mod_a.twig');
		$this->assertSame('twig-mod-a file-end [ka-lib-ocmod]', $this->render(static::DIR . 'twig_mod_a'));
	}

	/*
		{% include %} of a modified template: the included template is found by the loader.
	*/
	public function testIncludedModifiedTemplate() {
		$this->refreshModifications();

		$this->assertSame('outer: twig-mod-a file-end [ka-lib-ocmod]', $this->render(static::DIR . 'twig_mod_outer'));
	}

	/*
		Two modified templates in one request without a re-used environment: both have the modification.
	*/
	public function testTwoModifiedTemplatesInOneRequest() {
		$this->refreshModifications();

		$this->assertSame('twig-mod-a file-end [ka-lib-ocmod]|twig-mod-b file-end [ka-lib-ocmod]',
			$this->render(static::DIR . 'twig_mod_a,' . static::DIR . 'twig_mod_b'));
	}

	/*
		ka_speedup: the second template of the request is rendered with the environment of the first one,
		it has the modification too (it was the original template before the fix).
	*/
	public function testReusedEnvironmentFindsModifiedTemplates() {
		$this->addSpeedupModule();
		$this->refreshModifications();

		$twig_classes = '';
		foreach (glob(\extension\ka_extensions\KamodManager::getKamodCacheDir() . 'system/template/twig.*.kamod.php') as $file) {
			$twig_classes .= file_get_contents($file);
		}
		$this->assertStringContainsString('static $ka_twig_engine', $twig_classes);

		$this->assertSame('twig-mod-a file-end [ka-lib-ocmod]|twig-mod-b file-end [ka-lib-ocmod]',
			$this->render(static::DIR . 'twig_mod_a,' . static::DIR . 'twig_mod_b'));
		$this->assertSame('outer: twig-mod-a file-end [ka-lib-ocmod]|twig-mod-b file-end [ka-lib-ocmod]',
			$this->render(static::DIR . 'twig_mod_outer,' . static::DIR . 'twig_mod_b'));
	}
}
