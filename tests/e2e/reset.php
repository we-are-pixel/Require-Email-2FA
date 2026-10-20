<?php
/**
 * Reset the test site between end-to-end tests. Run with: wp eval-file.
 *
 * Two Factor may have been deactivated by the previous test, so its meta keys are spelled out.
 *
 * @package Force_2FA
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

activate_plugin( 'two-factor/two-factor.php' );
delete_option( 'force_2fa_enforced_capability' );

$force_2fa_sub = get_user_by( 'login', 'sub' );

if ( $force_2fa_sub ) {
	foreach ( array( '_two_factor_enabled_providers', '_two_factor_provider', '_two_factor_backup_codes' ) as $force_2fa_key ) {
		delete_user_meta( $force_2fa_sub->ID, $force_2fa_key );
	}
}

// An unused code left by an earlier test stops Two Factor from emailing a new one.
foreach ( get_users( array( 'fields' => 'ID' ) ) as $force_2fa_user_id ) {
	foreach ( array( '_two_factor_email_token', '_two_factor_email_token_timestamp', '_two_factor_last_login_failure', '_two_factor_failed_login_attempts' ) as $force_2fa_key ) {
		delete_user_meta( $force_2fa_user_id, $force_2fa_key );
	}
}
