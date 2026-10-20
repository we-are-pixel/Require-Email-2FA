#!/usr/bin/env bash
#
# Build a throwaway WordPress (SQLite, no MySQL) with Two Factor and this plugin,
# installed in Require-Email-2FA-main/ exactly like on the live sites.
#
# Usage: tests/bin/setup-wp.sh single|multisite [plugin-source-dir]
# Env:   WP_VERSION (latest), TWO_FACTOR_VERSION (latest), WP_PHP (php), FORCE_2FA_TESTS_ROOT.

set -euo pipefail

MODE="${1:-single}"
SOURCE="${2:-}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
ROOT="${FORCE_2FA_TESTS_ROOT:-${TMPDIR:-/tmp}/require-email-2fa}"
SITE="$ROOT/$MODE"

# Pinned, checksum-verified tools that run as code here.
WP_CLI_VERSION="2.12.0"
WP_CLI_SHA512="be928f6b8ca1e8dfb9d2f4b75a13aa4aee0896f8a9a0a1c45cd5d2c98605e6172e6d014dda2e27f88c98befc16c040cbb2bd1bfa121510ea5cdf5f6a30fe8832"
SQLITE_VERSION="2.2.23"
SQLITE_SHA256="44be096a14ebcea424b5e4bf764436ec85fb067f74ab47822c4c5346df21591e"

checksum() {
	if command -v shasum >/dev/null; then shasum -a "$1" "$2" | cut -d' ' -f1; else "sha${1}sum" "$2" | cut -d' ' -f1; fi
}

fetch() {
	local url="$1" file="$2" bits="$3" hash="$4"
	[ -f "$file" ] || curl -fsSL "$url" -o "$file"
	[ "$(checksum "$bits" "$file")" = "$hash" ] || { echo "Checksum mismatch: $file" >&2; rm -f "$file"; exit 1; }
}

mkdir -p "$ROOT"
fetch "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" "$ROOT/wp-cli.phar" 512 "$WP_CLI_SHA512"
fetch "https://downloads.wordpress.org/plugin/sqlite-database-integration.${SQLITE_VERSION}.zip" "$ROOT/sqlite.zip" 256 "$SQLITE_SHA256"

wp() { bash "$REPO/tests/bin/wp.sh" "$MODE" --quiet "$@"; }

rm -rf "$SITE"
mkdir -p "$SITE"
wp core download --version="${WP_VERSION:-latest}" --force
unzip -q "$ROOT/sqlite.zip" -d "$SITE/wp-content/plugins/"
cp "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" "$SITE/wp-content/db.php"

# Tests put extra constants (scope, kill switch, environment) in test-config.php.
echo "if ( is_readable( __DIR__ . '/test-config.php' ) ) { require __DIR__ . '/test-config.php'; }" \
	| wp config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check --extra-php
wp config set WP_DEBUG true --raw
wp config set WP_DEBUG_LOG true --raw
wp config set WP_DEBUG_DISPLAY false --raw

if [ "$MODE" = "multisite" ]; then
	URL="http://localhost"
	wp core multisite-install --url="$URL" --title=Test --admin_user=admin --admin_password=password --admin_email=admin@example.test --skip-email
	wp site create --slug=site-a
	wp site create --slug=site-b
else
	URL="http://127.0.0.1:8888"
	wp core install --url="$URL" --title=Test --admin_user=admin --admin_password=password --admin_email=admin@example.test --skip-email
fi

# WP-CLI guesses the address from the folder layout and can append the folder name; pin it.
wp option update home "$URL"
wp option update siteurl "$URL"

NETWORK=""
[ "$MODE" = "multisite" ] && NETWORK="--network"

wp plugin install two-factor ${TWO_FACTOR_VERSION:+--version="$TWO_FACTOR_VERSION"} --force
wp plugin activate two-factor $NETWORK

mkdir -p "$SITE/wp-content/mu-plugins"
cp "$REPO/tests/fixtures/test-site.php" "$SITE/wp-content/mu-plugins/"

# The plugin under test: a release-like copy of the working tree, or another source (old releases).
TARGET="$SITE/wp-content/plugins/Require-Email-2FA-main"
if [ -n "$SOURCE" ]; then
	cp -R "$SOURCE" "$TARGET"
else
	bash "$REPO/bin/build.sh" "$ROOT/build"
	cp -R "$ROOT/build/Require-Email-2FA-main" "$TARGET"
fi
wp plugin activate Require-Email-2FA-main $NETWORK

echo "WordPress ($MODE) ready: $SITE"
