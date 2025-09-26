<?php
/**
 * Lead Magnet Locker Uploader
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

/**
 * File uploader service for Lead Magnet Locker.
 *
 * Handles secure uploads, deletions, directory hardening,
 * and basic file metadata formatting.
 */
class LeadMagnetLockerFileUploader {

	/**
	 * Fully-qualified downloads table name (with $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $download_table_name = '';

	/**
	 * Set the downloads table name used by this service.
	 *
	 * @param string $download_table_name Downloads table name (prefixed).
	 * @return static
	 */
	public function set_tables_name( string $download_table_name ): static {
		// Optional: validate/whitelist table name early.
		$this->download_table_name = $download_table_name;

		return $this;
	}

	/**
	 * AJAX handler for file upload
	 */
	public function ajax_upload_file(): void {
		// Verify nonce early and die on failure.
		// This also handles unslashing internally.
		check_ajax_referer( 'lead_magnet_upload', 'upload_nonce' );

		$result = $this->handle_file_upload();

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for file deletion
	 *
	 * @return void
	 */
	public function ajax_delete_file(): void {
		// Verify nonce BEFORE reading any $_POST fields.
		check_ajax_referer( 'lead_magnet_delete', 'delete_nonce' );

		$filename = '';
		if ( isset( $_POST['filename'] ) ) {
			$filename = sanitize_file_name( wp_unslash( $_POST['filename'] ) );
		}

		$result = $this->handle_file_deletion( $filename );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}


	/**
	 * Handle file upload
	 *
	 * @return array
	 */
	public function handle_file_upload(): array {
		// Verify nonce early and die on failure.
		// This also handles unslashing internally.
		check_ajax_referer( 'lead_magnet_upload', 'upload_nonce' );

		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ),
			);
		}

		// Validate $_FILES presence.
		if ( ! isset( $_FILES['lead_magnet_file'] ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'No file payload received.', 'lead-magnet-locker' ),
			);
		}

		// We validate the presence above; wp_handle_upload() performs filename/mime checks and moves the file securely.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $_FILES['lead_magnet_file'];

		// Validate indices before use.
		$error_code = isset( $file['error'] )
			? (int) $file['error']
			: UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error_code ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Upload error: ', 'lead-magnet-locker' ) . $this->get_upload_error_message( $error_code ),
			);
		}

		$raw_name  = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		$file_name = sanitize_file_name( $raw_name );
		$file_size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$tmp_name  = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( '' === $file_name || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid upload payload.', 'lead-magnet-locker' ),
			);
		}

		// Allowed types — map to mimes to let WP validate it in wp_handle_upload.
		$allowed_exts = array(
			'pdf',
			'zip',
			'doc',
			'docx',
			'xls',
			'xlsx',
			'ppt',
			'pptx',
			'jpg',
			'jpeg',
			'png',
			'gif',
			'txt',
			'mp3',
			'mp4',
		);
		$ext          = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: list of allowed file extensions */
					esc_html__( 'File type not allowed. Allowed types: %s', 'lead-magnet-locker' ),
					implode( ', ', $allowed_exts )
				),
			);
		}

		// Size check (10MB default).
		$max_size = (int) apply_filters( 'lead_magnet_max_file_size', 10 * 1024 * 1024 );
		if ( $file_size > $max_size ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: maximum file size in human readable form, e.g. "10 MB" */
					esc_html__( 'File too large. Maximum size: %s', 'lead-magnet-locker' ),
					$this->format_file_size( $max_size )
				),
			);
		}

		// Ensure the secure directory exists and is hardened.
		$this->create_secure_directory();

		// Temporarily override upload_dir to our secure subdirectory.
		/**
		 * Filter upload directories to store files in the secure subfolder.
		 *
		 * @param array $dirs Upload directory array.
		 * @return array      Modified directories array.
		 */
		$filter = function ( array $dirs ): array {
			$subdir         = '/lead-magnet-files';
			$dirs['path']   = $dirs['basedir'] . $subdir;
			$dirs['url']    = $dirs['baseurl'] . $subdir; // we won't expose the URL, but keep structure consistent.
			$dirs['subdir'] = $subdir;
			return $dirs;
		};
		add_filter( 'upload_dir', $filter );

		// Build a "sideload" style array and let WP move/validate it.
		// This avoids using move_uploaded_file() directly.
		$file_array = array(
			'name'     => $file_name,
			'type'     => isset( $file['type'] ) && is_string( $file['type'] )
				? sanitize_mime_type( $file['type'] )
				: '',
			'tmp_name' => $tmp_name,
			'error'    => 0,
			'size'     => $file_size,
		);

		// Constrain mimes to allow extensions.
		$mimes         = wp_get_mime_types();
		$allowed_mimes = array_filter(
			$mimes,
			function ( $mime, $extension ) use ( $allowed_exts ) {
				// $extension string may contain pipes; split.
				$exts = array_map( 'trim', explode( '|', (string) $extension ) );
				return (bool) array_intersect( $exts, $allowed_exts );
			},
			ARRAY_FILTER_USE_BOTH
		);

		// Duplication check.
		$overrides = array(
			'test_form'                => false,  // we're not using a standard form field.
			'mimes'                    => $allowed_mimes,
			'unique_filename_callback' => function ( $dir, $name, $ext ) {
				// Sanitize.
				$sanitized = sanitize_file_name( $name );

				// If $name already ends with $ext, don't append again.
				if ( $ext && str_ends_with( $sanitized, $ext ) ) {
					return wp_unique_filename( $dir, $sanitized );
				}

				return wp_unique_filename( $dir, $sanitized . $ext );
			},
		);

		// Let WP handle the move + filename uniqueness and mime checks.
		$moved = wp_handle_upload( $file_array, $overrides );

		// Validate result: ensure WordPress accepted the file with expected type/ext.
		if ( ! $moved || ! empty( $moved['error'] ) ) {
			if ( isset( $moved['error'] ) ) {
				return array(
					'success' => false,
					'message' => (string) $moved['error'],
				);
			}
			return array(
				'success' => false,
				'message' => esc_html__( 'Upload failed.', 'lead-magnet-locker' ),
			);
		}

		// Remove the filter ASAP.
		remove_filter( 'upload_dir', $filter );

		if ( isset( $moved['error'] ) ) {
			return array(
				'success' => false,
				'message' => (string) $moved['error'],
			);
		}

		// Final filename (basename) for returning to UI/logging.
		$stored_basename = isset( $moved['file'] ) ? wp_basename( $moved['file'] ) : $file_name;

		return array(
			'success' => true,
			'message' => esc_html__( 'File uploaded successfully: ', 'lead-magnet-locker' ) . $stored_basename,
		);
	}

	/**
	 * Handle file deletion
	 *
	 * @param string $filename Name of the file to delete.
	 * @return array Result array with success/message.
	 */
	public function handle_file_deletion( string $filename ): array {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ),
			);
		}

		$filename = sanitize_file_name( $filename );
		if ( '' === $filename ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid filename.', 'lead-magnet-locker' ),
			);
		}

		$upload_dir = wp_upload_dir();
		$file_path  = trailingslashit( $upload_dir['basedir'] ) . 'lead-magnet-files/' . $filename;

		if ( ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'File not found.', 'lead-magnet-locker' ),
			);
		}

		// Check if the file is being used in any downloads.
		global $wpdb;

		// Validate / whitelist the table name to avoid injection via identifier.
		$table = $this->validated_table_name( $this->download_table_name, $wpdb->prefix );
		if ( '' === $table ) {
			return array(
				'success' => false,
				'message' => esc_html__( 'Invalid table name.', 'lead-magnet-locker' ),
			);
		}

		// Build a safe identifier and a cache key.
		$cache_group = 'lead_magnet_locker';
		$cache_key   = 'file_usage_count:' . md5( $table . '|' . $filename );

		// Try the object cache first.
		$usage_count = wp_cache_get( $cache_key, $cache_group );
		if ( false === $usage_count ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			$usage_count = (int) $wpdb->get_var(
				// IMPORTANT: the SQL **string** here contains only the %s placeholder;
				// the table name is concatenated outside, so there is no interpolated variable in the string.
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->download_table_name} WHERE file_name = %s",
					array( $filename )
				)
			);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			// Store in the object cache for a short time (helps PHPCS and real perf). TTL may be ignored if no persistent cache.
			wp_cache_set( $cache_key, $usage_count, $cache_group, 60 );
		}

		if ( $usage_count > 0 ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %d: number of downloads the file has been used in */
					esc_html__( 'Cannot delete file. It has been used in %d downloads.', 'lead-magnet-locker' ),
					$usage_count
				),
			);
		}

		// Use wp_delete_file() instead of unlink().
		$deleted = wp_delete_file( $file_path );

		// Best-effort: invalidate the cached count for this file.
		wp_cache_delete( $cache_key, $cache_group );

		if ( $deleted ) {
			return array(
				'success' => true,
				'message' => esc_html__( 'File deleted successfully: ', 'lead-magnet-locker' ) . $filename,
			);
		}

		return array(
			'success' => false,
			'message' => esc_html__( 'Failed to delete file.', 'lead-magnet-locker' ),
		);
	}


	/**
	 * Create a secure directory for file storage
	 *
	 * @return void
	 */
	private function create_secure_directory(): void {
		$upload_dir = wp_upload_dir();
		$secure_dir = trailingslashit( $upload_dir['basedir'] ) . 'lead-magnet-files/';

		if ( ! file_exists( $secure_dir ) ) {
			wp_mkdir_p( $secure_dir );

			// Create .htaccess to prevent direct access (Apache).
			$htaccess_content = "Order deny,allow\nDeny from all\n";
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			file_put_contents( $secure_dir . '.htaccess', $htaccess_content );

			// Create index.php for additional security.
			$index_content = "<?php\n// Silence is golden.\n";
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			file_put_contents( $secure_dir . 'index.php', $index_content );
		}
	}

	/**
	 * Validate and normalize a table name. Returns sanitized table name or empty string on failure.
	 *
	 * @param string $table  Raw table name to validate.
	 * @param string $prefix Expected table prefix (usually $wpdb->prefix).
	 * @return string Sanitized table name or empty string if invalid.
	 */
	private function validated_table_name( string $table, string $prefix ): string {
		$table = trim( $table );

		// Minimal hardening: require prefix and allow only a-z, 0-9, _ after it.
		if ( '' === $table || 0 !== strpos( $table, $prefix ) ) {
			return '';
		}
		if ( ! preg_match( '/^[a-z0-9_]+$/', $table ) ) {
			return '';
		}

		return $table;
	}


	/**
	 * Get a human-readable message for a PHP upload error code.
	 *
	 * @param int $error_code PHP upload error code (e.g., UPLOAD_ERR_* constant).
	 * @return string Localized error message.
	 */
	private function get_upload_error_message( int $error_code ): string {
		return match ( $error_code ) {
			UPLOAD_ERR_INI_SIZE   => esc_html__( 'File exceeds upload_max_filesize directive in php.ini', 'lead-magnet-locker' ),
			UPLOAD_ERR_FORM_SIZE  => esc_html__( 'File exceeds MAX_FILE_SIZE directive in HTML form', 'lead-magnet-locker' ),
			UPLOAD_ERR_PARTIAL    => esc_html__( 'File was only partially uploaded', 'lead-magnet-locker' ),
			UPLOAD_ERR_NO_FILE    => esc_html__( 'No file was uploaded', 'lead-magnet-locker' ),
			UPLOAD_ERR_NO_TMP_DIR => esc_html__( 'Missing temporary folder', 'lead-magnet-locker' ),
			UPLOAD_ERR_CANT_WRITE => esc_html__( 'Failed to write file to disk', 'lead-magnet-locker' ),
			UPLOAD_ERR_EXTENSION  => esc_html__( 'File upload stopped by extension', 'lead-magnet-locker' ),
			default               => esc_html__( 'Unknown upload error', 'lead-magnet-locker' ),
		};
	}

	/**
	 * Format a byte size into a human-readable string.
	 *
	 * @param int $bytes Size in bytes.
	 * @return string Human-readable size (e.g., "1.23 MB").
	 */
	public function format_file_size( int $bytes ): string {
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' ); // Added more units.
		$bytes = max( $bytes, 0 );
		$pow   = (int) floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );

		$divisor = (float) pow( 1024, $pow );
		$size    = $bytes / $divisor;

		return number_format( $size, 2 ) . ' ' . $units[ $pow ];
	}
}
