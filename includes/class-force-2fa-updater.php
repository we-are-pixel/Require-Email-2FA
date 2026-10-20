<?php
/**
 * Updates from GitHub Releases.
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Offers new GitHub releases through the normal WordPress / MainWP update screens.
 */
final class Force_2FA_Updater {

	/**
	 * Release asset to install. Its folder matches the one used on the live sites.
	 */
	const ASSET = 'Require-Email-2FA-main.zip';

	/**
	 * Plugin Update Checker's name for "the latest published release".
	 */
	const RELEASE_STRATEGY = 'latest_release';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Start Plugin Update Checker unless updates are managed elsewhere.
	 */
	public function init(): void {
		$dir = dirname( FORCE_2FA_FILE );

		// A git checkout is updated with git, never replaced by a release zip.
		if ( file_exists( $dir . '/.git' ) ) {
			return;
		}

		$enabled = ! ( defined( 'FORCE_2FA_DISABLE_SELF_UPDATE' ) && FORCE_2FA_DISABLE_SELF_UPDATE );

		/**
		 * Filters whether the plugin checks GitHub for updates.
		 *
		 * @param bool $enabled Whether self-update is on.
		 */
		if ( ! apply_filters( 'force_2fa_self_update_enabled', $enabled ) ) {
			return;
		}

		$library = $dir . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

		// A half-finished upload must not take the site down.
		if ( ! is_readable( $library ) ) {
			return;
		}

		require_once $library;

		$headers = get_file_data( FORCE_2FA_FILE, array( 'uri' => 'Update URI' ) );
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( $headers['uri'], FORCE_2FA_FILE, basename( $dir ) );
		$api     = $checker->getVcsApi();

		// getVcsApi() is typed as the base Api; enableReleaseAssets() lives on the GitHub/GitLab APIs.
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/^' . preg_quote( self::ASSET, '/' ) . '$/', $api::REQUIRE_RELEASE_ASSETS );
		}

		add_filter( $checker->getUniqueName( 'vcs_update_detection_strategies' ), array( $this, 'releases_only' ) );
	}

	/**
	 * Only published releases count as updates; a bare tag or branch never does.
	 *
	 * @param callable[] $strategies PUC strategies keyed by name.
	 * @return callable[]
	 */
	public function releases_only( array $strategies ): array {
		return array_intersect_key( $strategies, array( self::RELEASE_STRATEGY => true ) );
	}
}
