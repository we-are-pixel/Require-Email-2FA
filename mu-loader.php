<?php
/**
 * MU loader for Force Email Two-Factor (Enforcement).
 *
 * OPTIONAL. Copy THIS single file into wp-content/mu-plugins/ to force-load the
 * plugin on every request so it cannot be deactivated from the admin UI. The
 * plugin folder itself must still be present at:
 *
 *     wp-content/plugins/force-email-two-factor/force-email-two-factor.php
 *
 * Safe to use alongside normal (or network) activation: the plugin has a
 * FORCE_2FA_LOADED re-load guard, so loading it twice is a no-op.
 *
 * The emergency kill switch still applies — define( 'FORCE_2FA_DISABLE', true )
 * in wp-config.php disables enforcement even when force-loaded here.
 *
 * @package force-email-two-factor
 */

defined( 'ABSPATH' ) || exit;

$force_2fa_main = WP_PLUGIN_DIR . '/force-email-two-factor/force-email-two-factor.php';

if ( is_readable( $force_2fa_main ) ) {
	require_once $force_2fa_main;
}

unset( $force_2fa_main );
