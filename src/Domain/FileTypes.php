<?php
/**
 * File types a lead magnet may have.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Single source of truth for allowed extensions and their MIME types.
 */
final class FileTypes {

	/**
	 * Allowed extensions (in display order) and the MIME type they are served with.
	 */
	public const MIME_TYPES = array(
		'pdf'  => 'application/pdf',
		'zip'  => 'application/zip',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'ppt'  => 'application/vnd.ms-powerpoint',
		'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'txt'  => 'text/plain',
		'mp3'  => 'audio/mpeg',
		'mp4'  => 'video/mp4',
	);

	/**
	 * Fallback for files added outside the uploader (e.g. via FTP).
	 */
	private const DEFAULT_MIME_TYPE = 'application/octet-stream';

	/**
	 * Allowed extensions, lower case, without the dot.
	 *
	 * @return list<string>
	 */
	public static function extensions(): array {
		return array_keys( self::MIME_TYPES );
	}

	/**
	 * Whether a file name has an allowed extension.
	 *
	 * @param string $file_name File name.
	 * @return bool
	 */
	public static function is_allowed( string $file_name ): bool {
		return isset( self::MIME_TYPES[ self::extension( $file_name ) ] );
	}

	/**
	 * MIME type a file is served with.
	 *
	 * @param string $file_name File name or path.
	 * @return string
	 */
	public static function mime_type( string $file_name ): string {
		return self::MIME_TYPES[ self::extension( $file_name ) ] ?? self::DEFAULT_MIME_TYPE;
	}

	/**
	 * Lower-case extension of a file name.
	 *
	 * @param string $file_name File name or path.
	 * @return string
	 */
	private static function extension( string $file_name ): string {
		return strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
	}
}
