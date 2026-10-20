<?php
/**
 * Minimal stand-in for Wordfence Login Security's user controller.
 *
 * @package Force_2FA
 */

namespace WordfenceLS;

/**
 * Reports 2FA as active for the listed user IDs.
 */
class Controller_Users {

	/**
	 * User IDs with Wordfence 2FA.
	 *
	 * @var int[]
	 */
	public static $with_2fa = array();

	/**
	 * Shared instance, like Wordfence.
	 */
	public static function shared(): self {
		return new self();
	}

	/**
	 * Whether the user has Wordfence 2FA.
	 *
	 * @param \WP_User $user User.
	 */
	public function has_2fa_active( $user ): bool {
		return in_array( $user->ID, self::$with_2fa, true );
	}
}
