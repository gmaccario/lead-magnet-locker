<?php
/**
 * A file received from the upload form.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

/**
 * Upload data, already read and sanitised from the request.
 */
final class UploadedFile {

	/**
	 * Constructor.
	 *
	 * @param string $name        Sanitised file name.
	 * @param string $tmp_path    Temporary path.
	 * @param int    $size        Size in bytes.
	 * @param int    $error_code  PHP UPLOAD_ERR_* code.
	 * @param bool   $is_uploaded Whether PHP confirms the file came from this upload.
	 * @param string $mime_type   MIME type sent by the browser (sanitised).
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $tmp_path,
		public readonly int $size,
		public readonly int $error_code,
		public readonly bool $is_uploaded,
		public readonly string $mime_type
	) {}
}
