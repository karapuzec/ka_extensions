#!/bin/sh
# Safe mode test for a store with Ka Extensions installed (admin login admin/test by default).
# Run the store with opcache like on a production server, e.g.:
#   php -d opcache.enable_cli=1 -d opcache.revalidate_freq=60 -S localhost:8103
#   tests/safe_mode.sh http://localhost:8103 [admin user] [admin password]
# Checks: a new safe mode code is shown on the settings page and activates safe mode at once,
# the previous code stops working at once, an empty code disables safe mode.
url=${1%/}; user=${2:-admin}; pass=${3:-test}
[ -n "$url" ] || { echo "usage: $0 <store url>"; exit 1; }
jar=$(mktemp); sjar=$(mktemp)
fail() { rm -f "$jar" "$sjar"; echo "FAIL: $*"; exit 1; }

login() { # $1 - cookie jar, prints user_token
	curl -s -c "$1" -b "$1" -o /dev/null -w '%{redirect_url}' -d "username=$user&password=$pass" \
		"$url/admin/index.php?route=common/login" | sed -n 's/.*user_token=\([A-Za-z0-9]*\).*/\1/p'
}
settings_url() { echo "$url/admin/index.php?route=extension/extension/ka_extensions/settings&user_token=$token"; }
save_code() { curl -s -b "$jar" -o /dev/null --data-urlencode "safe_mode_code=$1" "$(settings_url)"; }
shown_code() {
	curl -s -b "$jar" "$(settings_url)" | sed -n '/name="safe_mode_code"/,/id="input-safe_mode_code"/p' \
		| sed -n 's/.*value="\([^"]*\)".*/\1/p' | head -n 1
}
is_safe_mode() { # $1 - code
	rm -f "$sjar"
	curl -s -c "$sjar" -b "$sjar" -o /dev/null "$url/admin/index.php?route=ka_safe_mode&code=$1"
	stoken=$(login "$sjar")
	curl -s -b "$sjar" "$url/admin/index.php?route=extension/extension/ka_extensions&user_token=$stoken" \
		| grep -q "operating in kamod safe mode"
}

token=$(login "$jar")
[ -n "$token" ] || fail "admin login"

save_code test1a
[ "$(shown_code)" = test1a ] || fail "settings page shows '$(shown_code)' instead of test1a"
is_safe_mode test1a || fail "code test1a does not activate safe mode"

save_code test2b
[ "$(shown_code)" = test2b ] || fail "settings page shows '$(shown_code)' instead of test2b"
is_safe_mode test2b || fail "new code test2b does not activate safe mode"
is_safe_mode test1a && fail "old code test1a still activates safe mode"

save_code ""
[ -z "$(shown_code)" ] || fail "settings page shows '$(shown_code)' after clearing the code"
is_safe_mode test2b && fail "safe mode works after clearing the code"

rm -f "$jar" "$sjar"
echo "OK: safe mode"
