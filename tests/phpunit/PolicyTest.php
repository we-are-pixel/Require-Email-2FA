<?php
/**
 * Who must use two-factor.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

/**
 * Scope, excluded roles and integrations.
 */
final class PolicyTest extends TestCase {

	/**
	 * Before an admin chooses, everyone is required.
	 */
	public function test_everyone_is_in_scope_until_a_scope_is_saved(): void {
		$this->assertNull( $this->policy()->scope() );
		$this->assertFalse( $this->policy()->is_exempt( $this->user() ) );
	}

	/**
	 * "Administrators only" leaves editors out.
	 */
	public function test_administrators_only_scope(): void {
		$this->policy()->save_scope( 'manage_options' );

		$this->assertTrue( $this->policy()->is_exempt( $this->user( 'editor' ) ) );
		$this->assertFalse( $this->policy()->is_exempt( $this->user( 'administrator' ) ) );
	}

	/**
	 * "Contributors and up" leaves subscribers (and WooCommerce customers) out.
	 */
	public function test_contributors_and_up_scope(): void {
		$this->policy()->save_scope( 'edit_posts' );

		$this->assertTrue( $this->policy()->is_exempt( $this->user( 'subscriber' ) ) );
		$this->assertFalse( $this->policy()->is_exempt( $this->user( 'contributor' ) ) );
	}

	/**
	 * A tampered option is ignored, which means everyone stays in scope.
	 */
	public function test_unknown_saved_scope_is_ignored(): void {
		update_site_option( \Force_2FA_Policy::OPTION, 'read' );

		$this->assertNull( $this->policy()->scope() );
		$this->assertFalse( $this->policy()->is_exempt( $this->user() ) );
	}

	/**
	 * The capability filter (and the wp-config constant it mirrors) beats the saved scope.
	 */
	public function test_capability_filter_beats_saved_scope(): void {
		$this->policy()->save_scope( '' );
		$this->filter_value( 'force_2fa_enforced_capability', 'manage_options' );

		$this->assertTrue( $this->policy()->is_exempt( $this->user( 'editor' ) ) );
	}

	/**
	 * A role is excluded only when every role the user has is excluded.
	 */
	public function test_excluded_role_needs_every_role_excluded(): void {
		$this->filter_value( 'force_2fa_excluded_roles', array( 'Subscriber ' ) );
		$subscriber = $this->user( 'subscriber' );
		$both       = $this->user( 'subscriber' );
		$both->add_role( 'editor' );
		clean_user_cache( $both->ID );

		$this->assertTrue( $this->policy()->is_exempt( $subscriber ) );
		$this->assertFalse( $this->policy()->is_exempt( $both ) );
		$this->assertFalse( $this->policy()->is_exempt( $this->user( '' ) ) );
	}

	/**
	 * A role change in the same request is picked up.
	 */
	public function test_role_change_is_picked_up_immediately(): void {
		$this->policy()->save_scope( 'manage_options' );
		$user = $this->user( 'editor' );
		$this->assertTrue( $this->policy()->is_exempt( $user ) );

		wp_update_user(
			array(
				'ID'   => $user->ID,
				'role' => 'administrator',
			)
		);

		$this->assertFalse( $this->policy()->is_exempt( new \WP_User( $user->ID ) ) );
	}

	/**
	 * Users whose 2FA runs in Wordfence are not sent through a second 2FA plugin.
	 */
	public function test_wordfence_users_are_left_to_wordfence(): void {
		require_once __DIR__ . '/../fixtures/wordfence.php';
		$wordfence = $this->user();
		$other     = $this->user();
		\WordfenceLS\Controller_Users::$with_2fa = array( $wordfence->ID );

		try {
			$this->assertTrue( $this->policy()->is_exempt( $wordfence ) );
			$this->assertFalse( $this->policy()->is_exempt( $other ) );
		} finally {
			\WordfenceLS\Controller_Users::$with_2fa = array();
		}
	}

	/**
	 * The exemption filter has the last word.
	 */
	public function test_exemption_filter_has_the_last_word(): void {
		$this->filter_value( 'force_2fa_user_is_exempt', true );

		$this->assertTrue( $this->policy()->is_exempt( $this->user( 'administrator' ) ) );
	}
}
