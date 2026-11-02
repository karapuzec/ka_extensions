<?php

namespace KaExtensionsTests\Integration;

/**
 * Kamod chain of a storefront template overridden by two modules: the base template has its own block,
 * the module ka_lib_chain_page replaces the whole page, ka_lib_chain_block overrides the block. The order
 * is set by install_before / install_after of ka_lib_chain_page. The modules are written by the test.
 *
 * @group kamod
 */
class TwigChainTest extends LibraryTestCase {

	const ROUTE = 'ka_lib_chain_test';
	const BASE  = "{% set label = 'info' %}<base>{% block info %}<{{ label }}>{% endblock %}</base>";

	protected function setUp(): void {
		$this->bootAdmin();
	}

	protected function tearDown(): void {
		@unlink($this->themeDir() . static::ROUTE . '.twig');
		foreach (array('ka_lib_chain_page', 'ka_lib_chain_block') as $module) {
			foreach (array(DIR_APPLICATION . 'controller/', $this->themeDir()) as $dir) {
				\extension\ka_extensions\Directory::clearDirectory($dir . 'extension/ka_extensions/' . $module);
				@rmdir($dir . 'extension/ka_extensions/' . $module);
			}
		}
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
	}

	protected function themeDir() {
		return \KaTesting\Env::getStoreDir() . '/catalog/view/theme/default/template/';
	}

	protected function addModule($module, $template, $ini = '') {
		$dir = DIR_APPLICATION . 'controller/extension/ka_extensions/' . $module . '/';
		@mkdir($dir, 0777, true);
		file_put_contents($dir . 'kamod.ini', "module = \"" . $module . "\"\n" . $ini . "\n");
		file_put_contents($dir . 'active.kamod', '');

		$file = $this->themeDir() . 'extension/ka_extensions/' . $module . '/template/' . static::ROUTE . '.twig';
		@mkdir(dirname($file), 0777, true);
		file_put_contents($file, $template);
	}

	protected function render($page, $ini) {
		file_put_contents($this->themeDir() . static::ROUTE . '.twig', static::BASE);
		$this->addModule('ka_lib_chain_block', "{% block info %}{{ parent() }}<vendor>{% endblock %}");
		if (!empty($page)) {
			$this->addModule('ka_lib_chain_page', $page, $ini);
		}
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();

		// the storefront is rendered in a separate process
		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;
		$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';
		$cmd = array($php, __DIR__ . '/tools/render_catalog.php', $platform_dir, \KaTesting\Env::getStoreDir(), static::ROUTE);
		$process = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		$this->assertSame(0, proc_close($process), $output . $errors);

		return preg_replace('/\{#.*?#\}|\s+/s', '', trim($output));
	}

	public function testBlockChildOverridesBlockOfBase() {
		$this->assertSame('<base><info><vendor></base>', $this->render('', ''));
	}

	public function testFullPageChildBeforeBlockChild() {
		$this->assertSame('<page>', $this->render('<page>', 'install_before = "ka_extensions/ka_lib_chain_block"'));
	}

	public function testFullPageChildAfterBlockChild() {
		$this->assertSame('<page>', $this->render('<page>', 'install_after = "ka_extensions/ka_lib_chain_block"'));
	}

	/*
		The full page has the block of the base inside its parent_template block (nested block): the
		outer block child adds its html there, an outer full page keeps its own empty block.
	*/
	public function testNestedBlockOfFullPageBeforeBlockChild() {
		$page = "{% block parent_template %}<page>{% block info %}{% endblock %}</page>{% endblock %}";
		$this->assertSame('<page><vendor></page>', $this->render($page, 'install_before = "ka_extensions/ka_lib_chain_block"'));
	}

	public function testNestedBlockOfFullPageAfterBlockChild() {
		$page = "{% block parent_template %}<page>{% block info %}{% endblock %}</page>{% endblock %}";
		$this->assertSame('<page></page>', $this->render($page, 'install_after = "ka_extensions/ka_lib_chain_block"'));
	}

	public function testParentOfFullPageIsBase() {
		$page = "{% block parent_template %}<page>{{ parent() }}</page>{% endblock %}";
		$this->assertSame('<page><base><info><vendor></base></page>', $this->render($page, 'install_before = "ka_extensions/ka_lib_chain_block"'));
	}
}
