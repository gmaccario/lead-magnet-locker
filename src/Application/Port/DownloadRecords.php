<?php
/**
 * Port: where download records are kept.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application\Port;

use LeadMagnet\Locker\Domain\DownloadRecord;
use LeadMagnet\Locker\Domain\DownloadStatistics;

/**
 * Download record storage.
 */
interface DownloadRecords {

	/**
	 * Store a submission.
	 *
	 * @param string $email        Email address.
	 * @param string $file_name    File name.
	 * @param string $download_key Download key.
	 * @return bool False on a storage error.
	 */
	public function create( string $email, string $file_name, string $download_key ): bool;

	/**
	 * Record by download key.
	 *
	 * @param string $download_key Download key.
	 * @return DownloadRecord|null
	 */
	public function find_by_key( string $download_key ): ?DownloadRecord;

	/**
	 * Record by ID.
	 *
	 * @param int $id Record ID.
	 * @return DownloadRecord|null
	 */
	public function find( int $id ): ?DownloadRecord;

	/**
	 * Mark a record as downloaded now.
	 *
	 * @param int $id Record ID.
	 * @return void
	 */
	public function mark_downloaded( int $id ): void;

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool False on a storage error (a missing record is not an error).
	 */
	public function delete( int $id ): bool;

	/**
	 * Number of records for a file.
	 *
	 * @param string $file_name File name.
	 * @return int
	 */
	public function count_for_file( string $file_name ): int;

	/**
	 * Dashboard statistics.
	 *
	 * @return DownloadStatistics
	 */
	public function statistics(): DownloadStatistics;

	/**
	 * Number of records matching a search ("" matches all).
	 *
	 * @param string $search Searched in email and file name, literally.
	 * @return int
	 */
	public function count( string $search = '' ): int;

	/**
	 * Records matching a search, newest first.
	 *
	 * @param string   $search Searched in email and file name, literally ("" matches all).
	 * @param int|null $limit  Page size, or null for all records.
	 * @param int      $offset Offset.
	 * @return list<DownloadRecord>
	 */
	public function search( string $search = '', ?int $limit = null, int $offset = 0 ): array;
}
