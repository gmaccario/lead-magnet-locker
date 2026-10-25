<?php
/**
 * Public downloads (?lead_magnet_download=…) and direct file access.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\E2E;

use LeadMagnet\Locker\Tests\E2E\Support\TestCase;

/**
 * Download tests.
 */
final class DownloadTest extends TestCase {

	public function test_the_link_from_a_submission_downloads_the_file_and_marks_the_record(): void {
		$visitor = $this->visitor();
		$email   = 'download' . self::EMAIL;
		$url     = $this->submit( $visitor, array( 'email' => $email, 'lmf' => self::$fixture['tokens']['a'] ) )->json()['data']['download_url'];

		$response = $visitor->get( $url );

		$this->assertSame( 200, $response->status );
		$this->assertSame( 'application/pdf', $response->header( 'content-type' ) );
		$this->assertStringStartsWith( 'attachment; filename="' . self::PREFIX . 'a.pdf"', (string) $response->header( 'content-disposition' ) );
		$this->assertSame( (string) strlen( self::$fixture['files'][ self::PREFIX . 'a.pdf' ] ), $response->header( 'content-length' ) );
		$this->assertSame( self::$fixture['files'][ self::PREFIX . 'a.pdf' ], $response->body );
		$this->assertNotNull( self::fixture( 'records', $email )[0]['downloaded_at'] );
	}

	public function test_text_files_are_served_as_text(): void {
		$record   = self::fixture( 'insert-record', 'text' . self::EMAIL, self::PREFIX . 'b.txt' );
		$response = $this->visitor()->get( '/?lead_magnet_download=' . $record['key'] . '&lmd-nonce=' . $record['nonce'] );

		$this->assertSame( 200, $response->status );
		$this->assertStringStartsWith( 'text/plain', (string) $response->header( 'content-type' ) );
		$this->assertSame( self::$fixture['files'][ self::PREFIX . 'b.txt' ], $response->body );
	}

	public function test_a_bad_nonce_is_forbidden(): void {
		$record   = self::fixture( 'insert-record', 'forbidden' . self::EMAIL, self::PREFIX . 'a.pdf' );
		$response = $this->visitor()->get( '/?lead_magnet_download=' . $record['key'] . '&lmd-nonce=deadbeef00' );

		$this->assertSame( 403, $response->status );
		$this->assertSame( 'Forbidden', $response->body );
		$this->assertNull( self::fixture( 'record', $record['key'] )['record']['downloaded_at'] );
	}

	public function test_an_unknown_key_is_rejected(): void {
		$key      = str_repeat( 'Z', 32 );
		$nonce    = self::fixture( 'download-nonce', $key )['nonce'];
		$response = $this->visitor()->get( '/?lead_magnet_download=' . $key . '&lmd-nonce=' . $nonce );

		$this->assertStringContainsString( 'Invalid download link.', $response->body );
		$this->assertNotSame( 200, $response->status );
	}

	public function test_a_record_whose_file_is_gone_is_rejected(): void {
		$record   = self::fixture( 'insert-record', 'gone' . self::EMAIL, self::PREFIX . 'gone.pdf' );
		$response = $this->visitor()->get( '/?lead_magnet_download=' . $record['key'] . '&lmd-nonce=' . $record['nonce'] );

		$this->assertStringContainsString( 'File not found.', $response->body );
		$this->assertNotSame( 200, $response->status );
	}

	public function test_files_cannot_be_fetched_by_direct_url(): void {
		$visitor = $this->visitor();

		$this->assertNotSame( 200, $visitor->get( self::$fixture['private'] . '/' . self::PREFIX . 'a.pdf' )->status );

		self::fixture( 'put-file', 'legacy', self::PREFIX . 'direct.txt', 'secret' );
		$this->assertNotSame( 200, $visitor->get( self::$fixture['legacy'] . '/' . self::PREFIX . 'direct.txt' )->status );

		$listing = $visitor->get( self::$fixture['private'] . '/' );
		$this->assertStringNotContainsString( self::PREFIX . 'a.pdf', $listing->body );
	}
}
