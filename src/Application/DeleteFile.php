<?php
/**
 * Use case: an administrator deletes a lead magnet file.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Application\Port\DownloadRecords;
use LeadMagnet\Locker\Application\Port\FileStorage;

/**
 * Deletes a file unless download records refer to it.
 */
final class DeleteFile {

	public const INVALID_NAME = 'invalid_name';
	public const NOT_FOUND    = 'not_found';
	public const IN_USE       = 'in_use';
	public const FAILED       = 'failed';

	/**
	 * Constructor.
	 *
	 * @param FileStorage     $files   File storage.
	 * @param DownloadRecords $records Download records.
	 */
	public function __construct(
		private FileStorage $files,
		private DownloadRecords $records
	) {}

	/**
	 * Run the use case.
	 *
	 * @param string $name File name (sanitised).
	 * @return Outcome Success with data "name"; IN_USE with "count".
	 */
	public function execute( string $name ): Outcome {
		if ( '' === $name ) {
			return Outcome::failure( self::INVALID_NAME );
		}

		if ( null === $this->files->path( $name ) ) {
			return Outcome::failure( self::NOT_FOUND );
		}

		$count = $this->records->count_for_file( $name );
		if ( $count > 0 ) {
			return Outcome::failure( self::IN_USE, array( 'count' => $count ) );
		}

		return $this->files->delete( $name ) ? Outcome::success( array( 'name' => $name ) ) : Outcome::failure( self::FAILED );
	}
}
