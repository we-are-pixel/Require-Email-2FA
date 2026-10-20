<?php
/**
 * API logins inside Two Factor. The XML-RPC gate itself is tested over HTTP (tests/e2e).
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

use Two_Factor_Core;

/**
 * Two Factor's API-login switch.
 */
final class XmlrpcTest extends TestCase {

	/**
	 * Only an allowlisted account that just used its own Application Password passes.
	 */
	public function test_api_login_needs_allowlist_and_own_application_password(): void {
		$service = $this->user( 'editor', 'svc_backup' );
		$other   = $this->user( 'editor' );
		$this->filter_value( 'force_2fa_api_login_allowlist', array( 'SVC_backup', 'not-a-user' ) );

		// Reset the per-request record left by earlier tests.
		do_action( 'application_password_did_authenticate', new \WP_User( 0 ) );
		$this->assertFalse( Two_Factor_Core::is_user_api_login_enabled( $service->ID ) );

		do_action( 'application_password_did_authenticate', $service );
		$this->assertTrue( Two_Factor_Core::is_user_api_login_enabled( $service->ID ) );
		$this->assertFalse( Two_Factor_Core::is_user_api_login_enabled( $other->ID ) );
	}

	/**
	 * The allowlist accepts user IDs as well as logins.
	 */
	public function test_allowlist_accepts_user_ids(): void {
		$service = $this->user( 'editor' );
		$this->filter_value( 'force_2fa_api_login_allowlist', array( (string) $service->ID ) );

		do_action( 'application_password_did_authenticate', $service );

		$this->assertTrue( Two_Factor_Core::is_user_api_login_enabled( $service->ID ) );
	}
}
