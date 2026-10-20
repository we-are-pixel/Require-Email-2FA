<?php
/**
 * Plugin bootstrap.
 *
 * @package Force_2FA
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin parts together.
 */
final class Force_2FA {

	/**
	 * The single instance.
	 *
	 * @var Force_2FA|null
	 */
	private static $instance = null;

	/**
	 * Decides who must use two-factor.
	 *
	 * @var Force_2FA_Policy
	 */
	public $policy;

	/**
	 * Return the single instance.
	 *
	 * @return Force_2FA
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Start the parts. Enforcement is skipped when the kill switch is on.
	 */
	private function __construct() {
		$this->policy = new Force_2FA_Policy();

		new Force_2FA_Admin( $this->policy );
		new Force_2FA_Updater();

		if ( self::is_disabled() ) {
			return;
		}

		new Force_2FA_Providers( $this->policy );
		new Force_2FA_XMLRPC();
	}

	/**
	 * Whether FORCE_2FA_DISABLE turns enforcement off.
	 *
	 * @return bool
	 */
	public static function is_disabled(): bool {
		return defined( 'FORCE_2FA_DISABLE' ) && FORCE_2FA_DISABLE;
	}

	/**
	 * Whether Two Factor is loaded and offers its Email provider.
	 *
	 * @return bool
	 */
	public static function two_factor_ready(): bool {
		return class_exists( 'Two_Factor_Core' ) && array_key_exists( Force_2FA_Providers::EMAIL, (array) Two_Factor_Core::get_providers() );
	}

	/**
	 * Turn a configured list (constant or filter) into trimmed, lowercase strings.
	 *
	 * @param mixed $values Raw list.
	 * @return string[]
	 */
	public static function string_list( $values ): array {
		$list = array();

		foreach ( (array) $values as $value ) {
			$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';

			if ( '' !== $value ) {
				$list[] = $value;
			}
		}

		return $list;
	}
}
