<?php
/**
 * Small value objects: ByteSize, DownloadKey, DownloadRecord, Result.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Domain;

use LeadMagnet\Locker\Domain\ByteSize;
use LeadMagnet\Locker\Domain\DownloadRecord;
use LeadMagnet\Locker\Domain\DownloadKey;
use LeadMagnet\Locker\Presentation\Result;
use PHPUnit\Framework\TestCase;

/**
 * Value object tests.
 */
final class ValueObjectsTest extends TestCase {

	public function test_byte_sizes(): void {
		$this->assertSame( '0.00 B', ByteSize::format( 0 ) );
		$this->assertSame( '0.00 B', ByteSize::format( -5 ) );
		$this->assertSame( '512.00 B', ByteSize::format( 512 ) );
		$this->assertSame( '1.50 KB', ByteSize::format( 1536 ) );
		$this->assertSame( '10.00 MB', ByteSize::format( 10 * 1024 * 1024 ) );
		$this->assertSame( '100.00 MB', ByteSize::format( 104857600 ) );
	}

	public function test_download_keys_are_32_random_letters_and_digits(): void {
		$keys = array_map( static fn(): string => DownloadKey::generate(), range( 1, 50 ) );

		foreach ( $keys as $key ) {
			$this->assertMatchesRegularExpression( '/^[A-Za-z0-9]{32}$/', $key );
		}
		$this->assertCount( 50, array_unique( $keys ) );
	}

	public function test_results_keep_the_ajax_shape(): void {
		$this->assertSame( array( 'success' => true, 'message' => 'Done' ), Result::success( 'Done' )->to_array() );
		$this->assertSame( array( 'success' => false, 'message' => 'Nope' ), Result::failure( 'Nope' )->to_array() );
	}

	public function test_download_records_from_rows(): void {
		$record = DownloadRecord::from_row(
			array(
				'id'            => '42',
				'email'         => 'a@example.org',
				'file_name'     => 'guide.pdf',
				'download_key'  => 'key',
				'created_at'    => '2026-01-01 10:00:00',
				'downloaded_at' => null,
			)
		);

		$this->assertSame( 42, $record->id );
		$this->assertSame( 'guide.pdf', $record->file_name );
		$this->assertNull( $record->downloaded_at );
		$this->assertFalse( $record->is_downloaded() );
		$this->assertTrue( DownloadRecord::from_row( array( 'downloaded_at' => '2026-01-02 10:00:00' ) )->is_downloaded() );
	}
}
