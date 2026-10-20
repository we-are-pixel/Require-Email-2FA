<?php
/**
 * The part of the Two Factor plugin API this plugin calls.
 */

class Two_Factor_Core {
	/**
	 * @return array<string, object>
	 */
	public static function get_providers() {}

	/**
	 * @param WP_User|int|null $user
	 * @return array<string, object>|WP_Error
	 */
	public static function get_available_providers_for_user( $user = null ) {}
}
