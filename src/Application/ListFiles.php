<?php
/**
 * Use case: list the stored files for the dashboard.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Domain\StoredFile;

/**
 * Protects files added outside the uploader (e.g. via FTP), then lists all files.
 */
final class ListFiles {

	/**
	 * Constructor.
	 *
	 * @param FileStorage $files File storage.
	 */
	public function __construct( private FileStorage $files ) {}

	/**
	 * Run the use case.
	 *
	 * @return list<StoredFile>
	 */
	public function execute(): array {
		if ( $this->files->unprotected_files() ) {
			$this->files->migrate();
		}

		return $this->files->stored_files();
	}
}
