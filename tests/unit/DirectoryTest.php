<?php

namespace KaExtensionsTests\Unit;

use extension\ka_extensions\Directory;

/**
 * Directory::clearDirectory(): a subfolder that cannot be deleted throws, the next call in the same request
 * still deletes only the contents of its folder (the nesting level was not restored before).
 *
 * @group helpers
 */
class DirectoryTest extends UnitTestCase {

	protected $dir;

	protected function setUp(): void {
		$this->dir = sys_get_temp_dir() . '/ka_dir_test_' . uniqid();
		mkdir($this->dir . '/locked/sub', 0777, true);
		touch($this->dir . '/locked/sub/file.txt');
		mkdir($this->dir . '/other/x', 0777, true);
		touch($this->dir . '/other/x/y.txt');
	}

	protected function tearDown(): void {
		@chmod($this->dir . '/locked', 0777);
		exec('rm -rf ' . escapeshellarg($this->dir));
	}

	public function testFailedDeleteDoesNotBreakNextCall() {
		if (function_exists('posix_getuid') && posix_getuid() == 0) {
			$this->markTestSkipped('root can delete a folder in a read-only folder');
		}
		chmod($this->dir . '/locked', 0555);

		try {
			@Directory::clearDirectory($this->dir . '/locked');
			$this->fail('no exception for a folder that cannot be deleted');
		} catch (\Exception $e) {
			$this->assertStringContainsString('cannot be deleted', $e->getMessage());
		}

		Directory::clearDirectory($this->dir . '/other');

		$this->assertDirectoryExists($this->dir . '/other', 'the folder itself stays');
		$this->assertDirectoryDoesNotExist($this->dir . '/other/x');
	}
}
