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
	 * Action name used by admin-post.php and the nonce for admin downloads.
	 */
	public const ADMIN_ACTION = 'leadmalo_admin_download';

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

		$download_key = sanitize_text_field( wp_unslash( $_GET['lead_magnet_download'] ) );

		// Verify nonce tied to this key to validate origin.
		$nonce = isset( $_GET['lmd-nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['lmd-nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lead_magnet_download_' . $download_key ) ) {
			status_header( 403 );
			exit( 'Forbidden' );
		}

		// Get download record from a database.
		global $wpdb;
		$record = $wpdb->get_row(
			$wpdb->prepare(
				// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		$this->serve_file( $file_path );
	}

	/**
	 * Build the admin download URL for a history record.
	 *
	 * @param int $record_id Download record ID.
	 * @return string Nonce-protected admin-post.php URL.
	 */
	public function get_admin_download_url( int $record_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'    => self::ADMIN_ACTION,
					'record_id' => $record_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ADMIN_ACTION . '_' . $record_id
		);
	}

	/**
	 * Let an administrator download a record's file (runs on admin_post_{action}).
	 *
	 * Does not touch downloaded_at, so admin checks do not skew the statistics.
	 *
	 * @return void
	 */
	public function handle_admin_download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		$record_id = isset( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		check_admin_referer( self::ADMIN_ACTION . '_' . $record_id );

		global $wpdb;
		$record = $wpdb->get_row(
			$wpdb->prepare(
				// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $this->download_table_name WHERE id = %d",
				$record_id
			)
		);

		if ( ! $record ) {
			wp_die( esc_html__( 'Invalid download record.', 'lead-magnet-locker' ) );
		}

		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/lead-magnet-files/' . $record->file_name;

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found.', 'lead-magnet-locker' ) );
		}

		$this->serve_file( $file_path );
	}

	/**
	 * Stream a file to the browser as an attachment and stop execution.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return void
	 */
	private function serve_file( string $file_path ): void {
		$file_info = pathinfo( $file_path );
		$mime_type = $this->get_mime_type( $file_info['extension'] ?? '' );

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
