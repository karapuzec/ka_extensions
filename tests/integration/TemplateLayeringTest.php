<?php

namespace KaExtensionsTests\Integration;

/**
 * The rules of storefront template layering (theme T, a template path like extension/ka_lay/o_none_own):
 *
 * The base of the template, the first one found:
 * 1. the theme editor copy of theme T;
 * 2. the template file of theme T;
 * 3. (theme T has no own file) the theme editor copy of the default theme;
 * 4. the template file of the default theme.
 *
 * On top of the base, only the changes written for the file of the base (the file of theme T when the theme has
 * its own file, the default file otherwise):
 * - OCMOD modifications: always for a base file (2, 4), for a theme editor copy (1, 3) only with the setting
 *   'Modify Theme Files' on and after Extensions > Modifications > Refresh;
 * - then kapatches and kamod child templates: a kapatch in the folder of theme T does not change a template
 *   theme T does not have.
 *
 * The test checks every combination of:
 * - the theme: default, ka_lib_theme2 (has its own files), ka_lib_theme (no own files, uses the default ones);
 * - the theme editor copies of the template: none, of the default theme only, of the other themes only, of all themes;
 * - 'Modify Theme Files' on and off;
 * - an OCMOD modification of the template (templates o_*) or none (n_*);
 * - no kamod changes, a kapatch in the default folder, kapatches in the folders of the other themes, kamod
 *   child templates in all theme folders;
 * - before the refresh (the theme editor saves after the previous refresh) and after it;
 * - ka_speedup (one Twig environment for the request, emulated by the module ka_lib_speedup) on and off: the
 *   results are the same.
 *
 * Templates, OCMOD modification, the module ka_lib_layer with kapatches and child templates are written by the test.
 * The theme of the store is changed in the database, so the Twig environment re-used with ka_speedup (created
 * when the request starts) is the one of that theme.
 * With the environment variable KA_LAYERING_MATRIX_FILE the results are added to that file (json lines).
 *
 * @group theme-files
 */
class TemplateLayeringTest extends LibraryTestCase {

	const DIR        = 'extension/ka_lay/';
	const MODULE     = 'ka_lib_layer';
	const OCMOD_CODE = 'ka_lib_ocmod_layering';
	const SETTING    = 'ka_extensions_modify_theme_files';

	// the themes and whether they have their own copies of the templates
	protected static $themes = array(
		'default'       => true,
		'ka_lib_theme2' => true,
		'ka_lib_theme'  => false,
	);

	// themes with a theme editor copy of the template
	protected static $editor_states = array(
		'none' => array(),
		'def'  => array('default'),
		'own'  => array('ka_lib_theme', 'ka_lib_theme2'),
		'all'  => array('default', 'ka_lib_theme', 'ka_lib_theme2'),
	);

	protected $store_theme;

	protected function setUp(): void {
		$this->bootAdmin();
		$this->cleanUp();
	}

	protected function tearDown(): void {
		$this->cleanUp();
		$this->refreshModifications();
	}

	protected function cleanUp() {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "theme` WHERE route LIKE '" . $this->esc(static::DIR) . "%'");
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "modification` WHERE code = '" . $this->esc(static::OCMOD_CODE) . "'");
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $this->esc(static::SETTING) . "'");
		$this->oc->get('config')->set(static::SETTING, null);
		$this->setStoreTheme('default');

		foreach (array_keys(static::$themes) as $theme) {
			$this->removeDir(DIR_CATALOG . 'view/theme/' . $theme . '/template/' . static::DIR);
			$this->removeDir(DIR_CATALOG . 'view/theme/' . $theme . '/template/extension/ka_extensions/' . static::MODULE);
		}
		$this->removeDir(DIR_APPLICATION . 'controller/extension/ka_extensions/' . static::MODULE);
		$this->removeDir(DIR_APPLICATION . 'controller/extension/ka_extensions/ka_lib_speedup');
		$this->removeDir(DIR_SYSTEM . 'library/extension/ka_extensions/ka_lib_speedup');
	}

	protected function removeDir($dir) {
		if (is_dir($dir)) {
			\extension\ka_extensions\Directory::clearDirectory($dir);
			@rmdir($dir);
		}
	}

	protected function writeFile($file, $contents) {
		@mkdir(dirname($file), 0777, true);
		file_put_contents($file, $contents);
	}

	protected function setOption($value) {
		$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $this->esc(static::SETTING) . "'");
		$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'ka_extensions', `key` = '" . $this->esc(static::SETTING) . "', `value` = '" . (int)$value . "', serialized = '0'");
		$this->oc->get('config')->set(static::SETTING, (int)$value);
	}

	/*
		The theme of the default store (an enabled theme extension, see catalog/controller/event/theme.php). The
		storefront request reads it on start, before the first template is rendered.
	*/
	protected function setStoreTheme($theme) {
		foreach (array('ka_lib_theme', 'ka_lib_theme2') as $test_theme) {
			$this->db()->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = 'theme_" . $test_theme . "'");
		}
		$this->db()->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . $this->esc($theme) . "' WHERE store_id = '0' AND `key` = 'config_theme'");
		if ($theme != 'default') {
			$this->db()->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'theme_" . $this->esc($theme) . "', `key` = 'theme_" . $this->esc($theme) . "_status', `value` = '1', serialized = '0'");
		}
	}

	protected function route($ocmod, $kind, $editor) {
		return static::DIR . ($ocmod ? 'o' : 'n') . '_' . $kind . '_' . $editor;
	}

	protected function template($source) {
		return 'BASE:' . $source . ' a=[L1] END';
	}

	/*
		Templates of the default theme and ka_lib_theme2, the module ka_lib_layer with the kamod changes of $kind.
	*/
	protected function writeTemplates($ocmod, $kind) {

		$this->writeFile(DIR_CATALOG . 'view/theme/default/template/' . static::DIR . 'first.twig', 'FIRST');

		$module_dir = DIR_APPLICATION . 'controller/extension/ka_extensions/' . static::MODULE . '/';
		$this->writeFile($module_dir . 'kamod.ini', "module = \"" . static::MODULE . "\"\n");
		$this->writeFile($module_dir . 'active.kamod', '');

		foreach (array_keys(static::$editor_states) as $editor) {
			$route = $this->route($ocmod, $kind, $editor);

			foreach (static::$themes as $theme => $has_files) {
				if ($has_files) {
					$this->writeFile(DIR_CATALOG . 'view/theme/' . $theme . '/template/' . $route . '.twig', $this->template('orig-' . $theme));
				}

				$module_file = DIR_CATALOG . 'view/theme/' . $theme . '/template/extension/ka_extensions/' . static::MODULE . '/template/' . $route . '.twig';

				if ($kind == 'kpdef' && $theme == 'default') {
					$this->writeFile($module_file . '.xml', $this->kapatch('KD'));
				} elseif ($kind == 'kptheme' && $theme != 'default') {
					$this->writeFile($module_file . '.xml', $this->kapatch('KT:' . $theme));
				} elseif ($kind == 'child') {
					$marker = ($theme == 'default') ? 'CD' : 'CT:' . $theme;
					$this->writeFile($module_file, '{% block parent_template %}{{ parent() }} +' . $marker . '{% endblock %}');
				}
			}
		}
	}

	protected function kapatch($marker) {
		return '<kapatch version="1.0">
	<changes>
		<operation type="replace">
			<search><![CDATA[[L1]]]></search>
			<text><![CDATA[[L1][' . $marker . ']]]></text>
		</operation>
	</changes>
</kapatch>';
	}

	protected function addOcmod() {
		$xml = '<?xml version="1.0" encoding="utf-8"?>
<modification>
	<name>Ka Library template layering test</name>
	<code>' . static::OCMOD_CODE . '</code>
	<version>1.0</version>
	<author>ka_lib_test</author>
	<link></link>
	<file path="catalog/view/theme/*/template/' . static::DIR . 'o_*.twig">
		<operation>
			<search><![CDATA[[L1]]]></search>
			<add position="replace"><![CDATA[[L1][O]]]></add>
		</operation>
	</file>
</modification>';

		$this->db()->query("INSERT INTO `" . DB_PREFIX . "modification` SET extension_install_id = '0', name = 'Ka Library template layering test', `code` = '" . $this->esc(static::OCMOD_CODE) . "', author = 'ka_lib_test', version = '1.0', link = '', xml = '" . $this->esc($xml) . "', status = '1', date_added = NOW()");
	}

	/*
		The module ka_lib_speedup with the kapatch of ka_speedup (see TwigModificationTest): Template\Twig::render()
		renders all templates of the request with the Twig environment of the first one.
	*/
	protected function addSpeedupModule() {
		$dir = DIR_APPLICATION . 'controller/extension/ka_extensions/ka_lib_speedup/';
		$this->writeFile($dir . 'kamod.ini', "module = \"ka_lib_speedup\"\n");
		$this->writeFile($dir . 'active.kamod', '');

		$this->writeFile(DIR_SYSTEM . 'library/extension/ka_extensions/ka_lib_speedup/system/library/template/twig.php.xml', '<kapatch version="1.0">
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

	/*
		Saves the templates as Design > Theme Editor does (the model rebuilds the theme cache of Ka Extensions).
	*/
	protected function editTemplates($ocmod, $kind) {
		foreach (static::$editor_states as $editor => $themes) {
			foreach ($themes as $theme) {
				$this->oc->model('design/theme')->editTheme(0, $theme, $this->route($ocmod, $kind, $editor), $this->template('edit-' . $theme));
			}
		}
	}

	protected function refreshModifications() {
		$result = $this->oc->request('marketplace/modification/refresh');
		$this->assertNotEmpty($result['redirect'], $result['output']);

		$this->rebuild();
	}

	/*
		The kamod cache is rebuilt as on the next request.
	*/
	protected function rebuild() {
		$level = error_reporting(E_ALL & ~E_DEPRECATED);
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
		error_reporting($level);
	}

	/*
		Renders the routes in one storefront request of the theme (tools/render_catalog.php), the first route is
		a template without changes, so with ka_speedup the others are rendered with a re-used Twig environment.
	*/
	protected function render($theme, $routes) {
		$this->setStoreTheme($theme);

		$php = getenv('KA_TEST_PHP') ?: PHP_BINARY;
		$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';

		$process = proc_open(
			array($php, __DIR__ . '/tools/render_catalog.php', $platform_dir, \KaTesting\Env::getStoreDir(),
				static::DIR . 'first,' . implode(',', $routes), $theme
			),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);
		$this->assertSame(0, proc_close($process), $output . $errors);

		$this->setStoreTheme('default');

		$outputs = explode('|', $output);
		$this->assertSame('FIRST', trim(array_shift($outputs)), $output . $errors);

		$result = array();
		foreach (array_values($routes) as $i => $route) {
			$result[$route] = isset($outputs[$i]) ? trim(preg_replace('/\s+/', ' ', preg_replace('/\{#.*?#\}/s', '', $outputs[$i]))) : null;
		}

		return $result;
	}

	/*
		The expected template by the rules (see the class comment).
	*/
	protected function expected($theme, $ocmod, $kind, $editor, $option, $refreshed) {

		$has_file = static::$themes[$theme];
		$copies   = static::$editor_states[$editor];

		$is_copy = true;
		if (in_array($theme, $copies)) {
			$base = 'edit-' . $theme;
		} elseif ($has_file) {
			$base = 'orig-' . $theme;
			$is_copy = false;
		} elseif (in_array('default', $copies)) {
			$base = 'edit-default';
		} else {
			$base = 'orig-default';
			$is_copy = false;
		}

		// the file the base stands for
		$file_theme = $has_file ? $theme : 'default';

		$changes = '';
		if ($ocmod && (!$is_copy || ($option && $refreshed))) {
			$changes = '[O]';
		}
		if ($kind == 'kpdef' && $file_theme == 'default') {
			$changes = '[KD]' . $changes;
		}
		if ($kind == 'kptheme' && $file_theme != 'default') {
			$changes = '[KT:' . $file_theme . ']' . $changes;
		}

		$result = 'BASE:' . $base . ' a=[L1]' . $changes . ' END';

		if ($kind == 'child') {
			$result .= ' +' . ($file_theme == 'default' ? 'CD' : 'CT:' . $file_theme);
		}

		return $result;
	}

	/*
		One test per OCMOD modification and kind of kamod changes, every one checks 'Modify Theme Files' on and off
		with ka_speedup off and on (the platform test case does not support data providers).
	*/
	public function testOcmodNoKamod() { $this->checkMatrix(1, 'none'); }
	public function testOcmodKapatchOfDefaultTemplate() { $this->checkMatrix(1, 'kpdef'); }
	public function testOcmodKapatchesOfThemeTemplates() { $this->checkMatrix(1, 'kptheme'); }
	public function testOcmodChildTemplates() { $this->checkMatrix(1, 'child'); }
	public function testNoOcmodNoKamod() { $this->checkMatrix(0, 'none'); }
	public function testNoOcmodKapatchOfDefaultTemplate() { $this->checkMatrix(0, 'kpdef'); }
	public function testNoOcmodKapatchesOfThemeTemplates() { $this->checkMatrix(0, 'kptheme'); }
	public function testNoOcmodChildTemplates() { $this->checkMatrix(0, 'child'); }

	protected function checkMatrix($ocmod, $kind) {

		$expected = array();
		$actual   = array();

		foreach (array(1, 0) as $option) {
			foreach (array(0, 1) as $speedup) {
				$this->cleanUp();
				$this->checkCase($ocmod, $kind, $option, $speedup, $expected, $actual);
			}
		}

		$this->assertSame($expected, $actual);
	}

	protected function checkCase($ocmod, $kind, $option, $speedup, &$expected, &$actual) {

		$prefix = 'option ' . ($option ? 'on' : 'off') . ', speedup ' . ($speedup ? 'on' : 'off') . ', ';

		$this->setOption($option);
		if ($speedup) {
			$this->addSpeedupModule();
		}
		$this->writeTemplates($ocmod, $kind);
		if ($ocmod) {
			$this->addOcmod();
		}
		$this->refreshModifications();

		if ($speedup) {
			$twig_classes = '';
			foreach (glob(\extension\ka_extensions\KamodManager::getKamodCacheDir() . 'system/template/twig.*.kamod.php') as $file) {
				$twig_classes .= file_get_contents($file);
			}
			$this->assertStringContainsString('static $ka_twig_engine', $twig_classes);
		}

		// the theme editor saves after the refresh, then the refresh
		$this->editTemplates($ocmod, $kind);
		$this->rebuild();

		$routes = array();
		foreach (array_keys(static::$editor_states) as $editor) {
			$routes[$editor] = $this->route($ocmod, $kind, $editor);
		}

		foreach (array(0, 1) as $refreshed) {
			if ($refreshed) {
				$this->refreshModifications();
			}

			foreach (array_keys(static::$themes) as $theme) {
				$outputs = $this->render($theme, $routes);
				foreach ($routes as $editor => $route) {
					$key = $prefix . ($refreshed ? 'after' : 'before') . ' refresh, ' . $theme . ', editor ' . $editor;
					$expected[$key] = $this->expected($theme, $ocmod, $kind, $editor, $option, $refreshed);
					$actual[$key]   = $outputs[$route];

					if (getenv('KA_LAYERING_MATRIX_FILE')) {
						file_put_contents(getenv('KA_LAYERING_MATRIX_FILE'), json_encode(array(
							'ocmod' => $ocmod, 'kind' => $kind, 'option' => $option, 'speedup' => $speedup,
							'refreshed' => $refreshed, 'theme' => $theme, 'editor' => $editor,
							'expected' => $expected[$key], 'actual' => $actual[$key],
						)) . "\n", FILE_APPEND);
					}
				}
			}
		}

		// a kapatch in the folder of a theme without its own template is not applied to anything (no cache file)
		if ($kind == 'kptheme') {
			$cache_dir = \extension\ka_extensions\KamodManager::getKamodTemplatesDir() . 'catalog/view/theme/';
			$actual[$prefix . 'kapatch cache of ka_lib_theme'] = is_file($cache_dir . 'ka_lib_theme/template/' . $routes['none'] . '.twig');
			$expected[$prefix . 'kapatch cache of ka_lib_theme'] = false;
			$actual[$prefix . 'kapatch cache of ka_lib_theme2'] = is_file($cache_dir . 'ka_lib_theme2/template/' . $routes['none'] . '.twig');
			$expected[$prefix . 'kapatch cache of ka_lib_theme2'] = true;
		}
	}
}
