<?php

namespace Force2FA\Tests;

use Force2FA\TestCase;

/**
 * Role-exclusion logic: force_2fa_user_is_exempt().
 */
final class ExemptTest extends TestCase {

	public function test_no_exclusions_means_nobody_is_exempt(): void {
		$user = $this->user( 1, 'sub', array( 'subscriber' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_sole_excluded_role_is_exempt(): void {
		$this->excludeRoles( array( 'subscriber' ) );
		$user = $this->user( 1, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_multi_role_with_one_non_excluded_role_is_enforced(): void {
		// The core security rule: excluding a low-priv role must not exempt a
		// user who also holds a higher, non-excluded role.
		$this->excludeRoles( array( 'subscriber' ) );
		$user = $this->user( 2, 'multi', array( 'subscriber', 'editor' ) );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_no_roles_is_never_exempt_fail_secure(): void {
		$this->excludeRoles( array( 'subscriber' ) );
		$user = $this->user( 3, 'norole', array() );
		$this->assertFalse( force_2fa_user_is_exempt( $user ) );
	}

	public function test_role_matching_is_case_insensitive(): void {
		$this->excludeRoles( array( 'Subscriber' ) );
		$user = $this->user( 4, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_all_roles_excluded_is_exempt(): void {
		$this->excludeRoles( array( 'subscriber', 'customer' ) );
		$user = $this->user( 5, 'shopper', array( 'subscriber', 'customer' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_filter_can_force_exempt_even_without_role_exclusions(): void {
		$user = $this->user( 6, 'special', array( 'editor' ) );
		$this->setFilter( 'force_2fa_user_is_exempt', true );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_excluded_roles_filter_accepts_a_single_string(): void {
		$this->setFilter( 'force_2fa_excluded_roles', 'subscriber' );
		$user = $this->user( 7, 'sub', array( 'subscriber' ) );
		$this->assertTrue( force_2fa_user_is_exempt( $user ) );
	}

	public function test_null_excluded_roles_filter_means_no_exclusions(): void {
		$this->setFilter( 'force_2fa_excluded_roles', null );
		$user = $this->user( 8, 'sub', array( 'subscriber' ) );
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

		$subscriber = $this->user( 9, 'sub', array( 'subscriber' ) );
		$customer   = $this->user( 10, 'customer', array( 'customer' ) );

		$this->assertFalse( force_2fa_user_is_exempt( $subscriber ) );
		$this->assertTrue( force_2fa_user_is_exempt( $customer ) );
	}
}
