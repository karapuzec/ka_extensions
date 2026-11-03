<?php

namespace KaExtensionsTests\Integration;

/**
 * Extensions > Modifications > Refresh and the kamod cache.
 *
 * The kamod cache is built over the OCMOD modification files and is rebuilt at the start of a request
 * when it is not valid. It was treated as valid when the time of its marker (valid.kamod) was not older
 * than the folder of the modification files, the times have a one-second resolution. A cache built
 * in the same second as the refresh wrote the new modification files (e.g. by the refresh request
 * itself after a module upgrade had marked the cache invalid) was built over the old files and stayed
 * (seen once with Ka Tags 2.0.1.0: the code of its removed OCMOD stayed in the admin product form until
 * the next refresh). The refresh marks the kamod cache invalid after the modification files are written.
 *
 * @group kamod
 */
class KamodRefreshTest extends LibraryTestCase {

	protected function setUp(): void {
		$this->bootAdmin();
	}

	protected function tearDown(): void {
		// the next request rebuilds the cache, as here
		\extension\ka_extensions\KamodManager::getInstance()->rebuildKamodCache();
	}

	protected function markerFile() {
		return DIR_STORAGE . \extension\ka_extensions\KamodManager::KAMOD_CACHE_DIR . '/valid.kamod';
	}

	public function testRefreshMarksTheKamodCacheInvalid() {
		$manager = \extension\ka_extensions\KamodManager::getInstance();
		$manager->rebuildKamodCache();
		$this->assertTrue($manager->isKamodCacheValid());

		$result = $this->oc->request('marketplace/modification/refresh');
		$this->assertNotEmpty($result['redirect'], $result['output']);

		// the cache and the new modification files in the same second
		clearstatcache();
		if (file_exists($this->markerFile())) {
			touch($this->markerFile(), filemtime(DIR_MODIFICATION . 'system'));
			clearstatcache();
		}
		$this->assertFalse($manager->isKamodCacheValid(), 'the next request rebuilds the kamod cache over the new modification files');

		$manager->rebuildKamodCache();
		$this->assertTrue($manager->isKamodCacheValid());
	}
}
