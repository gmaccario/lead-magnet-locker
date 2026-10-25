<?php
/**
 * File upload and delete from the admin (dashboard form and AJAX).
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Presentation;

use LeadMagnet\Locker\Application\DeleteFile;
use LeadMagnet\Locker\Application\Outcome;
use LeadMagnet\Locker\Application\UploadedFile;
use LeadMagnet\Locker\Application\UploadFile;
use LeadMagnet\Locker\Domain\ByteSize;
use LeadMagnet\Locker\Domain\FileTypes;

/**
 * Reads upload/delete requests, runs the use cases and words the result.
 */
final class FileActions {

	/**
	 * Default maximum upload size (filterable with lead_magnet_max_file_size).
	 */
	private const MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Constructor.
	 *
	 * @param UploadFile $upload Use case.
	 * @param DeleteFile $delete Use case.
	 */
	public function __construct(
		private UploadFile $upload,
		private DeleteFile $delete
	) {}

	/**
	 * AJAX: upload a file (action lead_magnet_upload_file).
	 *
	 * @return void
	 */
	public function ajax_upload(): void {
		check_ajax_referer( 'lead_magnet_upload', 'upload_nonce' );

		$this->send_json( $this->upload_from_request() );
	}

	/**
	 * AJAX: delete a file (action lead_magnet_delete_file).
	 *
	 * @return void
	 */
	public function ajax_delete(): void {
		check_ajax_referer( 'lead_magnet_delete', 'delete_nonce' );

		$this->send_json( $this->delete( isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '' ) );
	}

	/**
	 * Store the uploaded "lead_magnet_file". The caller verifies the nonce.
	 *
	 * @return Result
	 */
	public function upload_from_request(): Result {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Result::failure( __( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller.
		if ( ! isset( $_FILES['lead_magnet_file'] ) || ! is_array( $_FILES['lead_magnet_file'] ) ) {
			return Result::failure( __( 'No file payload received.', 'lead-magnet-locker' ) );
		}

		// Each value is checked or sanitised below; WordPress validates the file itself when storing it.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['lead_magnet_file'];
		// phpcs:enable
		$tmp_path = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		$upload = new UploadedFile(
			sanitize_file_name( isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '' ),
			$tmp_path,
			isset( $file['size'] ) ? (int) $file['size'] : 0,
			isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE,
			'' !== $tmp_path && is_uploaded_file( $tmp_path ),
			isset( $file['type'] ) && is_string( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : ''
		);

		$outcome = $this->upload->execute( $upload, (int) apply_filters( 'lead_magnet_max_file_size', self::MAX_FILE_SIZE ) );

		return $outcome->is_success()
			? Result::success( __( 'File uploaded successfully: ', 'lead-magnet-locker' ) . $outcome->string( 'name' ) )
			: Result::failure( $this->upload_message( $outcome ) );
	}

	/**
	 * Delete a file. The caller verifies the nonce.
	 *
	 * @param string $name File name (sanitised).
	 * @return Result
	 */
	public function delete( string $name ): Result {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Result::failure( __( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		$outcome = $this->delete->execute( sanitize_file_name( $name ) );

		return match ( $outcome->error ) {
			null                     => Result::success( __( 'File deleted successfully: ', 'lead-magnet-locker' ) . $outcome->string( 'name' ) ),
			DeleteFile::INVALID_NAME => Result::failure( __( 'Invalid filename.', 'lead-magnet-locker' ) ),
			DeleteFile::NOT_FOUND    => Result::failure( __( 'File not found.', 'lead-magnet-locker' ) ),
			DeleteFile::IN_USE       => Result::failure(
				sprintf(
					/* translators: %d: number of downloads the file has been used in */
					__( 'Cannot delete file. It has been used in %d downloads.', 'lead-magnet-locker' ),
					$outcome->int( 'count' )
				)
			),
			default                  => Result::failure( __( 'Failed to delete file.', 'lead-magnet-locker' ) ),
		};
	}

	/**
	 * Admin-facing message for a failed upload.
	 *
	 * @param Outcome $outcome UploadFile result.
	 * @return string
	 */
	private function upload_message( Outcome $outcome ): string {
		return match ( $outcome->error ) {
			UploadFile::UPLOAD_ERROR     => __( 'Upload error: ', 'lead-magnet-locker' ) . $this->php_upload_error( $outcome->int( 'code' ) ),
			UploadFile::INVALID_PAYLOAD  => __( 'Invalid upload payload.', 'lead-magnet-locker' ),
			UploadFile::TYPE_NOT_ALLOWED => sprintf(
				/* translators: %s: list of allowed file extensions */
				__( 'File type not allowed. Allowed types: %s', 'lead-magnet-locker' ),
				implode( ', ', FileTypes::extensions() )
			),
			UploadFile::TOO_LARGE        => sprintf(
				/* translators: %s: maximum file size in human readable form, e.g. "10 MB" */
				__( 'File too large. Maximum size: %s', 'lead-magnet-locker' ),
				ByteSize::format( $outcome->int( 'max_size' ) )
			),
			default                      => '' !== $outcome->string( 'message' ) ? $outcome->string( 'message' ) : __( 'Upload failed.', 'lead-magnet-locker' ),
		};
	}

	/**
	 * Message for a PHP upload error code.
	 *
	 * @param int $code UPLOAD_ERR_* code.
	 * @return string
	 */
	private function php_upload_error( int $code ): string {
		return match ( $code ) {
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds upload_max_filesize directive in php.ini', 'lead-magnet-locker' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds MAX_FILE_SIZE directive in HTML form', 'lead-magnet-locker' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded', 'lead-magnet-locker' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded', 'lead-magnet-locker' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder', 'lead-magnet-locker' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk', 'lead-magnet-locker' ),
			UPLOAD_ERR_EXTENSION  => __( 'File upload stopped by extension', 'lead-magnet-locker' ),
			default               => __( 'Unknown upload error', 'lead-magnet-locker' ),
		};
	}

	/**
	 * Send a result in the JSON shape used since 1.0: data = {success, message}.
	 *
	 * @param Result $result Result.
	 * @return void
	 */
	private function send_json( Result $result ): void {
		if ( $result->success ) {
			wp_send_json_success( $result->to_array() );
		}
		wp_send_json_error( $result->to_array() );
	}
}
