<?php
/**
 * Who must use two-factor.
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides which users are required to use two-factor.
 */
final class Force_2FA_Policy {

	/**
	 * Option holding the scope chosen in the admin (network option on multisite).
	 */
	const OPTION = 'force_2fa_enforced_capability';

	/**
	 * Hosts that count as a local copy, besides *.local, *.test and *.localhost.
	 */
	const LOCAL_HOSTS = array( 'localhost', '127.0.0.1' );

	/**
	 * Exemptions computed in this request, by user ID and then site ID.
	 *
	 * @var bool[][]
	 */
	private $exempt = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'clean_user_cache', array( $this, 'forget' ) );
	}

	/**
	 * Scope choices: capability => label. '' means every user.
	 *
	 * @return string[]
	 */
	public static function scopes(): array {
		return array(
			''               => __( 'All users', 'force-email-two-factor' ),
			'edit_posts'     => __( 'Contributors and up', 'force-email-two-factor' ),
			'manage_options' => __( 'Administrators only', 'force-email-two-factor' ),
		);
	}

	/**
	 * Whether the user is not required to use two-factor.
	 *
	 * @param WP_User $user User to check.
	 * @return bool
	 */
	public function is_exempt( WP_User $user ): bool {
		$site = get_current_blog_id();

		// Per site, because the scope and role filters may differ after switch_to_blog().
		if ( ! isset( $this->exempt[ $user->ID ][ $site ] ) ) {
			$this->exempt[ $user->ID ][ $site ] = $this->compute_exempt( $user );
		}

		/**
		 * Filters whether a user is exempt from required two-factor.
		 *
		 * @param bool    $exempt Whether the user is exempt.
		 * @param WP_User $user   User being checked.
		 */
		return (bool) apply_filters( 'force_2fa_user_is_exempt', $this->exempt[ $user->ID ][ $site ], $user );
	}

	/**
	 * Drop a cached exemption when WordPress refreshes the user (role changes).
	 *
	 * @param int $user_id User ID.
	 */
	public function forget( $user_id ): void {
		unset( $this->exempt[ (int) $user_id ] );
	}

	/**
	 * The scope chosen in the admin, or null when none was chosen yet.
	 *
	 * @return string|null
	 */
	public function scope(): ?string {
		$scope = get_site_option( self::OPTION, null );

		return is_string( $scope ) && array_key_exists( $scope, self::scopes() ) ? $scope : null;
	}

	/**
	 * Store the scope chosen in the admin.
	 *
	 * @param string $scope One of the scopes() keys.
	 */
	public function save_scope( string $scope ): void {
		// Anything unexpected falls back to the strictest choice: all users.
		update_site_option( self::OPTION, array_key_exists( $scope, self::scopes() ) ? $scope : '' );
	}

	/**
	 * Whether this is a local copy, where only authenticator apps are asked for.
	 *
	 * @return bool
	 */
	public function is_local(): bool {
		if ( 'local' !== wp_get_environment_type() ) {
			return false;
		}

		// A "local" setting carried over to a live site must not switch 2FA off there.
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return in_array( $host, self::LOCAL_HOSTS, true ) || 1 === preg_match( '/\.(local|test|localhost)$/', $host );
	}

	/**
	 * Work out the exemption before filters.
	 *
	 * @param WP_User $user User to check.
	 * @return bool
	 */
	private function compute_exempt( WP_User $user ): bool {
		$capability = $this->capability();

		if ( '' !== $capability && ! $this->has_capability( $user, $capability ) ) {
			return true;
		}

		return $this->has_only_excluded_roles( $user ) || $this->wordfence_active( $user );
	}

	/**
	 * Capability a user needs to be required to use two-factor; '' means every user.
	 *
	 * @return string
	 */
	private function capability(): string {
		$capability = defined( 'FORCE_2FA_ENFORCED_CAPABILITY' ) ? FORCE_2FA_ENFORCED_CAPABILITY : (string) $this->scope();

		/**
		 * Filters the capability that puts a user in scope; '' means every user.
		 *
		 * @param string $capability Capability name.
		 */
		$capability = apply_filters( 'force_2fa_enforced_capability', $capability );

		return is_string( $capability ) ? trim( $capability ) : '';
	}

	/**
	 * Whether every role the user holds is excluded; one other role keeps them in scope.
	 *
	 * @param WP_User $user User to check.
	 * @return bool
	 */
	private function has_only_excluded_roles( WP_User $user ): bool {
		$excluded = $this->excluded_roles();

		if ( empty( $excluded ) ) {
			return false;
		}

		// A super admin's powers don't come from a role, so excluding their roles must not exempt them.
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return false;
		}

		$roles = $this->network_roles( $user );

		return ! empty( $roles ) && empty( array_diff( $roles, $excluded ) );
	}

	/**
	 * Whether the user holds the capability anywhere in the network.
	 *
	 * Logins are network-wide, so a per-site check would let an admin of one site
	 * skip two-factor by logging in through another.
	 *
	 * @param WP_User $user       User to check.
	 * @param string  $capability Capability name.
	 * @return bool
	 */
	private function has_capability( WP_User $user, string $capability ): bool {
		if ( ! is_multisite() ) {
			return user_can( $user, $capability );
		}

		if ( is_super_admin( $user->ID ) ) {
			return true;
		}

		foreach ( get_blogs_of_user( $user->ID ) as $site ) {
			if ( user_can_for_site( $user, (int) $site->userblog_id, $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every role the user holds across the network.
	 *
	 * @param WP_User $user User to check.
	 * @return string[]
	 */
	private function network_roles( WP_User $user ): array {
		$roles = $user->roles;

		if ( is_multisite() ) {
			foreach ( get_blogs_of_user( $user->ID ) as $site ) {
				$roles = array_merge( $roles, ( new WP_User( $user->ID, '', (int) $site->userblog_id ) )->roles );
			}
		}

		return Force_2FA::string_list( $roles );
	}

	/**
	 * Roles that are not required to use two-factor.
	 *
	 * @return string[]
	 */
	private function excluded_roles(): array {
		$roles = defined( 'FORCE_2FA_EXCLUDED_ROLES' ) ? FORCE_2FA_EXCLUDED_ROLES : array();

		/**
		 * Filters the role slugs that are not required to use two-factor.
		 *
		 * @param string[] $roles Role slugs.
		 */
		return Force_2FA::string_list( apply_filters( 'force_2fa_excluded_roles', $roles ) );
	}

	/**
	 * Whether Wordfence already handles two-factor for the user.
	 *
	 * @param WP_User $user User to check.
	 * @return bool
	 */
	private function wordfence_active( WP_User $user ): bool {
		$controller = 'WordfenceLS\Controller_Users';

		if ( ! is_callable( array( $controller, 'shared' ) ) ) {
			return false;
		}

		try {
			return (bool) call_user_func( array( $controller, 'shared' ) )->has_2fa_active( $user );
		} catch ( Throwable $e ) {
			// A Wordfence failure must keep the email floor, never remove it.
			return false;
		}
	}
}
