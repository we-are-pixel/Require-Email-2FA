<?php

namespace Force2FA\Tests;

use Force2FA\TestCase;

/**
 * Enforcement-scope + role-exclusion logic: force_2fa_user_is_exempt() and the
 * pure force_2fa_exemption_decision() it delegates to.
 */
final class ExemptTest extends TestCase {

	// --- Capability scope (the shipped default: 'manage_options') --------------

	public function test_non_admin_is_exempt_by_default(): void {
		// Default scope is manage_options, so a plain subscriber is out of scope.
		$user = $this->user( 1, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_administrator_is_enforced_by_default(): void {
		$user = $this->adminUser( 2, 'admin' );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_non_admin_with_manage_options_is_enforced(): void {
		// Scope is capability-based, not role-based: a custom role that grants
		// manage_options is in scope even though its slug isn't 'administrator'.
		$user = $this->user( 3, 'custom', array( 'shop_manager' ), array( 'manage_options' => true ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_empty_capability_enforces_on_everyone(): void {
		// Restoring the original site-wide baseline: '' disables the gate.
		$this->enforceCapability( '' );
		$user = $this->user( 4, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_custom_enforced_capability_widens_scope(): void {
		// Enforce on contributors-and-up via edit_posts.
		$this->enforceCapability( 'edit_posts' );
		$contributor = $this->user( 5, 'contrib', array( 'contributor' ), array( 'edit_posts' => true ) );
		$subscriber  = $this->user( 6, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $contributor ) );
		$this->assertTrue( force_2fa_user_is_exempt( $subscriber ) );
	}

	public function test_non_string_capability_filter_enforces_on_everyone(): void {
		// A malformed filter value must fail toward MORE enforcement, not less.
		$this->setFilter( 'force_2fa_enforced_capability', null );
		$user = $this->user( 7, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	// --- Role denylist carve-out (layered on top of capability scope) ---------

	public function test_excluded_role_exempts_an_in_scope_admin(): void {
		// An admin is in scope, but excluding their sole role carves them out.
		$this->excludeRoles( array( 'administrator' ) );
		$user = $this->adminUser( 8, 'admin' );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_role_denylist_in_isolation_with_gate_disabled(): void {
		// With the capability gate off, only the role denylist decides.
		$this->enforceCapability( '' );

		$this->excludeRoles( array( 'subscriber' ) );
		$sole  = $this->user( 10, 'sub', array( 'subscriber' ) );
		$multi = $this->user( 11, 'multi', array( 'subscriber', 'editor' ) );
		$none  = $this->user( 12, 'norole', array() );

		$this->assertTrue( force_2fa_user_is_exempt( $sole ), 'sole excluded role is exempt' );
		$this->assertFalse( force_2fa_user_is_exempt( $multi ), 'a non-excluded second role keeps enforcement' );
		$this->assertFalse( force_2fa_user_is_exempt( $none ), 'no roles is never exempt (fail secure)' );
	}

	public function test_role_matching_is_case_insensitive(): void {
		$this->enforceCapability( '' );
		$this->excludeRoles( array( 'Subscriber' ) );
		$user = $this->user( 13, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	// --- Config plumbing (filters feeding the excluded-roles list) ------------

	public function test_excluded_roles_filter_accepts_a_single_string(): void {
		$this->enforceCapability( '' );
		$this->setFilter( 'force_2fa_excluded_roles', 'subscriber' );
		$user = $this->user( 14, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_malformed_excluded_roles_entries_are_ignored(): void {
		$this->enforceCapability( '' );
		$this->setFilter(
			'force_2fa_excluded_roles',
			array(
				array( 'subscriber' ),
				new \stdClass(),
				null,
				'customer',
			)
		);

		$subscriber = $this->user( 15, 'sub', array( 'subscriber' ) );
		$customer   = $this->user( 16, 'customer', array( 'customer' ) );

		$this->assertFalse( force_2fa_user_is_exempt( $subscriber ) );
		$this->assertTrue( force_2fa_user_is_exempt( $customer ) );
	}

	// --- The user-facing override filter --------------------------------------

	public function test_filter_can_force_exempt_an_in_scope_admin(): void {
		$user = $this->adminUser( 17, 'admin' );
		$this->setFilter( 'force_2fa_user_is_exempt', true );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_filter_can_force_enforce_an_out_of_scope_user(): void {
		// The override can also pull an otherwise-exempt non-admin back into scope.
		$user = $this->user( 18, 'sub', array( 'subscriber' ) );
		$this->setFilter( 'force_2fa_user_is_exempt', false );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	// --- Pure decision function (no WordPress) --------------------------------

	public function test_decision_capability_gate_exempts_users_without_capability(): void {
		$this->assertTrue(
			force_2fa_exemption_decision( array( 'subscriber' ), array(), 'manage_options', false )
		);
		$this->assertFalse(
			force_2fa_exemption_decision( array( 'administrator' ), array(), 'manage_options', true )
		);
	}

	public function test_decision_empty_capability_disables_the_gate(): void {
		// No capability configured → gate off → fall through to role logic (no
		// exclusions here, so not exempt).
		$this->assertFalse(
			force_2fa_exemption_decision( array( 'subscriber' ), array(), '', false )
		);
	}

	public function test_decision_role_denylist_among_in_scope_users(): void {
		$this->assertTrue(
			force_2fa_exemption_decision( array( 'administrator' ), array( 'administrator' ), 'manage_options', true ),
			'in scope but sole role excluded → exempt'
		);
		$this->assertFalse(
			force_2fa_exemption_decision( array( 'administrator', 'editor' ), array( 'administrator' ), 'manage_options', true ),
			'a non-excluded second role keeps enforcement'
		);
		$this->assertFalse(
			force_2fa_exemption_decision( array(), array( 'subscriber' ), '', true ),
			'no roles is never exempt via the denylist'
		);
	}
}
