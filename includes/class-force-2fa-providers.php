<?php
/**
 * Two Factor provider filters.
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Email provider to every in-scope user, keeps their own method primary and keeps Email switched on.
 */
final class Force_2FA_Providers {

	/**
	 * Two Factor's Email provider key.
	 */
	const EMAIL = 'Two_Factor_Email';

	/**
	 * Two Factor's option listing the providers allowed on the site (Settings → Two-Factor, 0.16+).
	 */
	const SITE_PROVIDERS_OPTION = 'two_factor_enabled_providers';

	/**
	 * Providers that are not a method the user set up themselves (Dummy always passes).
	 */
	const NOT_OWN_METHODS = array( self::EMAIL, 'Two_Factor_Backup_Codes', 'Two_Factor_Dummy' );

	/**
	 * Decides who must use two-factor.
	 *
	 * @var Force_2FA_Policy
	 */
	private $policy;

	/**
	 * Register hooks.
	 *
	 * @param Force_2FA_Policy $policy Policy.
	 */
	public function __construct( Force_2FA_Policy $policy ) {
		$this->policy = $policy;

		add_filter( 'two_factor_enabled_providers_for_user', array( $this, 'filter_enabled' ), 10, 2 );
		add_filter( 'two_factor_primary_provider_for_user', array( $this, 'choose_primary' ), 10, 2 );
		add_filter( 'option_' . self::SITE_PROVIDERS_OPTION, array( $this, 'keep_email_enabled' ) );
	}

	/**
	 * Append Email to the user's providers, or drop it on local copies; never remove anything else.
	 *
	 * @param string[] $providers Enabled provider keys.
	 * @param int      $user_id   User ID.
	 * @return string[]
	 */
	public function filter_enabled( $providers, $user_id ): array {
		$providers = is_array( $providers ) ? $providers : array();

		// Mail rarely works locally, and Two Factor stores Email once users save their settings.
		if ( $this->policy->is_local() ) {
			return array_values( array_diff( $providers, array( self::EMAIL ) ) );
		}

		$user = get_userdata( (int) $user_id );

		if ( ! $user || ! Force_2FA::two_factor_ready() || $this->policy->is_exempt( $user ) ) {
			return $providers;
		}

		if ( ! in_array( self::EMAIL, $providers, true ) ) {
			$providers[] = self::EMAIL;
		}

		return $providers;
	}

	/**
	 * Keep the user's own method primary; use Email only when they have none.
	 *
	 * @param string $provider Provider key Two Factor picked.
	 * @param int    $user_id  User ID.
	 * @return string
	 */
	public function choose_primary( $provider, $user_id ) {
		$user = get_userdata( (int) $user_id );

		if ( ! $user || $this->is_own_method( $provider ) ) {
			return $provider;
		}

		// Locally no own method means no prompt; this also stops Two Factor < 0.17 restoring Email.
		if ( $this->policy->is_local() ) {
			return $this->first_own_method( $user ) ?? '';
		}

		if ( ! Force_2FA::two_factor_ready() || $this->policy->is_exempt( $user ) ) {
			return $provider;
		}

		return $this->first_own_method( $user ) ?? self::EMAIL;
	}

	/**
	 * Keep Email in the site's allowed providers, so unchecking it in Settings → Two-Factor can't switch codes off.
	 *
	 * @param mixed $providers Saved provider keys.
	 * @return mixed
	 */
	public function keep_email_enabled( $providers ) {
		if ( is_array( $providers ) && ! in_array( self::EMAIL, $providers, true ) ) {
			$providers[] = self::EMAIL;
		}

		return $providers;
	}

	/**
	 * The first available method the user set up themselves.
	 *
	 * @param WP_User $user User.
	 * @return string|null
	 */
	private function first_own_method( WP_User $user ): ?string {
		$available = Two_Factor_Core::get_available_providers_for_user( $user );

		if ( ! is_array( $available ) ) {
			return null;
		}

		foreach ( array_keys( $available ) as $key ) {
			if ( $this->is_own_method( $key ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Whether the provider key is a method the user set up (TOTP, passkey, ...).
	 *
	 * @param mixed $provider Provider key.
	 * @return bool
	 */
	private function is_own_method( $provider ): bool {
		return is_string( $provider ) && ! in_array( $provider, self::NOT_OWN_METHODS, true );
	}
}
