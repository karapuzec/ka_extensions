<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\KaPatch;
use extension\ka_extensions\KamodBuilder;

class PatchLog {
	public $messages = array();

	public function log($msg, $type = 'I') {
		$this->messages[] = array($type, $msg);
	}
}

/**
 * KaPatch: xml patches applied to files before kamod uses them.
 *
 * @group kamod
 */
class KaPatchTest extends UnitTestCase {

	protected $dir;
	protected $log;

	protected function setUp(): void {
		parent::setUp();
		class_exists(KamodBuilder::class);
		$this->dir = sys_get_temp_dir() . '/ka_lib_unit_patch_' . uniqid() . '/';
		mkdir($this->dir . 'logs', 0777, true);
		$this->log = new PatchLog();
		if (!defined('DIR_LOGS')) {
			define('DIR_LOGS', sys_get_temp_dir() . '/ka_lib_unit_logs/');
		}
		@mkdir(DIR_LOGS, 0777, true);
	}

	protected function tearDown(): void {
		foreach (glob($this->dir . '*.xml') as $file) {
			unlink($file);
		}
		rmdir($this->dir . 'logs');
		rmdir($this->dir);
	}

	protected function patch($name, $operations, $version = '1.0') {
		$xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n"
			. ($version === null ? '<kamod>' : '<kamod version="' . $version . '">')
			. '<changes>' . $operations . '</changes></kamod>';
		file_put_contents($this->dir . $name, $xml);
		return $name;
	}

	protected function apply($contents, $patch) {
		$kapatch = new KaPatch($this->dir, $this->log);
		return $kapatch->applyXml($contents, $patch);
	}

	public function testReplace() {
		$patch = $this->patch('a.xml', '<operation type="replace"><search><![CDATA[return $twig->render(]]></search>'
			. '<text><![CDATA[$this->extendTwig($twig); return $twig->render(]]></text></operation>');
		$this->assertSame('<?php $this->extendTwig($twig); return $twig->render($a);', $this->apply('<?php return $twig->render($a);', $patch));
	}

	public function testRegexpReplace() {
		$patch = $this->patch('b.xml', '<operation type="replace"><search regexp="true"><![CDATA[function (\w+)\(\)]]></search>'
			. '<text><![CDATA[function $1($x = null)]]></text></operation>');
		$this->assertSame("function a(\$x = null) {}\nfunction b(\$x = null) {}", $this->apply("function a() {}\nfunction b() {}", $patch));
	}

	public function testSeveralPatches() {
		$a = $this->patch('a.xml', '<operation type="replace"><search>one</search><text>two</text></operation>');
		$b = $this->patch('b.xml', '<operation type="replace"><search>two</search><text>three</text></operation>');
		$kapatch = new KaPatch($this->dir, $this->log);
		$this->assertSame('three', $kapatch->applyXmlPatches('one', array($a, $b)));
		$this->assertSame('one', $kapatch->applyXmlPatches('one', array()));
	}

	public function testMissingPatternIsLogged() {
		$patch = $this->patch('a.xml', '<operation type="replace"><search>not here</search><text>x</text></operation>');
		$before = is_file(DIR_LOGS . 'kamod.errors.log') ? filesize(DIR_LOGS . 'kamod.errors.log') : 0;
		$this->assertSame('contents', $this->apply('contents', $patch));
		clearstatcache();
		$this->assertGreaterThan($before, filesize(DIR_LOGS . 'kamod.errors.log'));
	}

	public function testMissingXmlKeepsContents() {
		$this->assertSame('contents', $this->apply('contents', 'missing.xml'));
		$this->assertSame('E', $this->log->messages[0][0]);
	}

	public function testPatchWithoutVersionKeepsContents() {
		$patch = $this->patch('a.xml', '<operation type="replace"><search>c</search><text>x</text></operation>', null);
		$this->assertSame('contents', $this->apply('contents', $patch));
		$this->assertSame('E', $this->log->messages[0][0]);
	}

	public function testEmptyContents() {
		$patch = $this->patch('a.xml', '<operation type="replace"><search>c</search><text>x</text></operation>');
		$this->assertSame('', $this->apply('', $patch));
	}

	/**
	 * A patch for a newer kamod version is skipped and the contents are kept (applyXml() returned null,
	 * findAndApply() then wrote an empty file).
	 */
	public function testPatchOfANewerKamodVersionKeepsContents() {
		$patch = $this->patch('a.xml', '<operation type="replace"><search>c</search><text>x</text></operation>', '1.5');
		$this->assertSame('contents', $this->apply('contents', $patch));
	}

	public function testPatchOfAnotherMajorKamodVersionKeepsContents() {
		$patch = $this->patch('a.xml', '<operation type="replace"><search>c</search><text>x</text></operation>', '0.9');
		$this->assertSame('contents', $this->apply('contents', $patch));
	}

	/**
	 * KamodBuilder::log() writes the hour (it wrote 'M', the month name, instead of 'H').
	 */
	public function testKamodLogTime() {
		$file = DIR_LOGS . 'kamod.log';
		@unlink($file);
		KamodBuilder::log('test message');
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}: test message$/', trim(file_get_contents($file)));
	}
}
