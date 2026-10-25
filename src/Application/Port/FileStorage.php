<?php
/**
 * Port: where the lead magnet files are kept.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application\Port;

use LeadMagnet\Locker\Application\Outcome;
use LeadMagnet\Locker\Application\UploadedFile;
use LeadMagnet\Locker\Domain\StoredFile;

/**
 * Protected file storage.
 */
interface FileStorage {

	/**
	 * Error code of store() when the upload is refused.
	 */
	public const REJECTED = 'rejected';

	/**
	 * Absolute path of a stored file.
	 *
	 * @param string $name File name.
	 * @return string|null Null when there is no such file (or the name escapes the storage).
	 */
	public function path( string $name ): ?string;

	/**
	 * Stored files, sorted by name.
	 *
	 * @return list<StoredFile>
	 */
	public function stored_files(): array;

	/**
	 * Opaque token the public form sends instead of the file name.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public function token( string $name ): string;

	/**
	 * File name for a form token.
	 *
	 * @param string $token Token.
	 * @return string|null
	 */
	public function name_from_token( string $token ): ?string;

	/**
	 * Whether the pre-1.3.0 "file" form field may still be accepted.
	 *
	 * @return bool
	 */
	public function accepts_legacy_field(): bool;

	/**
	 * Names of files still outside the protected storage.
	 *
	 * @return list<string>
	 */
	public function unprotected_files(): array;

	/**
	 * Move unprotected files into the protected storage.
	 *
	 * @return list<string> Names of the files that could not be moved.
	 */
	public function migrate(): array;

	/**
	 * Store an uploaded file.
	 *
	 * @param UploadedFile $file Validated upload.
	 * @return Outcome Success with data "name" (stored name), or REJECTED with data "message".
	 */
	public function store( UploadedFile $file ): Outcome;

	/**
	 * Delete a stored file.
	 *
	 * @param string $name File name.
	 * @return bool
	 */
	public function delete( string $name ): bool;
}
