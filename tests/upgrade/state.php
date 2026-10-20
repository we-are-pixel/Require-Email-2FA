<?php
/**
 * Print what must survive an update. Run with: wp eval-file state.php.
 *
 * @package Force_2FA
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$force_2fa_plugin  = 'Require-Email-2FA-main/force-email-two-factor.php';
$force_2fa_folders = array_map( 'basename', glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR ) );
sort( $force_2fa_folders, SORT_STRING | SORT_FLAG_CASE );
$force_2fa_admin   = get_user_by( 'login', 'admin' );
$force_2fa_sub     = get_user_by( 'login', 'upgradesub' );

printf(
	"version=%s active=%d network=%d scope=%s admin=%s sub=%s folders=%s\n",
	get_plugin_data( WP_PLUGIN_DIR . '/' . $force_2fa_plugin )['Version'],
	is_plugin_active( $force_2fa_plugin ),
	is_multisite() && is_plugin_active_for_network( $force_2fa_plugin ),
	var_export( is_multisite() ? get_site_option( 'force_2fa_enforced_capability' ) : get_option( 'force_2fa_enforced_capability' ), true ),
	implode( ',', Two_Factor_Core::get_enabled_providers_for_user( $force_2fa_admin ) ),
	implode( ',', Two_Factor_Core::get_enabled_providers_for_user( $force_2fa_sub ) ),
	implode( ',', $force_2fa_folders )
);
