#!/bin/sh
# Runs the tests of the Ka Extensions library.
#
#   tests/run.sh [options] [unit] [setup] [integration]     (default steps: all three)
#
#   unit         PHPUnit tests of library classes with OpenCart stubs, no store and no database
#   setup        installs a clean OpenCart 3.0.5.1 with the Ka Extensions package built from the committed
#                HEAD of this working copy, saves the DB snapshot
#   integration  PHPUnit tests on that store (real OpenCart classes, MySQL)
#
# Options:
#   --group=a,b          only tests of these PHPUnit groups
#   --exclude-group=a,b  skip PHPUnit groups (known-bug is skipped by default)
#   --filter=pattern     PHPUnit --filter (test class or method name)
#
# Settings come from the environment or from tests/config.local.sh (see config.local.sh.dist):
#   KA_TEST_DB_USER, KA_TEST_DB_PASSWORD  database access (default: root, empty password)
#   KA_TEST_PLATFORM_DIR  the shared platform ka_oc3_testing (default: tests/platform, exported by the setup)
#   KA_TEST_PLATFORM_REV  platform revision exported into tests/platform (default: 14)
#   KA_TEST_STORE_DIR    store folder            (default: /tmp/ka_extensions_lib_store)
#   KA_TEST_STORE_URL    store url               (default: http://localhost:8177/)
#   KA_TEST_DB_NAME      database                (default: kat_ka_extensions_lib)
#   KA_TEST_DB_SNAPSHOT  snapshot file           (default: $KA_TEST_STORE_DIR.sql)
#   KA_TEST_KA_EXTENSIONS_ZIP  a ready package instead of the one built from this working copy
#   KA_TEST_OC_VERSION   OpenCart release        (default: 3.0.5.1)
#   KA_TEST_PHP          PHP binary              (default: php)
#   KA_BUILD_TOOLS       folder with ka_build.php and ka_gen.php (default: exported from ka_tools)
#   KA_BUILD_SVN         svn command for ka_build.php and the platform export (default: svn)
#
# The package is built from committed changes only (git): commit before the setup step.
set -e

TESTS_DIR=$(cd "$(dirname "$0")" && pwd)
[ -f "$TESTS_DIR/config.local.sh" ] && . "$TESTS_DIR/config.local.sh"

REPO_DIR=$(dirname "$TESTS_DIR")
PLATFORM_DIR=${KA_TEST_PLATFORM_DIR:-$TESTS_DIR/platform}
PHP=${KA_TEST_PHP:-php}
SVN=${KA_BUILD_SVN:-svn}

export KA_TEST_PLATFORM_DIR="$PLATFORM_DIR"
export KA_TEST_STORE_DIR=${KA_TEST_STORE_DIR:-/tmp/ka_extensions_lib_store}
export KA_TEST_STORE_URL=${KA_TEST_STORE_URL:-http://localhost:8177/}
export KA_TEST_DB_NAME=${KA_TEST_DB_NAME:-kat_ka_extensions_lib}
export KA_TEST_DB_SNAPSHOT=${KA_TEST_DB_SNAPSHOT:-$KA_TEST_STORE_DIR.sql}
export KA_TEST_PHP="$PHP"
export KA_TEST_DB_USER=${KA_TEST_DB_USER:-root}
export KA_TEST_DB_PASSWORD=${KA_TEST_DB_PASSWORD:-}
export KA_BUILD_TOOLS KA_BUILD_SVN

# setup deletes the store folder, so it must never be the repository or a folder inside it
STORE_DIR_REAL=$(cd "$KA_TEST_STORE_DIR" 2>/dev/null && pwd -P || echo "$KA_TEST_STORE_DIR")
REPO_DIR_REAL=$(cd "$REPO_DIR" && pwd -P)
case "$STORE_DIR_REAL/" in
"$REPO_DIR_REAL"/*)
	echo "KA_TEST_STORE_DIR ($KA_TEST_STORE_DIR) must be outside the repository ($REPO_DIR)" >&2
	exit 1
	;;
esac
case "$REPO_DIR_REAL/" in
"$STORE_DIR_REAL"/*)
	echo "KA_TEST_STORE_DIR ($KA_TEST_STORE_DIR) must not contain the repository ($REPO_DIR)" >&2
	exit 1
	;;
esac

GROUP=
EXCLUDE_GROUP=
FILTER=
STEPS=
for arg in "$@"; do
	case $arg in
	--group=*)         GROUP=${arg#*=} ;;
	--exclude-group=*) EXCLUDE_GROUP=${arg#*=} ;;
	--filter=*)        FILTER=${arg#*=} ;;
	-*)
		echo "Unknown option: $arg" >&2
		exit 1
		;;
	*) STEPS="$STEPS $arg" ;;
	esac
done
STEPS=${STEPS:-unit setup integration}

set --
[ -n "$GROUP" ] && set -- "$@" --group "$GROUP"
[ -n "$EXCLUDE_GROUP" ] && set -- "$@" --exclude-group "$EXCLUDE_GROUP"
[ -n "$FILTER" ] && set -- "$@" --filter "$FILTER"

# the platform is kept in svn: git has no svn:externals, so it is exported once (pinned revision)
if [ ! -d "$PLATFORM_DIR" ]; then
	"$SVN" export -q -r "${KA_TEST_PLATFORM_REV:-14}" https://svn.ka-station.com/ka_oc3_testing/trunk "$PLATFORM_DIR"
fi
[ -d "$PLATFORM_DIR/vendor" ] || (cd "$PLATFORM_DIR" && composer install)

for step in $STEPS; do
	case $step in
	unit)
		(cd "$TESTS_DIR/unit" && "$PHP" "$PLATFORM_DIR/vendor/bin/phpunit" "$@")
		;;
	setup)
		[ -d "$TESTS_DIR/e2e/node_modules" ] || (cd "$TESTS_DIR/e2e" && npm ci && npx playwright install chromium)
		if [ -n "$KA_TEST_KA_EXTENSIONS_ZIP" ]; then
			PACKAGE=$KA_TEST_KA_EXTENSIONS_ZIP
		else
			# the platform builds the package of a commit with ka_build.php in a temporary git worktree.
			# It needs a clone with a .git folder: for a "git worktree" checkout its main clone is used
			COMMIT=$(git -C "$REPO_DIR" rev-parse HEAD)
			GIT_COMMON_DIR=$(cd "$REPO_DIR" && cd "$(git rev-parse --git-common-dir)" && pwd)
			PACKAGE=$(KA_TEST_KA_EXTENSIONS_GIT=$(dirname "$GIT_COMMON_DIR") KA_TEST_KA_EXTENSIONS_FETCH=0 \
				KA_TEST_KA_EXTENSIONS_REF=$COMMIT \
				"$PHP" "$PLATFORM_DIR/bin/download-ka-extensions.php")
			# the platform falls back to the published release when the build fails
			case "$PACKAGE" in
			*"$(echo "$COMMIT" | cut -c1-12)"*) ;;
			*)
				echo "The package of $COMMIT was not built (got: $PACKAGE)" >&2
				exit 1
				;;
			esac
		fi
		if [ -n "$KA_TEST_OC_ZIP" ]; then SOURCE="--zip=$KA_TEST_OC_ZIP"; else SOURCE="--version=${KA_TEST_OC_VERSION:-3.0.5.1}"; fi
		"$PHP" "$PLATFORM_DIR/bin/setup-store.php" --force "$SOURCE" \
			--dir="$KA_TEST_STORE_DIR" --url="$KA_TEST_STORE_URL" --db="$KA_TEST_DB_NAME" --snapshot="$KA_TEST_DB_SNAPSHOT" \
			--package="$PACKAGE" \
			--node-dir="$TESTS_DIR/e2e"
		;;
	integration)
		(cd "$TESTS_DIR/integration" && "$PHP" "$PLATFORM_DIR/vendor/bin/phpunit" "$@")
		;;
	*)
		echo "Unknown step: $step" >&2
		exit 1
		;;
	esac
done
