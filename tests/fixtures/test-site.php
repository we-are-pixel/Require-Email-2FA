<?php
/**
 * Test fixture (mu-plugin): capture mail and allow Application Passwords over plain HTTP.
 *
 * @package Force_2FA
 */

add_filter(
	'pre_wp_mail',
	function ( $result, $atts ) {
		update_option( 'force_2fa_tests_last_mail', $atts['message'] );
		return true;
	},
	10,
	2
);

add_filter( 'wp_is_application_passwords_available', '__return_true' );
