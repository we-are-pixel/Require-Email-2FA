<?php
/**
 * Admin notices and the scope setting.
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows the plugin status and lets admins choose who must use two-factor.
 */
final class Force_2FA_Admin {

	/**
	 * Action and nonce name for saving the scope (admin-post.php).
	 */
	const SCOPE_ACTION = 'force_2fa_set_scope';

	/**
	 * Form field and query arg holding the scope.
	 */
	const SCOPE_FIELD = 'force_2fa_scope';

	/**
	 * Choice pre-selected in the scope form: everyone who can edit content, but not customers.
	 */
	const SUGGESTED_SCOPE = 'edit_posts';

	/**
	 * Two Factor's main plugin file.
	 */
	const TWO_FACTOR_FILE = 'two-factor/two-factor.php';

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
		$basename     = plugin_basename( FORCE_2FA_FILE );

		add_action( 'all_admin_notices', array( $this, 'status_notice' ) );
		add_action( 'all_admin_notices', array( $this, 'scope_notice' ) );
		add_action( 'admin_post_' . self::SCOPE_ACTION, array( $this, 'save_scope' ) );
		add_filter( 'plugin_action_links_' . $basename, array( $this, 'action_links' ) );
		add_filter( 'network_admin_plugin_action_links_' . $basename, array( $this, 'action_links' ) );
	}

	/**
	 * Refuse per-site activation on multisite: logins are network-wide, so 2FA must be too.
	 *
	 * @param bool $network_wide Whether this is a network activation.
	 */
	public static function require_network_activation( $network_wide ): void {
		// Already network-active (e.g. MainWP "Activate" after an upload): nothing to refuse.
		if ( ! is_multisite() || $network_wide || is_plugin_active_for_network( plugin_basename( FORCE_2FA_FILE ) ) ) {
			return;
		}

		wp_die(
			esc_html__( 'Require Email 2FA must be network-activated on multisite: Network Admin → Plugins.', 'force-email-two-factor' ),
			esc_html__( 'Network activation required', 'force-email-two-factor' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Warn when two-factor is off, not enforced, or limited on a local copy.
	 */
	public function status_notice(): void {
		$message = current_user_can( $this->manage_capability() ) ? $this->status_message() : '';

		if ( '' !== $message ) {
			wp_admin_notice( esc_html( $message ), array( 'type' => 'warning' ) );
		}
	}

	/**
	 * Ask who must use two-factor until a choice is saved, or when "2FA scope" is clicked.
	 */
	public function scope_notice(): void {
		if ( ! $this->can_choose_scope() ) {
			return;
		}

		$scope = $this->policy->scope();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to show the form.
		if ( null !== $scope && ! isset( $_GET[ self::SCOPE_FIELD ] ) ) {
			return;
		}

		$selected = $scope ?? self::SUGGESTED_SCOPE;

		include __DIR__ . '/views/html-notice-scope.php';
	}

	/**
	 * Save the scope form.
	 */
	public function save_scope(): void {
		if ( ! current_user_can( $this->manage_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to change who must use two-factor.', 'force-email-two-factor' ), 403 );
		}

		check_admin_referer( self::SCOPE_ACTION );

		$this->policy->save_scope( isset( $_POST[ self::SCOPE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::SCOPE_FIELD ] ) ) : '' );

		$referer = wp_get_referer();

		wp_safe_redirect( remove_query_arg( self::SCOPE_FIELD, $referer ? $referer : admin_url() ) );
		exit;
	}

	/**
	 * Add a "2FA scope" link to the plugin row.
	 *
	 * @param string[] $links Action links.
	 * @return string[]
	 */
	public function action_links( $links ): array {
		if ( $this->can_choose_scope() ) {
			$links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( self::SCOPE_FIELD, 'edit' ) ),
				esc_html__( '2FA scope', 'force-email-two-factor' )
			);
		}

		return $links;
	}

	/**
	 * Whether the current user may choose the scope here (a wp-config.php scope can't be changed).
	 *
	 * @return bool
	 */
	private function can_choose_scope(): bool {
		return ! defined( 'FORCE_2FA_ENFORCED_CAPABILITY' ) && current_user_can( $this->manage_capability() );
	}

	/**
	 * The one problem worth telling admins about, or ''.
	 *
	 * @return string
	 */
	private function status_message(): string {
		if ( Force_2FA::is_disabled() ) {
			return __( 'Require Email 2FA is turned off by FORCE_2FA_DISABLE in wp-config.php. Two-factor login is not required.', 'force-email-two-factor' );
		}

		// On multisite Two Factor must run on every site, not just this one.
		if ( ! Force_2FA::two_factor_ready() || ( is_multisite() && ! is_plugin_active_for_network( self::TWO_FACTOR_FILE ) ) ) {
			return __( 'Two-factor login is NOT enforced: Require Email 2FA needs the Two Factor plugin active (network-active on multisite) with its Email method enabled.', 'force-email-two-factor' );
		}

		if ( $this->policy->is_local() ) {
			return __( 'Two-factor codes are off on this local copy (WP_ENVIRONMENT_TYPE is "local"). Users who set up an authenticator app still need it.', 'force-email-two-factor' );
		}

		return '';
	}

	/**
	 * Capability to manage this plugin's settings.
	 *
	 * @return string
	 */
	private function manage_capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}
}
