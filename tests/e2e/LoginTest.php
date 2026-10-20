<?php
/**
 * Logging in through wp-login.php.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests\E2E;

/**
 * Who gets asked for a code, and that the code works.
 */
final class LoginTest extends E2ETestCase {

	/**
	 * A plain user is stopped after the password and gets in with the emailed code.
	 */
	public function test_password_alone_is_not_enough_and_emailed_code_works(): void {
		$jar      = $this->jar();
		$response = $this->log_in( $jar, 'sub', 'pass-sub' );

		$this->assertStringContainsString( 'name="two-factor-email-code"', $response['body'] );
		$this->assertFalse( $this->logged_in( $jar ) );

		$this->answer( $jar, $response['body'], 'two-factor-email-code', $this->emailed_code() );

		$this->assertTrue( $this->logged_in( $jar ) );
	}

	/**
	 * A wrong code does not log in.
	 */
	public function test_wrong_code_is_rejected(): void {
		$jar      = $this->jar();
		$response = $this->log_in( $jar, 'sub', 'pass-sub' );

		$this->answer( $jar, $response['body'], 'two-factor-email-code', '00000000' );

		$this->assertFalse( $this->logged_in( $jar ) );
	}

	/**
	 * An authenticator-app user (1Password) is asked for the app code first.
	 */
	public function test_authenticator_app_is_asked_first(): void {
		$jar      = $this->jar();
		$response = $this->log_in( $jar, 'totp', 'pass-totp' );

		$this->assertSame( 'Two_Factor_Totp', $this->two_factor_form( $response['body'] )['provider'] );
		$code = self::wp( 'eval', 'echo Two_Factor_Totp::calc_totp( get_user_meta( get_user_by( "login", "totp" )->ID, Two_Factor_Totp::SECRET_META_KEY, true ) );' );
		$this->answer( $jar, $response['body'], 'authcode', $code );

		$this->assertTrue( $this->logged_in( $jar ) );
	}

	/**
	 * "Administrators only": a subscriber logs in with the password, the admin still needs a code.
	 */
	public function test_administrators_only_scope(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'manage_options' );

		$subscriber = $this->jar();
		$this->log_in( $subscriber, 'sub', 'pass-sub' );
		$this->assertTrue( $this->logged_in( $subscriber ) );

		$this->assertStringContainsString( 'two-factor-email-code', $this->log_in( $this->jar(), 'admin', 'password' )['body'] );
	}

	/**
	 * The wp-config constant beats the scope saved in the admin.
	 */
	public function test_wp_config_scope_beats_saved_scope(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'manage_options' );
		self::define_constant( 'FORCE_2FA_ENFORCED_CAPABILITY', "''" );

		$this->assertStringContainsString( 'two-factor-email-code', $this->log_in( $this->jar(), 'sub', 'pass-sub' )['body'] );
	}

	/**
	 * Excluded roles log in with the password.
	 */
	public function test_excluded_role_logs_in_with_password(): void {
		self::define_constant( 'FORCE_2FA_EXCLUDED_ROLES', "array( 'subscriber' )" );

		$jar = $this->jar();
		$this->log_in( $jar, 'sub', 'pass-sub' );

		$this->assertTrue( $this->logged_in( $jar ) );
	}

	/**
	 * The kill switch turns the email requirement off.
	 */
	public function test_kill_switch_turns_email_codes_off(): void {
		self::define_constant( 'FORCE_2FA_DISABLE', 'true' );

		$jar = $this->jar();
		$this->log_in( $jar, 'sub', 'pass-sub' );

		$this->assertTrue( $this->logged_in( $jar ) );
	}

	/**
	 * Local copies: no email codes, even saved ones, but an authenticator app is still asked for.
	 */
	public function test_local_copy_skips_email_codes_only(): void {
		self::define_constant( 'WP_ENVIRONMENT_TYPE', "'local'" );
		self::wp( 'user', 'meta', 'update', 'sub', '_two_factor_enabled_providers', '["Two_Factor_Email"]', '--format=json' );

		$jar = $this->jar();
		$this->log_in( $jar, 'sub', 'pass-sub' );
		$this->assertTrue( $this->logged_in( $jar ) );

		$totp = $this->log_in( $this->jar(), 'totp', 'pass-totp' )['body'];
		$this->assertSame( 'Two_Factor_Totp', $this->two_factor_form( $totp )['provider'] );
		$this->assertStringNotContainsString( 'Two_Factor_Email', $totp );
	}

	/**
	 * Local copies: backup codes alone are not asked for either.
	 */
	public function test_local_copy_with_backup_codes_only(): void {
		self::define_constant( 'WP_ENVIRONMENT_TYPE', "'local'" );
		self::wp( 'eval', '$user = get_user_by( "login", "sub" ); Two_Factor_Backup_Codes::get_instance()->generate_codes( $user ); update_user_meta( $user->ID, "_two_factor_enabled_providers", array( "Two_Factor_Email", "Two_Factor_Backup_Codes" ) );' );

		$jar = $this->jar();
		$this->log_in( $jar, 'sub', 'pass-sub' );

		$this->assertTrue( $this->logged_in( $jar ) );
	}

	/**
	 * Locally an authenticator app is still asked for, even when Backup Codes were saved as primary.
	 */
	public function test_local_copy_still_asks_for_the_app_over_backup_codes(): void {
		self::define_constant( 'WP_ENVIRONMENT_TYPE', "'local'" );
		self::wp( 'eval', '$user = get_user_by( "login", "totp" ); Two_Factor_Backup_Codes::get_instance()->generate_codes( $user ); update_user_meta( $user->ID, "_two_factor_enabled_providers", array( "Two_Factor_Email", "Two_Factor_Totp", "Two_Factor_Backup_Codes" ) ); update_user_meta( $user->ID, "_two_factor_provider", "Two_Factor_Backup_Codes" );' );

		$this->assertSame( 'Two_Factor_Totp', $this->two_factor_form( $this->log_in( $this->jar(), 'totp', 'pass-totp' )['body'] )['provider'] );
	}

	/**
	 * "local" only counts on a local address, so a copied setting can't switch 2FA off on a live site.
	 */
	public function test_local_setting_on_a_live_address_is_ignored(): void {
		self::define_constant( 'WP_ENVIRONMENT_TYPE', "'local'" );

		$this->assertSame( '1', self::wp( 'eval', 'echo (int) Force_2FA::instance()->policy->is_local();' ) );
		$this->assertSame( '0', self::wp( 'eval', 'add_filter( "home_url", function () { return "https://client-site.com"; } ); echo (int) Force_2FA::instance()->policy->is_local();' ) );
	}

	/**
	 * Development and staging copies keep two-factor (they are often public, with live passwords).
	 *
	 * @param string $environment WP_ENVIRONMENT_TYPE.
	 */
	#[\PHPUnit\Framework\Attributes\TestWith( array( 'development' ) )]
	#[\PHPUnit\Framework\Attributes\TestWith( array( 'staging' ) )]
	public function test_non_local_copies_still_require_codes( string $environment ): void {
		self::define_constant( 'WP_ENVIRONMENT_TYPE', "'$environment'" );

		$this->assertStringContainsString( 'two-factor-email-code', $this->log_in( $this->jar(), 'sub', 'pass-sub' )['body'] );
	}

	/**
	 * Without Two Factor nothing is enforced, and nothing breaks.
	 */
	public function test_login_still_works_without_two_factor(): void {
		self::wp( 'plugin', 'deactivate', 'two-factor' );

		$jar = $this->jar();
		$this->log_in( $jar, 'sub', 'pass-sub' );

		$this->assertTrue( $this->logged_in( $jar ) );
	}
}
