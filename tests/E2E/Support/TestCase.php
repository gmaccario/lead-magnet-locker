<?php
/**
 * Base class for the end-to-end tests.
 *
 * The tests drive the real local site over HTTP, like a browser would, so they
 * check the plugin's public behaviour without depending on its internals.
 * Fixture data is created before each test class and removed after it.
 *
 * Environment:
 * - LML_E2E_BASE_URL         Site URL (default http://localhost:85).
 * - LML_E2E_FIXTURE          Command that runs tests/E2E/fixture.php in the web container.
 * - LML_E2E_SNAPSHOTS        Snapshot folder (default tests/E2E/__snapshots__, git-ignored).
 * - LML_E2E_UPDATE_SNAPSHOTS Set to 1 to rewrite snapshots instead of comparing.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E\Support;

/**
 * E2E test case.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	protected const PREFIX = 'lml-e2e-';
	protected const EMAIL  = '@lml-e2e.test';

	/**
	 * Fixture data returned by "setup".
	 *
	 * @var array<string, mixed>
	 */
	protected static array $fixture = array();

	/**
	 * Logged-in clients, kept for the whole test class (logging in is slow).
	 *
	 * @var array<string, Http>
	 */
	private static array $clients = array();

	/**
	 * Create the fixture data.
	 */
	public static function setUpBeforeClass(): void {
		self::$clients = array();
		self::$fixture = self::fixture( 'setup' );
	}

	/**
	 * Remove the fixture data and restore the settings.
	 */
	public static function tearDownAfterClass(): void {
		self::$clients = array();
		self::fixture( 'teardown' );
	}

	/**
	 * Run a fixture command in the web server container.
	 *
	 * @param string ...$args Command and arguments.
	 * @return array<string, mixed>
	 */
	protected static function fixture( string ...$args ): array {
		$plugin  = dirname( __DIR__, 3 );
		$www     = dirname( $plugin, 3 );
		$command = getenv( 'LML_E2E_FIXTURE' );
		if ( ! $command ) {
			$command = 'docker compose -f ' . escapeshellarg( dirname( $www ) . '/docker-compose.yml' )
				. ' exec -T webserver php ' . escapeshellarg( '/var/www/html' . substr( $plugin, strlen( $www ) ) . '/tests/E2E/fixture.php' );
		}

		exec( $command . ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) ) . ' 2>&1', $output, $code );
		$json = json_decode( implode( "\n", $output ), true );
		if ( 0 !== $code || ! is_array( $json ) ) {
			throw new \RuntimeException( 'Fixture "' . $args[0] . '" failed: ' . implode( "\n", $output ) );
		}

		return $json;
	}

	/**
	 * Site URL.
	 *
	 * @return string
	 */
	protected static function base_url(): string {
		return rtrim( getenv( 'LML_E2E_BASE_URL' ) ? (string) getenv( 'LML_E2E_BASE_URL' ) : 'http://localhost:85', '/' );
	}

	/**
	 * A logged-out client.
	 *
	 * @return Http
	 */
	protected function visitor(): Http {
		return new Http( self::base_url() );
	}

	/**
	 * A client logged in as the fixture administrator.
	 *
	 * @return Http
	 */
	protected function admin(): Http {
		return $this->logged_in( 'admin' );
	}

	/**
	 * A client logged in as the fixture subscriber.
	 *
	 * @return Http
	 */
	protected function subscriber(): Http {
		return $this->logged_in( 'subscriber' );
	}

	/**
	 * A client logged in as a fixture user.
	 *
	 * @param string $who "admin" or "subscriber".
	 * @return Http
	 */
	private function logged_in( string $who ): Http {
		if ( ! isset( self::$clients[ $who ] ) ) {
			$http = new Http( self::base_url() );
			$this->assertTrue( $http->login( self::$fixture[ $who ]['login'], self::$fixture[ $who ]['password'] ), "Login as {$who} failed." );
			self::$clients[ $who ] = $http;
		}

		return self::$clients[ $who ];
	}

	/**
	 * URL of a fixture page.
	 *
	 * @param string $name Page key.
	 * @return string
	 */
	protected function page( string $name ): string {
		return self::$fixture['pages'][ $name ]['url'];
	}

	/**
	 * Outer HTML of the elements carrying a class.
	 *
	 * @param string $html       Page HTML.
	 * @param string $class_name Class name.
	 * @return list<string>
	 */
	protected function elements( string $html, string $class_name ): array {
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$nodes = ( new \DOMXPath( $dom ) )->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class_name} ')]" );
		$found = array();
		foreach ( $nodes ? $nodes : array() as $node ) {
			$found[] = (string) $dom->saveHTML( $node );
		}

		return $found;
	}

	/**
	 * Value of the first input with a given name.
	 *
	 * @param string $html Page HTML.
	 * @param string $name Input name.
	 * @return string|null
	 */
	protected function input_value( string $html, string $name ): ?string {
		$pattern = '/<input[^>]*name="' . preg_quote( $name, '/' ) . '"[^>]*>/';
		if ( ! preg_match( $pattern, $html, $input ) || ! preg_match( '/value="([^"]*)"/', $input[0], $value ) ) {
			return null;
		}

		return html_entity_decode( $value[1], ENT_QUOTES );
	}

	/**
	 * A JavaScript object printed by wp_localize_script().
	 *
	 * @param string $html Page HTML.
	 * @param string $name Variable name.
	 * @return array<string, mixed>
	 */
	protected function localized( string $html, string $name ): array {
		$this->assertMatchesRegularExpression( '/var ' . preg_quote( $name, '/' ) . ' = (\{.*?\});/', $html, "{$name} is not printed." );
		preg_match( '/var ' . preg_quote( $name, '/' ) . ' = (\{.*?\});/', $html, $match );

		return json_decode( $match[1], true );
	}

	/**
	 * Submit the public form through admin-ajax.php.
	 *
	 * @param Http                 $http   Client.
	 * @param array<string, mixed> $fields Fields besides action and nonce.
	 * @param string|null          $nonce  Nonce (default: read from the single-box page).
	 * @return Response
	 */
	protected function submit( Http $http, array $fields, ?string $nonce = null ): Response {
		if ( null === $nonce ) {
			$nonce = (string) $this->localized( $http->get( $this->page( 'single' ) )->body, 'lead_magnet_ajax' )['nonce'];
		}

		return $http->post( '/wp-admin/admin-ajax.php', array( 'action' => 'leadmalo_submit_email', 'nonce' => $nonce ) + $fields );
	}

	/**
	 * Compare a value with its stored snapshot (or store it on the first run).
	 *
	 * Volatile parts (nonces, times, generated ids, test rows) are normalised first.
	 *
	 * @param string $name  Snapshot name.
	 * @param string $value Value.
	 */
	protected function assertMatchesSnapshot( string $name, string $value ): void {
		$value = $this->normalise( $value );
		$dir   = getenv( 'LML_E2E_SNAPSHOTS' ) ? (string) getenv( 'LML_E2E_SNAPSHOTS' ) : dirname( __DIR__ ) . '/__snapshots__';
		$file  = $dir . '/' . $name . '.txt';

		if ( '1' === getenv( 'LML_E2E_UPDATE_SNAPSHOTS' ) || ! is_file( $file ) ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0700, true );
			}
			file_put_contents( $file, $value );
			fwrite( STDERR, "\n[snapshot written] {$file}\n" );
			$this->addToAssertionCount( 1 );
			return;
		}

		$this->assertSame( file_get_contents( $file ), $value, "Snapshot {$name} changed." );
	}

	/**
	 * Strip volatile parts from HTML or CSV.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function normalise( string $value ): string {
		$value = (string) preg_replace( '/\s+/', ' ', $value );
		$value = (string) preg_replace( '/<tr id="record-\d+">(?:(?!<\/tr>).)*lml-e2e\.test(?:(?!<\/tr>).)*<\/tr>/', '', $value );
		$value = (string) preg_replace( '/\b[0-9a-f]{10}\b/', '{NONCE}', $value );
		$value = (string) preg_replace( '/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', '{DATETIME}', $value );
		$value = (string) preg_replace( '/lead-magnet-[0-9a-f]{13}/', 'lead-magnet-{UNIQID}', $value );
		$value = (string) preg_replace( '/ver=[^"&\'\s]+/', 'ver={VER}', $value );

		return trim( str_replace( '> <', ">\n<", $value ) ) . "\n";
	}
}
