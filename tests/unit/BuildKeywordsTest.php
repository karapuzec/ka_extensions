<?php

namespace KaExtensionsTests\Unit;

/**
 * ka_build.php: the header keywords $Project$, $Author$, $Version$, $Revision$ are written into the
 * package copies of the packed files (git and svn modules), the source files are not changed.
 * Builds a small module in a temporary repository with the ka_build.php of this repository.
 *
 * @group build
 */
class BuildKeywordsTest extends \PHPUnit\Framework\TestCase {

	protected $dir;

	const HEADER = "<?php\r\n/*\r\n\t\$Project\$\r\n\t\$Author\$\r\n\r\n\t\$Version\$ (\$Revision\$)\r\n*/\r\n";

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/ka_build_kw_' . getmypid() . '_' . mt_rand();
		mkdir($this->dir . '/wc', 0777, true);
	}

	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->dir));
	}

	protected function run_cmd($cmd, $dir = null) {
		$output = array();
		$result = 0;
		exec('cd ' . escapeshellarg($dir ?: $this->dir . '/wc') . ' && ' . $cmd . ' 2>&1', $output, $result);
		$this->assertSame(0, $result, $cmd . "\n" . implode("\n", $output));
		return implode("\n", $output);
	}

	protected function put($file, $text) {
		$path = $this->dir . '/wc/' . $file;
		if (!is_dir(dirname($path))) {
			mkdir(dirname($path), 0777, true);
		}
		file_put_contents($path, $text);
	}

	protected function writeModule($vcs, $extra = '') {
		$this->put('admin/controller/extension/ka_extensions/kwt.php', self::HEADER . "class ControllerExtensionKaExtensionsKwt {\r\n\tprotected \$extension_version = '1.0.0.0';\r\n}\r\n");
		// stale values copied from svn
		$this->put('system/library/kwt/stale.php', "<?php\n/*\n \$Project: Old Name \$\n \$Author: someone \$\n \$Version: 0.9.0.0 \$ (\$Revision: 640 \$)\n \$Id\$ \$Date\$\n*/\n");
		$this->put('system/library/kwt/vendor/lib.js', "/* \$Version\$ */\n");
		$this->put('system/library/kwt/logo.png', "\x89PNG\0 \$Version\$");
		$this->put('manifest.ini', "name = \"Kw Test\"\ncode = \"kwt\"\nversion = \"1.2.3.4\"\nfile = \"kwt_ver\"\n"
			. "vcs = \"$vcs\"\nstart_revision = \"" . ($vcs == 'git' ? 'root' : '0') . "\"\ntarget_oc_version = \"3.0.0.0\"\nsvn_keywords = \"N\"\n"
			. "upload_dirs[] = \"admin/controller/extension/ka_extensions\"\nupload_dirs[] = \"system/library/kwt\"\n" . $extra);
	}

	protected function build() {
		$this->run_cmd(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/ka_build.php') . ' manifest.ini --out=' . escapeshellarg($this->dir . '/out'));
		$zip = new \ZipArchive();
		$this->assertTrue($zip->open($this->dir . '/out/tmp_pack/kwt_ver1.2.3.4.ocmod.zip') === true);
		$files = array();
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
		}
		return $files;
	}

	protected function gitModule($extra = '') {
		$this->writeModule('git', $extra);
		$git = 'git -c user.name=t -c user.email=t@example.com';
		$this->run_cmd("git init -q && $git add -A && $git commit -q -m 1 && $git commit -q --allow-empty -m 2 && $git commit -q --allow-empty -m 3");
	}

	public function testGitKeywordsAreWrittenToThePackage() {
		$this->gitModule("keywords_project = \"Kw Project\"\n");
		$files = $this->build();

		// bare keywords, CRLF kept; the revision is the commit count of HEAD
		$this->assertStringStartsWith("<?php\r\n/*\r\n\t\$Project: Kw Project \$\r\n\t\$Author: karapuz team <support@ka-station.com> \$\r\n\r\n\t\$Version: 1.2.3.4 \$ (\$Revision: 3 \$)\r\n*/\r\n",
			$files['upload/admin/controller/extension/ka_extensions/kwt.php']);
		// stale values are written again, other keywords are not touched
		$this->assertSame("<?php\n/*\n \$Project: Kw Project \$\n \$Author: karapuz team <support@ka-station.com> \$\n \$Version: 1.2.3.4 \$ (\$Revision: 3 \$)\n \$Id\$ \$Date\$\n*/\n",
			$files['upload/system/library/kwt/stale.php']);
		// vendor folders and binary files are not changed
		$this->assertSame("/* \$Version\$ */\n", $files['upload/system/library/kwt/vendor/lib.js']);
		$this->assertSame("\x89PNG\0 \$Version\$", $files['upload/system/library/kwt/logo.png']);
		// the source files are not changed
		$this->assertSame("<?php\n/*\n \$Project: Old Name \$\n \$Author: someone \$\n \$Version: 0.9.0.0 \$ (\$Revision: 640 \$)\n \$Id\$ \$Date\$\n*/\n", file_get_contents($this->dir . '/wc/system/library/kwt/stale.php'));
		$this->assertStringContainsString("\t\$Project\$\r\n", file_get_contents($this->dir . '/wc/admin/controller/extension/ka_extensions/kwt.php'));
	}

	public function testDefaultsAndRepeatedBuild() {
		$this->gitModule();
		$first = $this->build();
		$this->assertStringContainsString("\$Project: Kw Test \$", $first['upload/system/library/kwt/stale.php']);
		$this->assertStringContainsString("\$Author: karapuz team <support@ka-station.com> \$", $first['upload/system/library/kwt/stale.php']);
		$this->assertSame($first, $this->build());
	}

	public function testKeywordsCanBeTurnedOff() {
		$this->gitModule("expand_keywords = \"N\"\n");
		$files = $this->build();
		$this->assertStringContainsString("\t\$Project\$\r\n", $files['upload/admin/controller/extension/ka_extensions/kwt.php']);
		$this->assertStringContainsString("\$Revision: 640 \$", $files['upload/system/library/kwt/stale.php']);
	}

	/*
		svn: values expanded by svn (svn:keywords) are kept, bare keywords get the working copy revision
	*/
	public function testSvnExpandedValuesAreKept() {
		if (!is_executable('/usr/bin/svnadmin') && !is_executable('/usr/local/bin/svnadmin')) {
			$this->markTestSkipped('svnadmin is not installed');
		}
		$repo = $this->dir . '/repo';
		$this->run_cmd('svnadmin create ' . escapeshellarg($repo), $this->dir);
		$this->run_cmd('rmdir wc && svn checkout -q ' . escapeshellarg('file://' . $repo) . ' wc', $this->dir);
		$this->writeModule('svn');
		$this->run_cmd('svn add -q --force . && svn propset -q svn:keywords "Revision Version=1.2.3.4 Project=Svn%_Name Author=svn%_author" admin/controller/extension/ka_extensions/kwt.php'
			. ' && svn commit -q -m 1 && svn mkdir -q -m 2 ' . escapeshellarg('file://' . $repo . '/other') . ' && svn update -q');
		putenv('KA_BUILD_SVN');
		$files = $this->build();

		// expanded by svn (the file revision 1), not changed by the build
		$this->assertStringStartsWith("<?php\r\n/*\r\n\t\$Project: Svn Name \$\r\n\t\$Author: svn author \$\r\n\r\n\t\$Version: 1.2.3.4 \$ (\$Revision: 1 \$)\r\n",
			$files['upload/admin/controller/extension/ka_extensions/kwt.php']);
		// a file without svn:keywords: stale values are kept, bare keywords are written (working copy revision 2)
		$this->assertStringContainsString("\$Revision: 640 \$", $files['upload/system/library/kwt/stale.php']);
		$this->put('system/library/kwt/bare.php', self::HEADER);
		$this->run_cmd('svn add -q system/library/kwt/bare.php && svn commit -q -m 3 && svn update -q');
		$files = $this->build();
		$this->assertStringStartsWith("<?php\r\n/*\r\n\t\$Project: Kw Test \$\r\n\t\$Author: karapuz team <support@ka-station.com> \$\r\n\r\n\t\$Version: 1.2.3.4 \$ (\$Revision: 3 \$)\r\n",
			$files['upload/system/library/kwt/bare.php']);
	}
}
