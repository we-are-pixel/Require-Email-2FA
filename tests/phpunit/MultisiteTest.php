<?php
/**
 * Multisite: logins are network-wide, so the scope is too.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

/**
 * Network-wide scope and network activation.
 */
final class MultisiteTest extends TestCase {

	/**
	 * Only runs on the multisite test site.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only (WP_TESTS_MULTISITE=1).' );
		}
	}

	/**
	 * An admin of another site cannot skip 2FA by logging in where they are a subscriber.
	 */
	public function test_admin_of_another_site_stays_in_scope(): void {
		$this->policy()->save_scope( 'manage_options' );
		$user = $this->user( 'subscriber' );
		add_user_to_blog( $this->site( 'site-a' ), $user->ID, 'administrator' );

		$this->assertFalse( $this->policy()->is_exempt( $user ) );
	}

	/**
	 * An excluded role here does not exempt someone who is an editor elsewhere.
	 */
	public function test_excluded_role_does_not_cover_other_sites(): void {
		$this->filter_value( 'force_2fa_excluded_roles', array( 'subscriber' ) );
		$user = $this->user( 'subscriber' );
		add_user_to_blog( $this->site( 'site-b' ), $user->ID, 'editor' );

		$this->assertFalse( $this->policy()->is_exempt( $user ) );
	}

	/**
	 * Super admins are always in scope.
	 */
	public function test_super_admin_is_always_in_scope(): void {
		$this->policy()->save_scope( 'manage_options' );
		$user = $this->user( '' );
		grant_super_admin( $user->ID );

		$this->assertFalse( $this->policy()->is_exempt( $user ) );
	}

	/**
	 * Excluding the roles a super admin holds does not exempt them.
	 */
	public function test_super_admin_is_not_exempted_by_roles(): void {
		$this->filter_value( 'force_2fa_excluded_roles', array( 'subscriber' ) );
		$user = $this->user( 'subscriber' );
		grant_super_admin( $user->ID );

		$this->assertFalse( $this->policy()->is_exempt( $user ) );
	}

	/**
	 * The scope is stored once for the whole network.
	 */
	public function test_scope_is_a_network_setting(): void {
		$this->policy()->save_scope( 'edit_posts' );

		$this->assertSame( 'edit_posts', get_site_option( \Force_2FA_Policy::OPTION ) );
		$this->assertFalse( get_option( \Force_2FA_Policy::OPTION ) );
	}

	/**
	 * MainWP's "Activate" after an upload must not network-deactivate the plugin.
	 */
	public function test_site_activation_keeps_network_activation(): void {
		$plugin = plugin_basename( FORCE_2FA_FILE );
		$this->assertTrue( is_plugin_active_for_network( $plugin ) );

		$this->assertSame( '', $this->activate_on_this_site( $plugin ) );
		$this->assertTrue( is_plugin_active_for_network( $plugin ) );
	}

	/**
	 * A per-site activation of a plugin that is not network-active is refused.
	 */
	public function test_site_only_activation_is_refused(): void {
		$plugin = plugin_basename( FORCE_2FA_FILE );
		deactivate_plugins( $plugin, true, true );

		$this->assertStringContainsString( 'network-activated', $this->activate_on_this_site( $plugin ) );
		$this->assertNotContains( $plugin, (array) get_option( 'active_plugins', array() ) );
	}

	/**
	 * Activate on the current site only, the way MainWP does.
	 *
	 * @param string $plugin Plugin basename.
	 * @return string The wp_die() message, or '' when activation went through.
	 */
	private function activate_on_this_site( string $plugin ): string {
		$buffers = ob_get_level();
		$this->filter_value(
			'wp_die_handler',
			function ( $message ) {
				throw new \RuntimeException( wp_strip_all_tags( (string) $message ) );
			}
		);

		try {
			activate_plugin( $plugin );
			return '';
		} catch ( \RuntimeException $e ) {
			return $e->getMessage();
		} finally {
			while ( ob_get_level() > $buffers ) {
				ob_end_clean();
			}
		}
	}

	/**
	 * ID of a site created by setup-wp.sh.
	 *
	 * @param string $slug Site path without slashes.
	 */
	private function site( string $slug ): int {
		return (int) get_site_by_path( 'localhost', "/$slug/" )->blog_id;
	}
}
