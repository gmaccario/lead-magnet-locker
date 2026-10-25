<?php
/**
 * A lead magnet file in storage.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Name, size and modification time of a stored file.
 */
final class StoredFile {

	/**
	 * Constructor.
	 *
	 * @param string $name     File name, as used in the shortcode.
	 * @param int    $size     Size in bytes.
	 * @param int    $modified Modification time (Unix timestamp).
	 */
	public function __construct(
		public readonly string $name,
		public readonly int $size,
		public readonly int $modified
	) {}
}
