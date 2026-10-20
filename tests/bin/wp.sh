#!/usr/bin/env bash
#
# Run the pinned WP-CLI against a test site.
# Usage: tests/bin/wp.sh single|multisite <wp-cli arguments>

set -euo pipefail

ROOT="${FORCE_2FA_TESTS_ROOT:-${TMPDIR:-/tmp}/require-email-2fa}"
MODE="$1"
shift

exec "${WP_PHP:-php}" -d memory_limit=512M "$ROOT/wp-cli.phar" --path="$ROOT/$MODE" "$@"
