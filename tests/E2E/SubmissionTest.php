<?php
/**
 * Email submission (admin-ajax.php, action leadmalo_submit_email).
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * Submission tests.
 */
final class SubmissionTest extends TestCase {

	public function test_a_valid_submission_returns_a_download_link_and_stores_a_record(): void {
		$email    = 'token' . self::EMAIL;
		$response = $this->submit( $this->visitor(), array( 'email' => $email, 'lmf' => self::$fixture['tokens']['a'], 'file' => '' ) );

		$this->assertSame( 200, $response->status );
		$json = $response->json();
		$this->assertTrue( $json['success'] );
		$this->assertSame( 'Success! Your download is ready.', $json['data']['message'] );
		$this->assertMatchesRegularExpression(
			'#^' . preg_quote( self::base_url(), '#' ) . '/\?lead_magnet_download=[A-Za-z0-9]{32}&lmd-nonce=[0-9a-f]{10}$#',
			$json['data']['download_url']
		);

		$records = self::fixture( 'records', $email );
		$this->assertCount( 1, $records );
		$this->assertSame( self::PREFIX . 'a.pdf', $records[0]['file_name'] );
		$this->assertNull( $records[0]['downloaded_at'] );
		$this->assertStringContainsString( $records[0]['download_key'], $json['data']['download_url'] );
	}

	public function test_the_legacy_file_field_works_while_the_upgrade_window_is_open(): void {
		self::fixture( 'option', 'leadmalo_legacy_field_until', (string) ( time() + 3600 ) );

		$json = $this->submit( $this->visitor(), array( 'email' => 'legacy' . self::EMAIL, 'file' => self::PREFIX . 'b.txt' ) )->json();

		$this->assertTrue( $json['success'] );
		$this->assertSame( self::PREFIX . 'b.txt', self::fixture( 'records', 'legacy' . self::EMAIL )[0]['file_name'] );
	}

	public function test_the_legacy_file_field_is_refused_once_the_upgrade_window_has_closed(): void {
		self::fixture( 'option', 'leadmalo_legacy_field_until', (string) ( time() - 1 ) );
		$closed = $this->submit( $this->visitor(), array( 'email' => 'closed' . self::EMAIL, 'file' => self::PREFIX . 'b.txt' ) )->json();

		self::fixture( 'option', 'leadmalo_legacy_field_until', '__delete__' );
		$never = $this->submit( $this->visitor(), array( 'email' => 'never' . self::EMAIL, 'file' => self::PREFIX . 'b.txt' ) )->json();

		foreach ( array( $closed, $never ) as $json ) {
			$this->assertFalse( $json['success'] );
			$this->assertSame( 'File not found.', $json['data']['message'] );
		}
		$this->assertSame( array(), self::fixture( 'records', 'closed' . self::EMAIL ) );
	}

	/**
	 * @dataProvider invalid_submissions
	 *
	 * @param array<string, string> $fields  Fields.
	 * @param string                $message Expected error.
	 */
	public function test_invalid_submissions_are_rejected( array $fields, string $message ): void {
		self::fixture( 'option', 'leadmalo_legacy_field_until', (string) ( time() + 3600 ) );

		$json = $this->submit( $this->visitor(), $fields )->json();

		$this->assertFalse( $json['success'] );
		$this->assertSame( $message, $json['data']['message'] );
		$this->assertSame( array(), self::fixture( 'records', $fields['email'] ) );
	}

	/**
	 * @return array<string, array{array<string, string>, string}>
	 */
	public static function invalid_submissions(): array {
		return array(
			'invalid email'            => array( array( 'email' => 'not-an-email', 'lmf' => '' ), 'Please enter a valid email address.' ),
			'unknown token'            => array( array( 'email' => 'unknown' . self::EMAIL, 'lmf' => str_repeat( 'a', 32 ) ), 'File not found.' ),
			'malformed token'          => array( array( 'email' => 'malformed' . self::EMAIL, 'lmf' => 'x' ), 'File not found.' ),
			'bad token, file ignored'  => array( array( 'email' => 'ignored' . self::EMAIL, 'lmf' => str_repeat( 'b', 32 ), 'file' => self::PREFIX . 'a.pdf' ), 'File not found.' ),
			'nothing to download'      => array( array( 'email' => 'nothing' . self::EMAIL ), 'File not found.' ),
			'path traversal'           => array( array( 'email' => 'traversal' . self::EMAIL, 'file' => '../../../wp-config.php' ), 'File not found.' ),
			'protection file'          => array( array( 'email' => 'htaccess' . self::EMAIL, 'file' => '.htaccess' ), 'File not found.' ),
		);
	}

	public function test_a_bad_nonce_is_rejected(): void {
		$response = $this->submit( $this->visitor(), array( 'email' => 'nonce' . self::EMAIL, 'lmf' => self::$fixture['tokens']['a'] ), 'deadbeef00' );

		$this->assertStringContainsString( 'Security check failed', $response->body );
		$this->assertSame( array(), self::fixture( 'records', 'nonce' . self::EMAIL ) );
	}

	public function test_logged_in_users_can_submit_too(): void {
		$admin = $this->admin();
		$json  = $this->submit( $admin, array( 'email' => 'admin-submit' . self::EMAIL, 'lmf' => self::$fixture['tokens']['b'] ) )->json();

		$this->assertTrue( $json['success'] );
	}
}
