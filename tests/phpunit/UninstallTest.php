<?php
/**
 * Deleting the plugin.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

/**
 * Uninstall cleanup.
 */
final class UninstallTest extends TestCase {

	/**
	 * Uninstall removes the scope choice and the updater's cache and cron event.
	 */
	public function test_uninstall_removes_stored_data(): void {
		$slug = basename( dirname( FORCE_2FA_FILE ) );
		$this->policy()->save_scope( 'manage_options' );
		update_site_option( 'external_updates-' . $slug, 'cached' );
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'puc_cron_check_updates-' . $slug );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', plugin_basename( FORCE_2FA_FILE ) );
		}

		include dirname( FORCE_2FA_FILE ) . '/uninstall.php';

		$this->assertNull( $this->policy()->scope() );
		$this->assertFalse( get_site_option( 'external_updates-' . $slug ) );
		$this->assertFalse( wp_next_scheduled( 'puc_cron_check_updates-' . $slug ) );
	}
}
