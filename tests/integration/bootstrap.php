<?php
/*
	Bootstrap of the integration tests. The platform (ka_oc3_testing) restores the store database
	from the snapshot once per run. The store has Ka Extensions built from this working copy and
	the test module of tests/fixtures/store (copied by tests/run.sh).
*/

$platform_dir = getenv('KA_TEST_PLATFORM_DIR') ?: dirname(__DIR__) . '/platform';
require_once($platform_dir . '/bootstrap.php');

require_once(__DIR__ . '/LibraryTestCase.php');
