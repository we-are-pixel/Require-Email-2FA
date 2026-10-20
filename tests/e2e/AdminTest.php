<?php
/**
 * What admins see and change.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests\E2E;

/**
 * Notices and the "who must use 2FA" setting.
 */
final class AdminTest extends E2ETestCase {

	/**
	 * Until a scope is saved the admin is asked, with "contributors and up" pre-selected; saving stores the choice.
	 */
	public function test_admin_chooses_who_must_use_two_factor(): void {
		$jar  = $this->session( 'admin', 'password' );
		$page = $this->request( $jar, '/wp-admin/' )['body'];
		$this->assertStringContainsString( 'name="force_2fa_scope"', $page );
		$this->assertMatchesRegularExpression( '/value="edit_posts"\s+checked=/', $page );

		$fields                    = $this->scope_form( $page );
		$fields['force_2fa_scope'] = 'edit_posts';
		$this->request( $jar, '/wp-admin/admin-post.php', $fields );

		$this->assertSame( 'edit_posts', self::wp( 'option', 'get', 'force_2fa_enforced_capability' ) );
		$this->assertStringNotContainsString( 'name="force_2fa_scope"', $this->request( $jar, '/wp-admin/' )['body'] );
	}

	/**
	 * The "2FA scope" link reopens the form; an unknown value falls back to all users.
	 */
	public function test_scope_can_be_changed_later(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'manage_options' );
		$jar = $this->session( 'admin', 'password' );

		$this->assertStringContainsString( 'force_2fa_scope=edit', $this->request( $jar, '/wp-admin/plugins.php' )['body'] );

		$fields                    = $this->scope_form( $this->request( $jar, '/wp-admin/plugins.php?force_2fa_scope=edit' )['body'] );
		$fields['force_2fa_scope'] = 'read';
		$this->request( $jar, '/wp-admin/admin-post.php', $fields );

		$this->assertSame( '', self::wp( 'option', 'get', 'force_2fa_enforced_capability' ) );
	}

	/**
	 * Saving without a valid nonce does nothing.
	 */
	public function test_scope_needs_a_nonce(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'manage_options' );
		$jar = $this->session( 'admin', 'password' );

		$this->request(
			$jar,
			'/wp-admin/admin-post.php',
			array(
				'action'          => 'force_2fa_set_scope',
				'force_2fa_scope' => '',
				'_wpnonce'        => 'forged',
			)
		);

		$this->assertSame( 'manage_options', self::wp( 'option', 'get', 'force_2fa_enforced_capability' ) );
	}

	/**
	 * A non-admin cannot change the scope.
	 */
	public function test_scope_needs_an_admin(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'manage_options' );
		$admin  = $this->session( 'admin', 'password' );
		$fields = $this->scope_form( $this->request( $admin, '/wp-admin/plugins.php?force_2fa_scope=edit' )['body'] );
		$sub    = $this->session( 'sub', 'pass-sub' );

		$fields['force_2fa_scope'] = '';
		$this->request( $sub, '/wp-admin/admin-post.php', $fields );

		$this->assertSame( 'manage_options', self::wp( 'option', 'get', 'force_2fa_enforced_capability' ) );
	}

	/**
	 * Admins are told when the kill switch is on.
	 */
	public function test_admin_is_told_when_kill_switch_is_on(): void {
		self::define_constant( 'FORCE_2FA_DISABLE', 'true' );

		$this->assertStringContainsString( 'turned off by FORCE_2FA_DISABLE', $this->dashboard() );
	}

	/**
	 * Admins are told when Two Factor is not active.
	 */
	public function test_admin_is_told_when_two_factor_is_inactive(): void {
		self::wp( 'plugin', 'deactivate', 'two-factor' );

		$this->assertStringContainsString( 'is NOT enforced', $this->dashboard() );
	}

	/**
	 * Admins are told that a local copy has no email codes.
	 */
	public function test_admin_is_told_on_a_local_copy(): void {
		self::define_constant( 'WP_ENVIRONMENT_TYPE', "'local'" );

		$this->assertStringContainsString( 'Two-factor codes are off on this local copy', $this->dashboard() );
	}

	/**
	 * The admin dashboard HTML, with the scope already chosen.
	 */
	private function dashboard(): string {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', '' );

		return $this->request( $this->session( 'admin', 'password' ), '/wp-admin/' )['body'];
	}

	/**
	 * Hidden fields of the "who must use 2FA" form.
	 *
	 * @param string $html Page HTML.
	 * @return array<string, string>
	 */
	private function scope_form( string $html ): array {
		return $this->hidden_fields( $html, '//form[.//input[@name="force_2fa_scope"]]' );
	}
}
