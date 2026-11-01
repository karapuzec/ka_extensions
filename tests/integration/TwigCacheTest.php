<?php

namespace KaExtensionsTests\Integration;

/**
 * Compiled twig templates (DIR_CACHE/template) are removed when the kamod cache is rebuilt: the extension
 * installer keeps the file times of the archive, so a template of a new module version can be older than
 * its compiled copy and Twig (auto_reload compares file times only) would keep showing the old template.
 *
 * Rendering and the rebuild run in separate processes (tools/), like separate requests: a compiled
 * template class stays loaded in a process.
 *
 * @group kamod
 */
class TwigCacheTest extends LibraryTestCase {

	const ROUTE = 'ka_lib_twig_cache_test';
	const PART  = 'ka_lib_twig_cache_part';

	protected function setUp(): void {
		$this->bootAdmin();
	}

	protected function tearDown(): void {
		@unlink($this->templateFile(static::ROUTE));
		@unlink($this->templateFile(static::PART));
	}

	protected function templateFile($name) {
		return \KaTesting\Env::getStoreDir() . '/catalog/view/theme/default/template/' . $name . '.twig';
	}

	protected function runTool($tool, $args = array()) {
		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;
		$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';

		$process = proc_open(
			array_merge(array($php, __DIR__ . '/tools/' . $tool, $platform_dir, \KaTesting\Env::getStoreDir()), $args),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		$this->assertSame(0, proc_close($process), $output . $errors);

		return trim($output);
	}

	protected function render() {
		return $this->runTool('render_catalog.php', array(static::ROUTE));
	}

	/*
		The page template is given to Twig as a string (its compiled class depends on the text), the
		templates it extends or includes are read by the file loader: their compiled copies are checked by
		the file time only. So the changed template is an included one ('/default/...' path, like the
		module pages extending the common page templates).
	*/
	protected function writeTemplate($text, $age) {
		file_put_contents($this->templateFile(static::ROUTE), "{% include '/default/template/" . static::PART . ".twig' %}");
		file_put_contents($this->templateFile(static::PART), $text);
		touch($this->templateFile(static::PART), time() - $age);
	}

	public function testRebuildRemovesCompiledTemplates() {
		$dir = DIR_CACHE . 'template/zz';
		@mkdir($dir, 0777, true);
		file_put_contents($dir . '/compiled.php', '<?php // compiled template');

		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();

		$this->assertFileDoesNotExist($dir . '/compiled.php');
		$this->assertDirectoryDoesNotExist($dir);
		$this->assertDirectoryExists(DIR_CACHE . 'template', 'the cache folder itself stays');
	}

	public function testTemplateOlderThanCompiledCopyIsShownAfterRebuild() {
		$this->writeTemplate('old version', 7200);
		$this->assertSame('old version', $this->render());

		// a new version of the template with an older file time than the compiled copy, as unpacked by the installer
		$this->writeTemplate('new version', 3600);
		$this->assertSame('old version', $this->render(), 'Twig keeps the compiled copy newer than the file');

		$this->runTool('kamod_rebuild.php');

		$this->assertSame('new version', $this->render());
	}
}
