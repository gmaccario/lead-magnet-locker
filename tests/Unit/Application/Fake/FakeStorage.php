<?php
/**
 * In-memory FileStorage for unit tests.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Application\Fake;

use LeadMagnet\Locker\Application\Outcome;
use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Application\UploadedFile;
use LeadMagnet\Locker\Domain\StoredFile;

/**
 * Fake file storage: files are names with sizes.
 */
final class FakeStorage implements FileStorage {

	/**
	 * Protected files: name => size.
	 *
	 * @var array<string, int>
	 */
	public array $files = array();

	/**
	 * Files outside the protected storage.
	 *
	 * @var list<string>
	 */
	public array $unprotected = array();

	/**
	 * Result store() returns.
	 *
	 * @var Outcome|null
	 */
	public ?Outcome $store_result = null;

	/**
	 * Uploads passed to store().
	 *
	 * @var list<UploadedFile>
	 */
	public array $stored = array();

	/**
	 * Whether delete() fails.
	 *
	 * @var bool
	 */
	public bool $delete_fails = false;

	/**
	 * Whether the legacy field is accepted.
	 *
	 * @var bool
	 */
	public bool $legacy = false;

	public function path( string $name ): ?string {
		return isset( $this->files[ $name ] ) ? '/storage/' . $name : null;
	}

	public function stored_files(): array {
		$list = array();
		foreach ( $this->files as $name => $size ) {
			$list[] = new StoredFile( $name, $size, 0 );
		}

		return $list;
	}

	public function token( string $name ): string {
		return md5( $name );
	}

	public function name_from_token( string $token ): ?string {
		foreach ( array_keys( $this->files ) as $name ) {
			if ( md5( $name ) === $token ) {
				return $name;
			}
		}

		return null;
	}

	public function accepts_legacy_field(): bool {
		return $this->legacy;
	}

	public function unprotected_files(): array {
		return $this->unprotected;
	}

	public function migrate(): array {
		foreach ( $this->unprotected as $name ) {
			$this->files[ $name ] = 1;
		}
		$this->unprotected = array();

		return array();
	}

	public function store( UploadedFile $file ): Outcome {
		$this->stored[] = $file;

		return $this->store_result ?? Outcome::success( array( 'name' => $file->name ) );
	}

	public function delete( string $name ): bool {
		if ( $this->delete_fails ) {
			return false;
		}
		unset( $this->files[ $name ] );

		return true;
	}
}
