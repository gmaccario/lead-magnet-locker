<?php
/**
 * Minimal cURL client for the E2E tests (one cookie jar per instance).
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E\Support;

/**
 * HTTP client.
 */
final class Http {

	/**
	 * Cookie jar file.
	 *
	 * @var string
	 */
	private string $jar;

	/**
	 * Constructor.
	 *
	 * @param string $base_url Site URL, e.g. http://localhost:85.
	 */
	public function __construct( private string $base_url ) {
		$this->jar = (string) tempnam( sys_get_temp_dir(), 'lml-e2e-jar' );
	}

	/**
	 * Remove the cookie jar.
	 */
	public function __destruct() {
		if ( is_file( $this->jar ) ) {
			unlink( $this->jar );
		}
	}

	/**
	 * GET request.
	 *
	 * @param string $url Absolute URL or path.
	 * @return Response
	 */
	public function get( string $url ): Response {
		return $this->request( 'GET', $url );
	}

	/**
	 * POST request (form-encoded, or multipart when a value is a CURLFile).
	 *
	 * @param string               $url    Absolute URL or path.
	 * @param array<string, mixed> $fields Form fields.
	 * @return Response
	 */
	public function post( string $url, array $fields ): Response {
		$multipart = (bool) array_filter( $fields, static fn( $value ): bool => $value instanceof \CURLFile );

		return $this->request( 'POST', $url, $multipart ? $fields : http_build_query( $fields ) );
	}

	/**
	 * Log in through wp-login.php.
	 *
	 * @param string $login    User login.
	 * @param string $password Password.
	 * @return bool Whether WordPress set the logged-in cookie.
	 */
	public function login( string $login, string $password ): bool {
		$this->get( '/wp-login.php' ); // Sets the test cookie.
		$response = $this->post(
			'/wp-login.php',
			array(
				'log'        => $login,
				'pwd'        => $password,
				'wp-submit'  => 'Log In',
				'testcookie' => '1',
			)
		);

		return 302 === $response->status && str_contains( (string) file_get_contents( $this->jar ), 'wordpress_logged_in_' );
	}

	/**
	 * Send a request.
	 *
	 * @param string                      $method Method.
	 * @param string                      $url    Absolute URL or path.
	 * @param string|array<string, mixed> $body   Request body.
	 * @return Response
	 */
	private function request( string $method, string $url, string|array $body = '' ): Response {
		$headers = array();
		$curl    = curl_init( str_starts_with( $url, 'http' ) ? $url : $this->base_url . $url );

		curl_setopt_array(
			$curl,
			array(
				CURLOPT_CUSTOMREQUEST  => $method,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_COOKIEJAR      => $this->jar,
				CURLOPT_COOKIEFILE     => $this->jar,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTPHEADER     => array( 'X-LML-E2E: 1' ),
				CURLOPT_HEADERFUNCTION => static function ( $handle, string $line ) use ( &$headers ): int {
					$parts = explode( ':', $line, 2 );
					if ( 2 === count( $parts ) ) {
						$headers[ strtolower( trim( $parts[0] ) ) ][] = trim( $parts[1] );
					}
					return strlen( $line );
				},
			)
		);
		if ( 'POST' === $method ) {
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $body );
		}

		$content = curl_exec( $curl );
		if ( false === $content ) {
			throw new \RuntimeException( 'HTTP request failed: ' . curl_error( $curl ) );
		}

		return new Response( (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ), $headers, (string) $content );
	}
}
