<?php

namespace Force2FA\Tests;

use Force2FA\TestCase;

/**
 * Enforcement-scope + role-exclusion logic: force_2fa_user_is_exempt(), the pure
 * force_2fa_exemption_decision() it delegates to, and the network-aware capability
 * helper force_2fa_user_has_capability().
 */
final class ExemptTest extends TestCase {

	// --- Default scope: ALL users (the capability gate is off) ----------------

	public function test_enforced_capability_defaults_to_empty(): void {
		// No FORCE_2FA_ENFORCED_CAPABILITY defined and no filter → gate off.
		$this->assertSame( '', force_2fa_enforced_capability() );
	}

	public function test_all_users_enforced_by_default(): void {
		$user = $this->user( 1, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_non_string_capability_filter_means_all_users(): void {
		// A malformed filter value must fail toward MORE enforcement, not less.
		$this->setFilter( 'force_2fa_enforced_capability', null );
		$user = $this->user( 2, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	// --- Opt-in capability scope ----------------------------------------------

	public function test_capability_opt_in_exempts_users_without_it(): void {
		$this->enforceCapability( 'manage_options' );
		$subscriber = $this->user( 3, 'sub', array( 'subscriber' ) );
		$admin      = $this->adminUser( 4, 'admin' );
		$this->assertTrue( force_2fa_user_is_exempt( $subscriber ), 'non-admin is out of scope' );
		$this->assertFalse( force_2fa_user_is_exempt( $admin ), 'admin is in scope' );
	}

	public function test_capability_opt_in_catches_capability_not_role_slug(): void {
		// A custom role that grants manage_options is in scope even though its slug
		// is not 'administrator'.
		$this->enforceCapability( 'manage_options' );
		$user = $this->user( 5, 'custom', array( 'shop_manager' ), array( 'manage_options' => true ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_capability_opt_in_via_lower_capability(): void {
		$this->enforceCapability( 'edit_posts' );
		$contributor = $this->user( 6, 'contrib', array( 'contributor' ), array( 'edit_posts' => true ) );
		$subscriber  = $this->user( 7, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $contributor ) );
		$this->assertTrue( force_2fa_user_is_exempt( $subscriber ) );
	}

	// --- Network-aware capability (multisite) ---------------------------------

	public function test_super_admin_is_always_in_scope_on_multisite(): void {
		// A super admin holding only a low role on the current site must NOT be
		// exempt — their account is the highest-value one on the network.
		$this->enforceCapability( 'manage_options' );
		$this->superAdmin( 9 );
		$user = $this->user( 9, 'netadmin', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_capability_is_evaluated_for_the_current_site(): void {
		// The capability is checked on the site being logged into: a user who is an
		// admin on site 2 but only a subscriber on the current site (1) is exempt on
		// site 1, and enforced when the current site is 2.
		$this->enforceCapability( 'manage_options' );
		$user = $this->user( 10, 'multisiteadmin', array( 'subscriber' ) );
		$this->siteCaps( 1, 10, array() );
		$this->siteCaps( 2, 10, array( 'manage_options' => true ) );

		switch_to_blog( 1 );
		$this->assertTrue( force_2fa_user_is_exempt( $user ), 'exempt where they lack the capability' );

		switch_to_blog( 2 );
		$this->assertFalse( force_2fa_user_is_exempt( $user ), 'enforced on the site they administer' );
		restore_current_blog();
	}

	// --- Role denylist carve-out (applies within the scope) -------------------

	public function test_sole_excluded_role_is_exempt(): void {
		$this->excludeRoles( array( 'subscriber' ) );
		$user = $this->user( 11, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_multi_role_with_one_non_excluded_role_is_enforced(): void {
		// Excluding a low-priv role must not exempt a user who also holds a higher,
		// non-excluded role.
		$this->excludeRoles( array( 'subscriber' ) );
		$user = $this->user( 12, 'multi', array( 'subscriber', 'editor' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_no_roles_is_never_exempt_fail_secure(): void {
		$this->excludeRoles( array( 'subscriber' ) );
		$user = $this->user( 13, 'norole', array() );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_role_matching_is_case_insensitive(): void {
		$this->excludeRoles( array( 'Subscriber' ) );
		$user = $this->user( 14, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_all_roles_excluded_is_exempt(): void {
		$this->excludeRoles( array( 'subscriber', 'customer' ) );
		$user = $this->user( 15, 'shopper', array( 'subscriber', 'customer' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_excluded_roles_filter_accepts_a_single_string(): void {
		$this->setFilter( 'force_2fa_excluded_roles', 'subscriber' );
		$user = $this->user( 16, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_null_excluded_roles_filter_means_no_exclusions(): void {
		$this->setFilter( 'force_2fa_excluded_roles', null );
		$user = $this->user( 17, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_malformed_excluded_roles_entries_are_ignored(): void {
		$this->setFilter(
			'force_2fa_excluded_roles',
			array(
				array( 'subscriber' ),
				new \stdClass(),
				null,
				'customer',
			)
		);

		$subscriber = $this->user( 18, 'sub', array( 'subscriber' ) );
		$customer   = $this->user( 19, 'customer', array( 'customer' ) );

		$this->assertFalse( force_2fa_user_is_exempt( $subscriber ) );
		$this->assertTrue( force_2fa_user_is_exempt( $customer ) );
	}

	// --- The user-facing override filter --------------------------------------

	public function test_filter_can_force_exempt_even_without_role_exclusions(): void {
		$user = $this->user( 20, 'special', array( 'editor' ) );
		$this->setFilter( 'force_2fa_user_is_exempt', true );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_filter_can_force_enforce_an_otherwise_exempt_user(): void {
		// With admins-only opt-in, the override can pull a non-admin back into scope.
		$this->enforceCapability( 'manage_options' );
		$user = $this->user( 21, 'sub', array( 'subscriber' ) );
		$this->setFilter( 'force_2fa_user_is_exempt', false );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	// --- Pure decision function (no WordPress) --------------------------------

	public function test_decision_empty_capability_enforces_on_everyone(): void {
		$this->assertFalse(
			force_2fa_exemption_decision( array( 'subscriber' ), array(), '', false ),
			'gate off → not exempt via capability'
		);
	}

	public function test_decision_capability_gate_exempts_users_without_capability(): void {
		$this->assertTrue(
			force_2fa_exemption_decision( array( 'subscriber' ), array(), 'manage_options', false )
		);
		$this->assertFalse(
			force_2fa_exemption_decision( array( 'administrator' ), array(), 'manage_options', true )
		);
	}

	public function test_decision_role_denylist_among_in_scope_users(): void {
		$this->assertTrue(
			force_2fa_exemption_decision( array( 'subscriber' ), array( 'subscriber' ), '', true ),
			'sole excluded role → exempt'
		);
		$this->assertFalse(
			force_2fa_exemption_decision( array( 'subscriber', 'editor' ), array( 'subscriber' ), '', true ),
			'a non-excluded second role keeps enforcement'
		);
		$this->assertFalse(
			force_2fa_exemption_decision( array(), array( 'subscriber' ), '', true ),
			'no roles is never exempt via the denylist'
		);
	}
}
