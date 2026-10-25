<?php
/**
 * Use case: find the file behind a download.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Application\Port\DownloadRecords;
use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Domain\DownloadRecord;

/**
 * Resolves a record to a file path; visitor downloads count as completed.
 */
final class ResolveDownload {

	public const RECORD_NOT_FOUND = 'record_not_found';
	public const FILE_NOT_FOUND   = 'file_not_found';

	/**
	 * Constructor.
	 *
	 * @param DownloadRecords $records Download records.
	 * @param FileStorage     $files   File storage.
	 */
	public function __construct(
		private DownloadRecords $records,
		private FileStorage $files
	) {}

	/**
	 * A visitor's download link: marks the record as downloaded.
	 *
	 * @param string $download_key Download key.
	 * @return Outcome Success with data "path".
	 */
	public function for_visitor( string $download_key ): Outcome {
		$record  = $this->records->find_by_key( $download_key );
		$outcome = $this->resolve( $record );
		if ( null !== $record && $outcome->is_success() ) {
			$this->records->mark_downloaded( $record->id );
		}

		return $outcome;
	}

	/**
	 * An administrator's download from the history: leaves the statistics alone.
	 *
	 * @param int $record_id Record ID.
	 * @return Outcome Success with data "path".
	 */
	public function for_admin( int $record_id ): Outcome {
		return $this->resolve( $this->records->find( $record_id ) );
	}

	/**
	 * Path of a record's file.
	 *
	 * @param DownloadRecord|null $record Record.
	 * @return Outcome
	 */
	private function resolve( ?DownloadRecord $record ): Outcome {
		if ( null === $record ) {
			return Outcome::failure( self::RECORD_NOT_FOUND );
		}

		$path = $this->files->path( $record->file_name );

		return null !== $path ? Outcome::success( array( 'path' => $path ) ) : Outcome::failure( self::FILE_NOT_FOUND );
	}
}
