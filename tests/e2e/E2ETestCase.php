<?php
/**
 * Helpers that log in through wp-login.php and xmlrpc.php like a browser or client would.
 *
 * @package Force_2FA
 */

namespace Force_2FA\Tests\E2E;

/**
 * Base class for HTTP tests.
 */
abstract class E2ETestCase extends \PHPUnit\Framework\TestCase {

	const BASE = 'http://127.0.0.1:8888';

	/**
	 * Application Passwords created for the service accounts, by login.
	 *
	 * @var array<string, string>
	 */
	protected static $app_passwords = array();

	/**
	 * Test users: subscriber, authenticator-app user, two XML-RPC accounts.
	 */
	public static function create_users(): void {
		self::wp( 'user', 'create', 'sub', 'sub@example.test', '--role=subscriber', '--user_pass=pass-sub' );
		self::wp( 'user', 'create', 'totp', 'totp@example.test', '--role=subscriber', '--user_pass=pass-totp' );
		self::wp( 'user', 'create', 'svc', 'svc@example.test', '--role=editor', '--user_pass=pass-svc' );
		self::wp( 'user', 'create', 'other', 'other@example.test', '--role=editor', '--user_pass=pass-other' );
		self::wp( 'eval', '$id = get_user_by( "login", "totp" )->ID; update_user_meta( $id, Two_Factor_Totp::SECRET_META_KEY, Two_Factor_Totp::generate_key() ); Two_Factor_Core::enable_provider_for_user( $id, "Two_Factor_Totp" );' );

		foreach ( array( 'svc', 'other' ) as $login ) {
			self::$app_passwords[ $login ] = self::wp( 'user', 'application-password', 'create', $login, 'e2e', '--porcelain' );
		}
	}

	/**
	 * Back to the default configuration: everyone in scope, production, Two Factor active.
	 */
	protected function setUp(): void {
		file_put_contents( self::config_file(), "<?php\n" );
		self::wp( 'eval-file', __DIR__ . '/reset.php' );
	}

	/**
	 * Run WP-CLI against the test site.
	 *
	 * @param string ...$args Arguments.
	 */
	protected static function wp( string ...$args ): string {
		$command = escapeshellarg( __DIR__ . '/../bin/wp.sh' ) . ' single ' . implode( ' ', array_map( 'escapeshellarg', $args ) ) . ' 2>&1';

		exec( $command, $output, $status );

		if ( 0 !== $status ) {
			throw new \RuntimeException( "wp failed: $command\n" . implode( "\n", $output ) );
		}

		return trim( implode( "\n", $output ) );
	}

	/**
	 * Set a wp-config constant for the next requests.
	 *
	 * @param string $name  Constant.
	 * @param string $value PHP expression.
	 */
	protected static function define_constant( string $name, string $value ): void {
		file_put_contents( self::config_file(), "define( '$name', $value );\n", FILE_APPEND );
	}

	/**
	 * The test site's extra wp-config file.
	 */
	private static function config_file(): string {
		return ( getenv( 'FORCE_2FA_TESTS_ROOT' ) ?: sys_get_temp_dir() . '/require-email-2fa' ) . '/single/test-config.php';
	}

	/**
	 * Submit the login form. Returns the response for the next step.
	 *
	 * @param string $jar      Cookie file.
	 * @param string $login    Login.
	 * @param string $password Password.
	 * @return array{status: int, headers: string, body: string}
	 */
	protected function log_in( string $jar, string $login, string $password ): array {
		$this->request( $jar, '/wp-login.php' );

		return $this->request(
			$jar,
			'/wp-login.php',
			array(
				'log'         => $login,
				'pwd'         => $password,
				'redirect_to' => self::BASE . '/wp-admin/',
				'testcookie'  => '1',
			)
		);
	}

	/**
	 * Answer the two-factor form with a code.
	 *
	 * @param string $jar   Cookie file.
	 * @param string $form  HTML of the two-factor form.
	 * @param string $field Code field name.
	 * @param string $code  Code.
	 * @return array{status: int, headers: string, body: string}
	 */
	protected function answer( string $jar, string $form, string $field, string $code ): array {
		$fields = $this->two_factor_form( $form );

		$fields[ $field ] = $code;

		return $this->request( $jar, '/wp-login.php?action=validate_2fa', $fields );
	}

	/**
	 * Hidden fields of Two Factor's login form (provider, user, nonce).
	 *
	 * @param string $html Page HTML.
	 * @return array<string, string>
	 */
	protected function two_factor_form( string $html ): array {
		return $this->hidden_fields( $html, '//form[@id="loginform"]' );
	}

	/**
	 * Hidden fields of the form matched by an XPath query.
	 *
	 * @param string $html Page HTML.
	 * @param string $form XPath of the form.
	 * @return array<string, string>
	 */
	protected function hidden_fields( string $html, string $form ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html );
		libxml_clear_errors();

		$fields = array();

		foreach ( ( new \DOMXPath( $doc ) )->query( $form . '//input[@type="hidden"]' ) as $input ) {
			$fields[ $input->getAttribute( 'name' ) ] = $input->getAttribute( 'value' );
		}

		return $fields;
	}

	/**
	 * Whether the cookie file holds a working admin session.
	 *
	 * @param string $jar Cookie file.
	 */
	protected function logged_in( string $jar ): bool {
		return 200 === $this->request( $jar, '/wp-admin/profile.php' )['status'];
	}

	/**
	 * Log in completely, answering the emailed code when asked.
	 *
	 * @param string $login    Login.
	 * @param string $password Password.
	 * @return string Cookie file with the session.
	 */
	protected function session( string $login, string $password ): string {
		$jar      = $this->jar();
		$response = $this->log_in( $jar, $login, $password );

		if ( str_contains( $response['body'], 'name="two-factor-email-code"' ) ) {
			$this->answer( $jar, $response['body'], 'two-factor-email-code', $this->emailed_code() );
		}

		$profile = $this->request( $jar, '/wp-admin/profile.php' );

		$this->assertSame( 200, $profile['status'], "$login could not log in. Response:\n{$profile['headers']}\nCookies:\n" . file_get_contents( $jar ) );

		return $jar;
	}

	/**
	 * The code from the last email WordPress tried to send.
	 */
	protected function emailed_code(): string {
		// Without plugins, so notices from other plugins (WooCommerce) can't get mixed into the output.
		preg_match( '/\b\d{6,10}\b/', self::wp( 'option', 'get', 'force_2fa_tests_last_mail', '--skip-plugins' ), $code );

		return $code[0];
	}

	/**
	 * Call wp.getUsersBlogs over XML-RPC. Returns the raw response.
	 *
	 * @param string $login    Login.
	 * @param string $password Password or Application Password.
	 */
	protected function xmlrpc( string $login, string $password ): string {
		$body = '<?xml version="1.0"?><methodCall><methodName>wp.getUsersBlogs</methodName><params>'
			. '<param><value><string>' . htmlspecialchars( $login ) . '</string></value></param>'
			. '<param><value><string>' . htmlspecialchars( $password ) . '</string></value></param>'
			. '</params></methodCall>';

		return $this->request( $this->jar(), '/xmlrpc.php', $body )['body'];
	}

	/**
	 * A fresh cookie file.
	 */
	protected function jar(): string {
		return (string) tempnam( sys_get_temp_dir(), 'force-2fa-cookies' );
	}

	/**
	 * HTTP request without following redirects.
	 *
	 * @param string               $jar  Cookie file.
	 * @param string               $path Path on the test site.
	 * @param array<string>|string $post Form fields or raw body; empty for GET.
	 * @return array{status: int, headers: string, body: string}
	 */
	protected function request( string $jar, string $path, $post = array() ): array {
		$curl = curl_init( self::BASE . $path );

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => true,
				CURLOPT_COOKIEJAR      => $jar,
				CURLOPT_COOKIEFILE     => $jar,
			)
		);

		if ( array() !== $post ) {
			curl_setopt( $curl, CURLOPT_POSTFIELDS, is_array( $post ) ? http_build_query( $post ) : $post );
		}

		$raw  = (string) curl_exec( $curl );
		$size = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );

		return array(
			'status'  => curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ),
			'headers' => substr( $raw, 0, $size ),
			'body'    => substr( $raw, $size ),
		);
	}
}
