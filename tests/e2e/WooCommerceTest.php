<?php
/**
 * Logging in through the WooCommerce My Account form.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests\E2E;

/**
 * The shop's own login form must not skip two-factor, for admins either.
 */
final class WooCommerceTest extends E2ETestCase {

	/**
	 * Path of the My Account page.
	 *
	 * @var string
	 */
	private static $account = '';

	/**
	 * Activate WooCommerce for this class only, with a customer and a shop manager.
	 */
	public static function setUpBeforeClass(): void {
		try {
			self::wp( 'plugin', 'install', 'woocommerce', '--activate' );
		} catch ( \RuntimeException $e ) {
			// Current WooCommerce needs newer WordPress and PHP than the oldest sites in the CI matrix.
			if ( ! str_contains( $e->getMessage(), 'does not work with your version' ) ) {
				throw $e;
			}

			self::markTestSkipped( 'Current WooCommerce does not support this WordPress or PHP version.' );
		}

		// Otherwise the next admin page redirects to WooCommerce's setup wizard.
		self::wp( 'transient', 'delete', '_wc_activation_redirect' );
		self::wp( 'user', 'create', 'shopper', 'shopper@example.test', '--role=customer', '--user_pass=pass-shopper' );
		self::wp( 'user', 'create', 'manager', 'manager@example.test', '--role=shop_manager', '--user_pass=pass-manager' );

		self::$account = '/?page_id=' . (int) self::wp( 'option', 'get', 'woocommerce_myaccount_page_id', '--skip-plugins' );
	}

	/**
	 * Leave the site without WooCommerce for the other tests.
	 */
	public static function tearDownAfterClass(): void {
		self::wp( 'plugin', 'deactivate', 'woocommerce' );
	}

	/**
	 * An admin logging in on the shop's My Account page still needs the emailed code.
	 */
	public function test_admin_on_the_shop_form_needs_the_code(): void {
		$jar      = $this->jar();
		$response = $this->shop_log_in( $jar, 'admin', 'password' );

		$this->assertStringContainsString( 'name="two-factor-email-code"', $response['body'] );
		$this->assertFalse( $this->logged_in( $jar ) );

		$this->answer( $jar, $response['body'], 'two-factor-email-code', $this->emailed_code() );

		$this->assertTrue( $this->logged_in( $jar ) );
	}

	/**
	 * With everyone in scope, a customer needs the code too.
	 */
	public function test_customer_needs_the_code_when_everyone_is_in_scope(): void {
		$jar = $this->jar();

		$this->assertStringContainsString( 'name="two-factor-email-code"', $this->shop_log_in( $jar, 'shopper', 'pass-shopper' )['body'] );
		$this->assertFalse( $this->in_my_account( $jar ) );
	}

	/**
	 * "Contributors and up": customers log in with the password, shop managers still need the code.
	 */
	public function test_contributors_and_up_lets_customers_in_but_not_shop_managers(): void {
		self::wp( 'option', 'update', 'force_2fa_enforced_capability', 'edit_posts' );

		$customer = $this->jar();
		$this->shop_log_in( $customer, 'shopper', 'pass-shopper' );
		$this->assertTrue( $this->in_my_account( $customer ) );

		$this->assertStringContainsString( 'name="two-factor-email-code"', $this->shop_log_in( $this->jar(), 'manager', 'pass-manager' )['body'] );
	}

	/**
	 * Submit the My Account login form.
	 *
	 * @param string $jar      Cookie file.
	 * @param string $login    Login.
	 * @param string $password Password.
	 * @return array{status: int, headers: string, body: string}
	 */
	private function shop_log_in( string $jar, string $login, string $password ): array {
		$fields = $this->hidden_fields( $this->request( $jar, self::$account )['body'], '//form[contains(@class, "woocommerce-form-login")]' );

		return $this->request(
			$jar,
			self::$account,
			$fields + array(
				'username' => $login,
				'password' => $password,
				'login'    => 'Log in',
			)
		);
	}

	/**
	 * Whether the cookie file holds a customer session (My Account shows the log-out link).
	 *
	 * @param string $jar Cookie file.
	 */
	private function in_my_account( string $jar ): bool {
		return str_contains( $this->request( $jar, self::$account )['body'], 'customer-logout' );
	}
}
