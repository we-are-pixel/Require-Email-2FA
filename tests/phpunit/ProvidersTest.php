<?php
/**
 * What Two Factor asks each user for at login.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

use Two_Factor_Core;
use Two_Factor_Totp;
use WP_User;

/**
 * The email floor and the primary method.
 */
final class ProvidersTest extends TestCase {

	/**
	 * A user who set nothing up still gets an emailed code.
	 */
	public function test_user_without_a_method_gets_email_codes(): void {
		$user = $this->user();

		$this->assertSame( array( 'Two_Factor_Email' ), Two_Factor_Core::get_enabled_providers_for_user( $user ) );
		$this->assertSame( 'Two_Factor_Email', $this->primary( $user ) );
		$this->assertTrue( Two_Factor_Core::is_user_using_two_factor( $user->ID ) );
	}

	/**
	 * An authenticator app (1Password) stays first; email is kept as a fallback.
	 */
	public function test_authenticator_app_stays_primary_with_email_as_fallback(): void {
		$user = $this->user();
		$this->enable_totp( $user );

		$this->assertEqualsCanonicalizing( array( 'Two_Factor_Totp', 'Two_Factor_Email' ), Two_Factor_Core::get_enabled_providers_for_user( $user ) );
		$this->assertSame( 'Two_Factor_Totp', $this->primary( $user ) );
	}

	/**
	 * The user's own method wins even when Email was saved as primary.
	 */
	public function test_own_method_wins_over_a_saved_email_primary(): void {
		$user = $this->user();
		$this->enable_totp( $user );
		update_user_meta( $user->ID, Two_Factor_Core::PROVIDER_USER_META_KEY, 'Two_Factor_Email' );

		$this->assertSame( 'Two_Factor_Totp', $this->primary( $user ) );
	}

	/**
	 * Backup codes are for recovery, so email stays the everyday method.
	 */
	public function test_backup_codes_alone_keep_email_primary(): void {
		$user = $this->user();
		\Two_Factor_Backup_Codes::get_instance()->generate_codes( $user );
		Two_Factor_Core::enable_provider_for_user( $user->ID, 'Two_Factor_Backup_Codes' );

		$this->assertEqualsCanonicalizing( array( 'Two_Factor_Backup_Codes', 'Two_Factor_Email' ), Two_Factor_Core::get_enabled_providers_for_user( $user ) );
		$this->assertSame( 'Two_Factor_Email', $this->primary( $user ) );
	}

	/**
	 * Out-of-scope users are left exactly as they configured themselves.
	 */
	public function test_out_of_scope_user_is_untouched(): void {
		$this->filter_value( 'force_2fa_enforced_capability', 'manage_options' );
		$plain = $this->user();
		$own   = $this->user();
		$this->enable_totp( $own );

		$this->assertSame( array(), Two_Factor_Core::get_enabled_providers_for_user( $plain ) );
		$this->assertFalse( Two_Factor_Core::is_user_using_two_factor( $plain->ID ) );
		$this->assertSame( array( 'Two_Factor_Totp' ), Two_Factor_Core::get_enabled_providers_for_user( $own ) );
	}

	/**
	 * Without Two Factor's Email provider nothing is added (and the admin sees a warning).
	 */
	public function test_nothing_is_added_when_email_provider_is_missing(): void {
		$this->filter(
			'two_factor_providers',
			function ( $providers ) {
				unset( $providers['Two_Factor_Email'] );
				return $providers;
			}
		);

		$this->assertFalse( \Force_2FA::two_factor_ready() );
		$this->assertSame( array(), Two_Factor_Core::get_enabled_providers_for_user( $this->user() ) );
	}

	/**
	 * Unchecking Email in Settings → Two-Factor does not switch the codes off.
	 */
	public function test_email_stays_on_when_unchecked_in_two_factor_settings(): void {
		update_option( \Force_2FA_Providers::SITE_PROVIDERS_OPTION, array( 'Two_Factor_Totp' ) );

		$this->assertTrue( \Force_2FA::two_factor_ready() );
		$this->assertSame( array( 'Two_Factor_Email' ), Two_Factor_Core::get_enabled_providers_for_user( $this->user() ) );
	}

	/**
	 * The provider Two Factor shows first at login.
	 *
	 * @param WP_User $user User.
	 */
	private function primary( WP_User $user ): string {
		$provider = Two_Factor_Core::get_primary_provider_for_user( $user );

		return $provider ? get_class( $provider ) : '';
	}

	/**
	 * Give the user a working authenticator app (TOTP).
	 *
	 * @param WP_User $user User.
	 */
	private function enable_totp( WP_User $user ): void {
		update_user_meta( $user->ID, Two_Factor_Totp::SECRET_META_KEY, Two_Factor_Totp::generate_key() );
		Two_Factor_Core::enable_provider_for_user( $user->ID, 'Two_Factor_Totp' );
	}
}
