<?php
/**
 * Plugin Name:       Require Email 2FA
 * Plugin URI:        https://github.com/we-are-pixel/Require-Email-2FA
 * Update URI:        https://github.com/we-are-pixel/Require-Email-2FA
 * Description:       Requires two-factor login for every user: an emailed code by default, an authenticator app (e.g. 1Password) once set up. Needs the Two Factor plugin.
 * Version:           2.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.2
 * Author:            Pixel
 * Author URI:        https://wearepixel.ca
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       force-email-two-factor
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

// Old versions (1.x) set the same constant: if another copy of this plugin already loaded, stay idle.
if ( defined( 'FORCE_2FA_LOADED' ) ) {
	return;
}

define( 'FORCE_2FA_VERSION', '2.0.0' );
define( 'FORCE_2FA_LOADED', FORCE_2FA_VERSION );
define( 'FORCE_2FA_FILE', __FILE__ );

// MainWP uploads skip the "Requires" checks: on older WordPress (and PHP) do nothing rather than crash.
if ( version_compare( $GLOBALS['wp_version'], '6.7', '<' ) ) {
	return;
}

require_once __DIR__ . '/includes/class-force-2fa.php';
require_once __DIR__ . '/includes/class-force-2fa-policy.php';
require_once __DIR__ . '/includes/class-force-2fa-providers.php';
require_once __DIR__ . '/includes/class-force-2fa-xmlrpc.php';
require_once __DIR__ . '/includes/class-force-2fa-admin.php';
require_once __DIR__ . '/includes/class-force-2fa-updater.php';

register_activation_hook( __FILE__, array( 'Force_2FA_Admin', 'require_network_activation' ) );

Force_2FA::instance();
