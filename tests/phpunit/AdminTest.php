<?php
/**
 * Saving the scope form.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

use Force_2FA_Admin;

/**
 * The admin-post handler checks capability and nonce before saving.
 */
final class AdminTest extends TestCase {

	/**
	 * Start every test with "administrators only" saved.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->policy()->save_scope( 'manage_options' );
	}

	/**
	 * An admin with a valid nonce saves the choice.
	 */
	public function test_admin_saves_the_scope(): void {
		$this->assertSame( 'redirect', $this->submit( 'administrator', 'edit_posts', true ) );

		$this->assertSame( 'edit_posts', $this->policy()->scope() );
	}

	/**
	 * Without a valid nonce nothing is saved.
	 */
	public function test_scope_needs_a_valid_nonce(): void {
		$this->assertSame( 'wp_die', $this->submit( 'administrator', '', false ) );

		$this->assertSame( 'manage_options', $this->policy()->scope() );
	}

	/**
	 * A non-admin is refused even with their own valid nonce.
	 */
	public function test_scope_needs_an_admin(): void {
		$this->assertSame( 'wp_die', $this->submit( 'editor', '', true ) );

		$this->assertSame( 'manage_options', $this->policy()->scope() );
	}

	/**
	 * Post the form as a new user with the given role.
	 *
	 * @param string $role        Role of the submitting user.
	 * @param string $scope       Submitted scope.
	 * @param bool   $valid_nonce Whether to send a valid nonce.
	 * @return string How the handler ended: "wp_die" or "redirect".
	 */
	private function submit( string $role, string $scope, bool $valid_nonce ): string {
		$user = $this->user( $role );

		if ( is_multisite() && 'administrator' === $role ) {
			grant_super_admin( $user->ID );
		}

		wp_set_current_user( $user->ID );
		$_POST['force_2fa_scope'] = $scope;
		$_REQUEST['_wpnonce']     = $valid_nonce ? wp_create_nonce( Force_2FA_Admin::SCOPE_ACTION ) : 'forged';

		// wp_die() and the final redirect would end the test run, so turn both into exceptions.
		$this->filter_value(
			'wp_die_handler',
			function () {
				throw new \RuntimeException( 'wp_die' );
			}
		);
		$this->filter(
			'wp_redirect',
			function () {
				throw new \RuntimeException( 'redirect' );
			}
		);

		try {
			do_action( 'admin_post_' . Force_2FA_Admin::SCOPE_ACTION );
			return 'nothing';
		} catch ( \RuntimeException $e ) {
			return $e->getMessage();
		} finally {
			unset( $_POST['force_2fa_scope'], $_REQUEST['_wpnonce'] );
			wp_set_current_user( 0 );
		}
	}
}
