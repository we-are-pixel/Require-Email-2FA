<?php
/**
 * Logging in over XML-RPC.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests\E2E;

/**
 * Only allowlisted accounts with an Application Password get in.
 */
final class XmlrpcTest extends E2ETestCase {

	/**
	 * Allowlist the "svc" account.
	 */
	protected function setUp(): void {
		parent::setUp();
		self::define_constant( 'FORCE_2FA_API_LOGIN_ALLOWLIST', "array( 'svc' )" );
	}

	/**
	 * Allowlisted account with its Application Password works.
	 */
	public function test_allowlisted_application_password_works(): void {
		$this->assertStringNotContainsString( '<name>faultCode</name>', $this->xmlrpc( 'svc', self::$app_passwords['svc'] ) );
	}

	/**
	 * The real password is refused even for the allowlisted account.
	 */
	public function test_real_password_is_refused(): void {
		$this->assertStringContainsString( '<name>faultCode</name>', $this->xmlrpc( 'svc', 'pass-svc' ) );
	}

	/**
	 * An Application Password of a non-allowlisted account is refused.
	 */
	public function test_other_accounts_are_refused(): void {
		$this->assertStringContainsString( '<name>faultCode</name>', $this->xmlrpc( 'other', self::$app_passwords['other'] ) );
	}

	/**
	 * Narrowing the 2FA scope does not open XML-RPC to the users it leaves out.
	 */
	public function test_out_of_scope_users_are_still_refused(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'manage_options' );

		$this->assertStringContainsString( '<name>faultCode</name>', $this->xmlrpc( 'sub', 'pass-sub' ) );
	}

	/**
	 * XML-RPC stays closed while Two Factor is inactive; the allowlisted account still works.
	 */
	public function test_still_closed_without_two_factor(): void {
		self::wp( 'plugin', 'deactivate', 'two-factor' );

		$this->assertStringContainsString( '<name>faultCode</name>', $this->xmlrpc( 'sub', 'pass-sub' ) );
		$this->assertStringNotContainsString( '<name>faultCode</name>', $this->xmlrpc( 'svc', self::$app_passwords['svc'] ) );
	}
}
