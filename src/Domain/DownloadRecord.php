<?php
/**
 * One email submission and its download.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Download record entity.
 */
final class DownloadRecord {

	/**
	 * Constructor.
	 *
	 * @param int         $id            Record ID.
	 * @param string      $email         Email address.
	 * @param string      $file_name     File name.
	 * @param string      $download_key  Download key.
	 * @param string      $created_at    Submission time (site time, Y-m-d H:i:s).
	 * @param string|null $downloaded_at Download time (site time), or null when not downloaded yet.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $email,
		public readonly string $file_name,
		public readonly string $download_key,
		public readonly string $created_at,
		public readonly ?string $downloaded_at
	) {}

	/**
	 * Build from a database row.
	 *
	 * @param array<string, mixed> $row Row as returned by wpdb (ARRAY_A).
	 * @return self
	 */
	public static function from_row( array $row ): self {
		$string = static fn( string $key ): string => is_scalar( $row[ $key ] ?? null ) ? (string) $row[ $key ] : '';

		return new self(
			(int) $string( 'id' ),
			$string( 'email' ),
			$string( 'file_name' ),
			$string( 'download_key' ),
			$string( 'created_at' ),
			isset( $row['downloaded_at'] ) && is_scalar( $row['downloaded_at'] ) ? (string) $row['downloaded_at'] : null
		);
	}

	/**
	 * Whether the visitor downloaded the file.
	 *
	 * @return bool
	 */
	public function is_downloaded(): bool {
		return null !== $this->downloaded_at && '' !== $this->downloaded_at;
	}
}
