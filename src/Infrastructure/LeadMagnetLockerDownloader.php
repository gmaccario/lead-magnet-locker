<?php
/**
 * Secure file download handler for Lead Magnet Locker.
 *
 * Validates nonces, looks up the requested asset, updates download
 * timestamps, and streams the file to the browser.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

/**
 * Downloader service class.
 *
 * Responsible for validating requests and serving files to users.
 */
class LeadMagnetLockerDownloader {

	/**
	 * Fully-qualified downloads table name (with $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $download_table_name = '';

	/**
	 * Set the downloads table name.
	 *
	 * @param string $download_table_name Downloads table name (prefixed).
	 * @return static
	 */
	public function set_tables_name( string $download_table_name ): static {
		$this->download_table_name = $download_table_name;

		return $this;
	}

	/**
	 * Handle secure file downloads.
	 *
	 * @return void
	 */
	public function handle_download(): void {
		if ( ! isset( $_GET['lead_magnet_download'] ) ) {
			return;
		}

		$download_key = sanitize_text_field( $_GET['lead_magnet_download'] );

		// Verify nonce tied to this key to validate origin.
		$nonce = isset( $_GET['lmd-nonce'] ) ? sanitize_text_field( $_GET['lmd-nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lead_magnet_download_' . $download_key ) ) {
			status_header( 403 );
			exit( 'Forbidden' );
		}

		// Get download record from a database.
		global $wpdb;
		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->download_table_name WHERE download_key = %s",
				$download_key
			)
		);

		if ( ! $record ) {
			wp_die( 'Invalid download link.' );
		}

		// Check if a file exists.
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/lead-magnet-files/' . $record->file_name;

		if ( ! file_exists( $file_path ) ) {
			wp_die( 'File not found.' );
		}

		// Update download timestamp.
		$wpdb->update(
			$this->download_table_name,
			array( 'downloaded_at' => current_time( 'mysql' ) ),
			array( 'id' => $record->id ),
			array( '%s' ),
			array( '%d' )
		);

		// Serve the file.
		$file_info = pathinfo( $file_path );
		$mime_type = $this->get_mime_type( $file_info['extension'] );

		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// readfile() streams the file directly without loading into memory.
		// and doesn't require escaping as it's not HTML output.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );
		exit;
	}

	/**
	 * Get MIME type based on file extension.
	 *
	 * @param string $extension File extension (without leading dot).
	 * @return string MIME type string for the given extension.
	 */
	private function get_mime_type( string $extension ): string {
		$mime_types = array(
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

		return $mime_types[ strtolower( $extension ) ] ?? 'application/octet-stream';
	}
}
