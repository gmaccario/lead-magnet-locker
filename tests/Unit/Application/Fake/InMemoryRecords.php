<?php
/**
 * In-memory DownloadRecords for unit tests.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Application\Fake;

use LeadMagnet\Locker\Application\Port\DownloadRecords;
use LeadMagnet\Locker\Domain\DownloadRecord;
use LeadMagnet\Locker\Domain\DownloadStatistics;

/**
 * Fake download records.
 */
final class InMemoryRecords implements DownloadRecords {

	/**
	 * Records by ID.
	 *
	 * @var array<int, DownloadRecord>
	 */
	public array $records = array();

	/**
	 * Whether create() fails.
	 *
	 * @var bool
	 */
	public bool $fail = false;

	public function create( string $email, string $file_name, string $download_key ): bool {
		if ( $this->fail ) {
			return false;
		}
		$id                   = count( $this->records ) + 1;
		$this->records[ $id ] = new DownloadRecord( $id, $email, $file_name, $download_key, '2026-01-01 00:00:00', null );

		return true;
	}

	public function find_by_key( string $download_key ): ?DownloadRecord {
		foreach ( $this->records as $record ) {
			if ( $record->download_key === $download_key ) {
				return $record;
			}
		}

		return null;
	}

	public function find( int $id ): ?DownloadRecord {
		return $this->records[ $id ] ?? null;
	}

	public function mark_downloaded( int $id ): void {
		$r                    = $this->records[ $id ];
		$this->records[ $id ] = new DownloadRecord( $r->id, $r->email, $r->file_name, $r->download_key, $r->created_at, '2026-01-02 00:00:00' );
	}

	public function delete( int $id ): bool {
		unset( $this->records[ $id ] );

		return true;
	}

	public function count_for_file( string $file_name ): int {
		return count( array_filter( $this->records, static fn( DownloadRecord $r ): bool => $r->file_name === $file_name ) );
	}

	public function statistics(): DownloadStatistics {
		return new DownloadStatistics( count( $this->records ), 0, 0 );
	}

	public function count( string $search = '' ): int {
		return count( $this->search( $search ) );
	}

	public function search( string $search = '', ?int $limit = null, int $offset = 0 ): array {
		return array_values( $this->records );
	}
}
