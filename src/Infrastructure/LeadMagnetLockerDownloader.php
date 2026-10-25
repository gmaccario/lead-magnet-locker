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

use LeadMagnet\Locker\Domain\FileTypes;

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
	 * Public download link for a record key.
	 *
	 * The nonce is tied to the key, so a link stops working after the nonce lifetime.
	 *
	 * @param string $download_key Download key.
	 * @return string
	 */
	public function get_download_url( string $download_key ): string {
		return add_query_arg(
			array(
				'lead_magnet_download' => $download_key,
				'lmd-nonce'            => wp_create_nonce( 'lead_magnet_download_' . $download_key ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Handle secure file downloads (runs on 'init').
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

		$record = $this->downloads->find_by_key( $download_key );
		if ( null === $record ) {
			wp_die( esc_html__( 'Invalid download link.', 'lead-magnet-locker' ) );
		}

		$file_path = $this->storage->path( $record->file_name );
		if ( null === $file_path ) {
			wp_die( esc_html__( 'File not found.', 'lead-magnet-locker' ) );
		}

		$this->downloads->mark_downloaded( $record->id );

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

		$record = $this->downloads->find( $record_id );
		if ( null === $record ) {
			wp_die( esc_html__( 'Invalid download record.', 'lead-magnet-locker' ) );
		}

		$file_path = $this->storage->path( $record->file_name );
		if ( null === $file_path ) {
			wp_die( esc_html__( 'File not found.', 'lead-magnet-locker' ) );
		}

		$this->serve_file( $file_path );
	}

	/**
	 * Stream a file to the browser as an attachment and stop execution.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return never
	 */
	private function serve_file( string $file_path ): never {
		$name = basename( $file_path );

		header( 'Content-Type: ' . FileTypes::mime_type( $name ) );
		// Plain name for every client, plus the RFC 6266 form for names with special characters.
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', '\\' ), '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Streams the file without loading it into memory; not HTML, so nothing to escape.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );
		exit;
	}
}
