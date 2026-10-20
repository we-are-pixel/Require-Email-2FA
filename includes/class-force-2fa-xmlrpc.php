<?php
/**
 * XML-RPC login allowlist.
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lets only allowlisted accounts that use an Application Password log in over XML-RPC.
 */
final class Force_2FA_XMLRPC {

	/**
	 * User who authenticated with an Application Password in this request.
	 *
	 * @var int
	 */
	private $app_password_user_id = 0;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'application_password_did_authenticate', array( $this, 'remember_app_password_user' ) );
		add_filter( 'two_factor_user_api_login_enable', array( $this, 'api_login_enabled' ), 10, 2 );
		// Last, so no later authenticate callback can turn a refusal back into a login.
		add_filter( 'authenticate', array( $this, 'restrict_xmlrpc_login' ), PHP_INT_MAX );
	}

	/**
	 * Remember who used an Application Password, so the exception is tied to that account.
	 *
	 * @param WP_User $user User WordPress authenticated.
	 */
	public function remember_app_password_user( $user ): void {
		// Untyped on purpose: a wrong type from another plugin must not fatal the login.
		$this->app_password_user_id = $user instanceof WP_User ? $user->ID : 0;
	}

	/**
	 * Two Factor's API-login switch for users who use two-factor.
	 *
	 * @param bool $enabled Two Factor's default, replaced by our rule.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function api_login_enabled( $enabled, $user_id ): bool {
		$user = get_userdata( (int) $user_id );

		return $user instanceof WP_User && $this->may_use_api( $user );
	}

	/**
	 * Refuse every other XML-RPC login, whatever the two-factor scope and even while Two Factor is inactive.
	 *
	 * @param WP_User|WP_Error|null $user Result of earlier authentication.
	 * @return WP_User|WP_Error|null
	 */
	public function restrict_xmlrpc_login( $user ) {
		$is_xmlrpc_login = $user instanceof WP_User && defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;

		if ( ! $is_xmlrpc_login || $this->may_use_api( $user ) ) {
			return $user;
		}

		return new WP_Error( 'force_2fa_api_login_denied', __( 'XML-RPC logins are restricted to allowlisted service accounts authenticating with an Application Password.', 'force-email-two-factor' ) );
	}

	/**
	 * Whether the user is allowlisted and logged in with an Application Password just now.
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	private function may_use_api( WP_User $user ): bool {
		return $user->ID === $this->app_password_user_id && $this->is_allowlisted( $user );
	}

	/**
	 * Whether the user is on the allowlist. A number is a user ID, anything else a login.
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	private function is_allowlisted( WP_User $user ): bool {
		$allowlist = defined( 'FORCE_2FA_API_LOGIN_ALLOWLIST' ) ? FORCE_2FA_API_LOGIN_ALLOWLIST : array();

		/**
		 * Filters the accounts (IDs or logins) allowed to log in over XML-RPC.
		 *
		 * @param array $allowlist User IDs and/or logins.
		 */
		foreach ( Force_2FA::string_list( apply_filters( 'force_2fa_api_login_allowlist', $allowlist ) ) as $entry ) {
			if ( ctype_digit( $entry ) ? (int) $entry === $user->ID : strtolower( $user->user_login ) === $entry ) {
				return true;
			}
		}

		return false;
	}
}
