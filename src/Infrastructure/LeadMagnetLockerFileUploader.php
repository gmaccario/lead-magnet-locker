<?php
/**
 * Lead Magnet Locker Uploader
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Domain\ByteSize;
use LeadMagnet\Locker\Domain\FileTypes;
use LeadMagnet\Locker\Domain\Result;

/**
 * File uploader service for Lead Magnet Locker.
 *
 * Handles secure uploads into the protected storage and deletions.
 */
class LeadMagnetLockerFileUploader {

	/**
	 * Default maximum upload size (filterable with lead_magnet_max_file_size).
	 */
	private const MAX_FILE_SIZE = 10 * 1024 * 1024;

	/**
	 * Constructor.
	 *
	 * @param LeadMagnetLockerStorage $storage   Protected file storage.
	 * @param DownloadRepository      $downloads Download records.
	 */
	public function __construct(
		private LeadMagnetLockerStorage $storage,
		private DownloadRepository $downloads
	) {}

	/**
	 * AJAX: upload a file (action lead_magnet_upload_file).
	 *
	 * @return void
	 */
	public function ajax_upload_file(): void {
		check_ajax_referer( 'lead_magnet_upload', 'upload_nonce' );

		$this->send_json( $this->handle_file_upload() );
	}

	/**
	 * AJAX: delete a file (action lead_magnet_delete_file).
	 *
	 * @return void
	 */
	public function ajax_delete_file(): void {
		check_ajax_referer( 'lead_magnet_delete', 'delete_nonce' );

		$filename = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : '';

		$this->send_json( $this->handle_file_deletion( $filename ) );
	}

	/**
	 * Store the uploaded "lead_magnet_file" in the protected storage.
	 *
	 * The caller verifies the nonce (form or AJAX).
	 *
	 * @return Result
	 */
	public function handle_file_upload(): Result {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Result::failure( __( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by the caller.
		if ( ! isset( $_FILES['lead_magnet_file'] ) || ! is_array( $_FILES['lead_magnet_file'] ) ) {
			return Result::failure( __( 'No file payload received.', 'lead-magnet-locker' ) );
		}

		// wp_handle_upload() below validates the name and type and moves the file.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
		$file = $_FILES['lead_magnet_file'];

		$error_code = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error_code ) {
			return Result::failure( __( 'Upload error: ', 'lead-magnet-locker' ) . $this->get_upload_error_message( $error_code ) );
		}

		$file_name = sanitize_file_name( isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '' );
		$file_size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$tmp_name  = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( '' === $file_name || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return Result::failure( __( 'Invalid upload payload.', 'lead-magnet-locker' ) );
		}

		if ( ! FileTypes::is_allowed( $file_name ) ) {
			return Result::failure(
				sprintf(
					/* translators: %s: list of allowed file extensions */
					__( 'File type not allowed. Allowed types: %s', 'lead-magnet-locker' ),
					implode( ', ', FileTypes::extensions() )
				)
			);
		}

		$max_size = (int) apply_filters( 'lead_magnet_max_file_size', self::MAX_FILE_SIZE );
		if ( $file_size > $max_size ) {
			return Result::failure(
				sprintf(
					/* translators: %s: maximum file size in human readable form, e.g. "10 MB" */
					__( 'File too large. Maximum size: %s', 'lead-magnet-locker' ),
					ByteSize::format( $max_size )
				)
			);
		}

		$this->storage->ensure_directories();
		$subdir = $this->storage->upload_subdir();

		// Point WordPress at the protected folder for this one upload.
		$filter = static function ( array $dirs ) use ( $subdir ): array {
			$dirs['path']   = $dirs['basedir'] . $subdir;
			$dirs['url']    = $dirs['baseurl'] . $subdir; // Never exposed, kept for consistency.
			$dirs['subdir'] = $subdir;
			return $dirs;
		};

		$upload = array(
			'name'     => $file_name,
			'type'     => isset( $file['type'] ) && is_string( $file['type'] ) ? sanitize_mime_type( $file['type'] ) : '',
			'tmp_name' => $tmp_name,
			'error'    => 0,
			'size'     => $file_size,
		);

		add_filter( 'upload_dir', $filter );
		try {
			$moved = wp_handle_upload(
				$upload,
				array(
					'test_form'                => false,
					'mimes'                    => $this->allowed_mimes(),
					'unique_filename_callback' => static function ( string $dir, string $name, string $ext ): string {
						$sanitized = sanitize_file_name( $name );

						// Do not append the extension twice.
						return wp_unique_filename( $dir, $ext && str_ends_with( $sanitized, $ext ) ? $sanitized : $sanitized . $ext );
					},
				)
			);
		} finally {
			remove_filter( 'upload_dir', $filter );
		}

		if ( ! empty( $moved['error'] ) ) {
			return Result::failure( (string) $moved['error'] );
		}
		if ( empty( $moved['file'] ) ) {
			return Result::failure( __( 'Upload failed.', 'lead-magnet-locker' ) );
		}

		return Result::success( __( 'File uploaded successfully: ', 'lead-magnet-locker' ) . wp_basename( (string) $moved['file'] ) );
	}

	/**
	 * Delete a file, unless download records refer to it.
	 *
	 * @param string $filename File name.
	 * @return Result
	 */
	public function handle_file_deletion( string $filename ): Result {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Result::failure( __( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		$filename = sanitize_file_name( $filename );
		if ( '' === $filename ) {
			return Result::failure( __( 'Invalid filename.', 'lead-magnet-locker' ) );
		}

		$file_path = $this->storage->path( $filename );
		if ( null === $file_path ) {
			return Result::failure( __( 'File not found.', 'lead-magnet-locker' ) );
		}

		$usage_count = $this->downloads->count_for_file( $filename );
		if ( $usage_count > 0 ) {
			return Result::failure(
				sprintf(
					/* translators: %d: number of downloads the file has been used in */
					__( 'Cannot delete file. It has been used in %d downloads.', 'lead-magnet-locker' ),
					$usage_count
				)
			);
		}

		if ( ! wp_delete_file( $file_path ) ) {
			return Result::failure( __( 'Failed to delete file.', 'lead-magnet-locker' ) );
		}

		return Result::success( __( 'File deleted successfully: ', 'lead-magnet-locker' ) . $filename );
	}

	/**
	 * WordPress MIME map restricted to the allowed extensions.
	 *
	 * @return array<string, string>
	 */
	private function allowed_mimes(): array {
		return array_filter(
			wp_get_mime_types(),
			static fn( string $mime, string $extensions ): bool => (bool) array_intersect( explode( '|', $extensions ), FileTypes::extensions() ),
			ARRAY_FILTER_USE_BOTH
		);
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

	/**
	 * Human-readable message for a PHP upload error code.
	 *
	 * @param int $error_code UPLOAD_ERR_* code.
	 * @return string
	 */
	private function get_upload_error_message( int $error_code ): string {
		return match ( $error_code ) {
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
}
