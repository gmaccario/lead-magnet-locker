<?php
/**
 * Use case: an administrator uploads a lead magnet file.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Application\Port\FileStorage;
use LeadMagnet\Locker\Domain\FileTypes;

/**
 * Validates an upload, then stores it.
 */
final class UploadFile {

	public const UPLOAD_ERROR     = 'upload_error';
	public const INVALID_PAYLOAD  = 'invalid_payload';
	public const TYPE_NOT_ALLOWED = 'type_not_allowed';
	public const TOO_LARGE        = 'too_large';
	public const REJECTED         = 'rejected';

	/**
	 * Constructor.
	 *
	 * @param FileStorage $files File storage.
	 */
	public function __construct( private FileStorage $files ) {}

	/**
	 * Run the use case.
	 *
	 * @param UploadedFile $file     Upload.
	 * @param int          $max_size Maximum size in bytes.
	 * @return Outcome Success with data "name"; UPLOAD_ERROR with "code"; TOO_LARGE with "max_size"; REJECTED with "message".
	 */
	public function execute( UploadedFile $file, int $max_size ): Outcome {
		if ( UPLOAD_ERR_OK !== $file->error_code ) {
			return Outcome::failure( self::UPLOAD_ERROR, array( 'code' => $file->error_code ) );
		}

		if ( '' === $file->name || '' === $file->tmp_path || ! $file->is_uploaded ) {
			return Outcome::failure( self::INVALID_PAYLOAD );
		}

		if ( ! FileTypes::is_allowed( $file->name ) ) {
			return Outcome::failure( self::TYPE_NOT_ALLOWED );
		}

		if ( $file->size > $max_size ) {
			return Outcome::failure( self::TOO_LARGE, array( 'max_size' => $max_size ) );
		}

		$stored = $this->files->store( $file );

		return $stored->is_success()
			? Outcome::success( array( 'name' => $stored->string( 'name' ) ) )
			: Outcome::failure( self::REJECTED, array( 'message' => $stored->string( 'message' ) ) );
	}
}
