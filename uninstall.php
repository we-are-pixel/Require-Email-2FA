<?php
/**
 * Delete the plugin's stored data.
 *
 * @package Force_2FA
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Plugin Update Checker names its option and cron event after the plugin folder.
$force_2fa_slug = basename( __DIR__ );

delete_site_option( 'force_2fa_enforced_capability' );
delete_site_option( 'external_updates-' . $force_2fa_slug );

if ( ! is_multisite() ) {
	wp_clear_scheduled_hook( 'puc_cron_check_updates-' . $force_2fa_slug );
	return;
}

$force_2fa_sites = get_sites(
	array(
		'fields'                 => 'ids',
		'number'                 => 0,
		'update_site_cache'      => false,
		'update_site_meta_cache' => false,
	)
);

foreach ( $force_2fa_sites as $force_2fa_site_id ) {
	switch_to_blog( $force_2fa_site_id );
	wp_clear_scheduled_hook( 'puc_cron_check_updates-' . $force_2fa_slug );
	restore_current_blog();
}
