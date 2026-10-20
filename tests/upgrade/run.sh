#!/usr/bin/env bash
#
# Update real old releases, installed the way they are on the live sites
# (Require-Email-2FA-main/, single site and network-active), to this version:
# through the updater (MainWP "Update") and by overwrite upload (MainWP "Upload").
# Then prove this version can update itself, and only from a proper release.
#
# Usage: tests/upgrade/run.sh   (FROM_VERSIONS="v1.11.0" for WordPress 6.7: 1.12+ need 6.8)

set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
ROOT="${FORCE_2FA_TESTS_ROOT:-${TMPDIR:-/tmp}/require-email-2fa}"
HERE="$REPO/tests/upgrade"
BUILD="$ROOT/build"
NEXT="$ROOT/build-next"
PLUGIN="Require-Email-2FA-main"
VERSION="$(sed -n 's/^ \* Version: *//p' "$REPO/force-email-two-factor.php")"
FROM="${FROM_VERSIONS:-v1.11.0 v1.13.1 v1.13.2}"
NEWEST_FROM="${FROM##* }"

fail() {
	echo "FAIL ($1): $2" >&2
	exit 1
}

# expect <label> <actual> <expected substring>...
expect() {
	local label="$1" actual="$2"
	shift 2
	for wanted in "$@"; do
		[[ "$actual" == *"$wanted"* ]] || fail "$label" "expected '$wanted' in: $actual"
	done
}

# release <dir> <version>: a copy of this build that claims another version.
release() {
	rm -rf "$1" && mkdir -p "$1" && cp -R "$BUILD/$PLUGIN" "$1/"
	sed -i.bak "s/$VERSION/$2/g" "$1/$PLUGIN/force-email-two-factor.php" && rm "$1/$PLUGIN/force-email-two-factor.php.bak"
	(cd "$1" && zip -qr "$PLUGIN.zip" "$PLUGIN")
}

bash "$REPO/bin/build.sh" "$BUILD"
release "$NEXT" 9.9.9
release "$ROOT/build-no-zip" 9.9.10

for mode in single multisite; do
	wp() { bash "$REPO/tests/bin/wp.sh" "$mode" --quiet "$@"; }
	check_updates() { wp eval "add_filter( 'puc_check_now-$PLUGIN', '__return_true' ); do_action( 'puc_cron_check_updates-$PLUGIN' );"; }
	site="$ROOT/$mode"
	network=$([ "$mode" = multisite ] && echo 1 || echo 0)
	# What must hold after any update or rollback: same folder, nothing new next to it,
	# still active, saved scope kept, admins still asked for a code.
	kept=("active=1 " "network=$network " "scope='manage_options'" "admin=Two_Factor_Email " "folders=akismet,$PLUGIN,sqlite-database-integration,two-factor")

	for from in $FROM; do
		for path in updater upload; do
			label="$mode, $from, $path"
			old="$ROOT/release-$from"
			rm -rf "$old" && mkdir -p "$old" && git -C "$REPO" archive "$from" | tar -x -C "$old"

			bash "$REPO/tests/bin/setup-wp.sh" "$mode" "$old" >/dev/null
			cp "$HERE/github-mock.php" "$site/wp-content/mu-plugins/"
			wp user create upgradesub upgradesub@example.test --role=subscriber --user_pass=pass-upgrade
			wp eval 'update_site_option( "force_2fa_enforced_capability", "manage_options" );'
			wp eval-file "$HERE/release.php" "$VERSION" "$BUILD/$PLUGIN" "$BUILD/$PLUGIN.zip" 1
			: >"$site/wp-content/debug.log"

			if [ "$path" = updater ]; then
				check_updates
				[ "$(wp plugin list --update=available --field=name)" = "$PLUGIN" ] || fail "$label" "update to $VERSION not offered"
				wp plugin update "$PLUGIN"
			else
				wp plugin install "$BUILD/$PLUGIN.zip" --force
				wp eval "require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( '$PLUGIN/force-email-two-factor.php' );"
			fi

			expect "$label" "$(wp eval-file "$HERE/state.php")" "version=$VERSION " "${kept[@]}" "sub= "
			! grep -s "$PLUGIN" "$site/wp-content/debug.log" || fail "$label" "PHP errors from the plugin"
			echo "ok: $label -> $VERSION"
		done
	done

	label="$mode, $VERSION, next release"
	wp eval-file "$HERE/release.php" 9.9.9 "$NEXT/$PLUGIN" "$NEXT/$PLUGIN.zip" 1
	check_updates
	wp plugin update "$PLUGIN"
	expect "$label" "$(wp eval-file "$HERE/state.php")" "version=9.9.9 " "${kept[@]}" "sub= "
	echo "ok: $label -> 9.9.9"

	label="$mode, release without its zip"
	wp eval-file "$HERE/release.php" 9.9.10 "$ROOT/build-no-zip/$PLUGIN" "$ROOT/build-no-zip/$PLUGIN.zip" 0
	check_updates
	[ -z "$(wp plugin list --update=available --field=name)" ] || fail "$label" "an update without the release zip was offered"
	echo "ok: $label is ignored"

	label="$mode, roll back to $NEWEST_FROM by upload"
	rm -rf "$ROOT/rollback" && mkdir -p "$ROOT/rollback"
	git -C "$REPO" archive --format=zip --prefix="$PLUGIN/" -o "$ROOT/rollback/$PLUGIN.zip" "$NEWEST_FROM"
	wp plugin install "$ROOT/rollback/$PLUGIN.zip" --force
	expect "$label" "$(wp eval-file "$HERE/state.php")" "version=${NEWEST_FROM#v} " "${kept[@]}"
	echo "ok: $label"

	label="$mode, leftover 1.x copy next to $VERSION stays idle"
	wp plugin install "$BUILD/$PLUGIN.zip" --force
	rm -rf "$site/wp-content/plugins/force-email-two-factor" && mkdir -p "$site/wp-content/plugins/force-email-two-factor"
	git -C "$REPO" archive "$NEWEST_FROM" | tar -x -C "$site/wp-content/plugins/force-email-two-factor"
	wp plugin activate force-email-two-factor $([ "$mode" = multisite ] && echo --network)
	old_copy="$(wp eval 'echo has_filter( "two_factor_enabled_providers_for_user", "force_2fa_filter_enabled_providers" ) ? "running" : "idle";')"
	[ "$old_copy" = idle ] || fail "$label" "the 1.x copy is enforcing too"
	expect "$label" "$(wp eval-file "$HERE/state.php")" "version=$VERSION " "active=1 " "network=$network " "admin=Two_Factor_Email " "sub= "
	echo "ok: $label"
done
