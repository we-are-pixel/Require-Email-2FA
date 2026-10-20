<?php
/**
 * Base class: every test runs in a database transaction that is rolled back.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests;

use Force_2FA;
use Force_2FA_Policy;
use WP_User;

/**
 * Shared helpers for tests against real WordPress and Two Factor.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/**
	 * Filters added by the current test.
	 *
	 * @var array<int, array{0: string, 1: callable}>
	 */
	private $filters = array();

	/**
	 * Start a transaction.
	 */
	protected function setUp(): void {
		$GLOBALS['wpdb']->query( 'START TRANSACTION' );
		wp_cache_flush();
	}

	/**
	 * Roll back everything the test changed.
	 */
	protected function tearDown(): void {
		foreach ( $this->filters as $filter ) {
			remove_filter( $filter[0], $filter[1] );
		}

		$GLOBALS['wpdb']->query( 'ROLLBACK' );
		wp_cache_flush();
	}

	/**
	 * The plugin's policy.
	 */
	protected function policy(): Force_2FA_Policy {
		return Force_2FA::instance()->policy;
	}

	/**
	 * Create a user. IDs are reused after a rollback, so drop any cached decision.
	 *
	 * @param string $role  Role, or '' for none.
	 * @param string $login Login, random when empty.
	 */
	protected function user( string $role = 'subscriber', string $login = '' ): WP_User {
		$id = wp_insert_user(
			array(
				'user_login' => '' !== $login ? $login : uniqid( 'user' ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( 'user' ) . '@example.test',
				'role'       => $role,
			)
		);

		clean_user_cache( $id );

		return new WP_User( $id );
	}

	/**
	 * Add a filter for this test only.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 */
	protected function filter( string $hook, callable $callback ): void {
		add_filter( $hook, $callback );
		$this->filters[] = array( $hook, $callback );
	}

	/**
	 * Make a filter return a fixed value for this test only.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value to return.
	 */
	protected function filter_value( string $hook, $value ): void {
		$this->filter(
			$hook,
			function () use ( $value ) {
				return $value;
			}
		);
	}
}
