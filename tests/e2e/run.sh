#!/usr/bin/env bash
#
# Real HTTP logins against a throwaway WordPress served by PHP's built-in server.
# Usage: tests/e2e/run.sh [phpunit arguments]

set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
ROOT="${FORCE_2FA_TESTS_ROOT:-${TMPDIR:-/tmp}/require-email-2fa}"
SITE="$ROOT/single"

bash "$REPO/tests/bin/setup-wp.sh" single

# OPcache would serve stale copies of test-config.php between tests.
"${WP_PHP:-php}" -d opcache.enable=0 -S 127.0.0.1:8888 -t "$SITE" >"$ROOT/server.log" 2>&1 &
SERVER=$!
# Leave the site clean for the next run (PHPUnit uses the same site).
trap 'kill $SERVER 2>/dev/null || true; printf "<?php\n" >"$SITE/test-config.php"; bash "$REPO/tests/bin/wp.sh" single --quiet eval-file "$REPO/tests/e2e/reset.php"' EXIT
curl -fs -o /dev/null --retry 30 --retry-connrefused --retry-delay 1 "http://127.0.0.1:8888/wp-login.php"

# On failure, show what the site itself logged.
"$REPO/vendor/bin/phpunit" -c "$REPO/tests/e2e/phpunit.xml" "$@" || {
	tail -n 40 "$ROOT/server.log" "$SITE/wp-content/debug.log" 2>/dev/null
	exit 1
}

# Any PHP notice, warning or error raised by the plugin fails the run.
if grep -s "Require-Email-2FA-main" "$SITE/wp-content/debug.log"; then
	echo "PHP errors from the plugin (see above)." >&2
	exit 1
fi
