<?php

namespace Force2FA\Tests;

use Force2FA\TestCase;
use Two_Factor_Core;

/**
 * Integration of the plugin's enforcement filter with the Two Factor contract.
 *
 * These assert the plugin's whole reason for existing: that appending the Email
 * provider actually makes Two_Factor_Core treat a user as "using 2FA" (so the
 * login challenge fires), and that an excluded user does not — without
 * reimplementing the plugin logic, by routing through its real filter callback.
 */
final class IntegrationTest extends TestCase {

	public function test_normal_user_ends_up_using_two_factor(): void {
		$this->user( 3, 'editoruser', array( 'editor' ) );

		$this->assertTrue( Two_Factor_Core::is_user_using_two_factor( 3 ) );
		$this->assertContains(
			'Two_Factor_Email',
			Two_Factor_Core::get_enabled_providers_for_user( 3 )
		);
	}

	public function test_excluded_user_is_not_using_two_factor(): void {
		$this->excludeRoles( array( 'subscriber' ) );
		$this->user( 4, 'subuser', array( 'subscriber' ) );

		$this->assertFalse( Two_Factor_Core::is_user_using_two_factor( 4 ) );
		$this->assertSame(
			array(),
			Two_Factor_Core::get_enabled_providers_for_user( 4 )
		);
	}

	public function test_existing_stronger_factor_is_preserved_with_email_floor(): void {
		$this->user( 5, 'totper', array( 'editor' ) );

		// A user who configured TOTP keeps it; Email is only appended as a floor.
		$providers = Two_Factor_Core::get_enabled_providers_for_user( 5, array( 'Two_Factor_Totp' ) );

		$this->assertSame( array( 'Two_Factor_Totp', 'Two_Factor_Email' ), $providers );
	}
}
