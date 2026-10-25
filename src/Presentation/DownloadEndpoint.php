<?php
/**
 * File downloads over HTTP: visitor links and admin downloads.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Presentation;

use LeadMagnet\Locker\Application\Outcome;
use LeadMagnet\Locker\Application\ResolveDownload;
use LeadMagnet\Locker\Domain\FileTypes;

/**
 * Builds and serves download links.
 */
final class DownloadEndpoint {

	/**
	 * Action name used by admin-post.php and the nonce for admin downloads.
	 */
	public const ADMIN_ACTION = 'leadmalo_admin_download';

	/**
	 * Constructor.
	 *
	 * @param ResolveDownload $resolve Use case.
	 */
	public function __construct( private ResolveDownload $resolve ) {}

	/**
	 * Public download link for a record key.
	 *
	 * The nonce is tied to the key, so a link stops working after the nonce lifetime.
	 *
	 * @param string $download_key Download key.
	 * @return string
	 */
	public static function public_url( string $download_key ): string {
		return add_query_arg(
			array(
				'lead_magnet_download' => $download_key,
				'lmd-nonce'            => wp_create_nonce( 'lead_magnet_download_' . $download_key ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Admin download link for a history record.
	 *
	 * @param int $record_id Download record ID.
	 * @return string Nonce-protected admin-post.php URL.
	 */
	public static function admin_url( int $record_id ): string {
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
	 * Serve a visitor's download (runs on 'init').
	 *
	 * @return void
	 */
	public function handle_visitor(): void {
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

		$this->serve( $this->resolve->for_visitor( $download_key ), __( 'Invalid download link.', 'lead-magnet-locker' ) );
	}

	/**
	 * Serve an administrator's download from the history (runs on admin_post_{action}).
	 *
	 * @return void
	 */
	public function handle_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		$record_id = isset( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		check_admin_referer( self::ADMIN_ACTION . '_' . $record_id );

		$this->serve( $this->resolve->for_admin( $record_id ), __( 'Invalid download record.', 'lead-magnet-locker' ) );
	}

	/**
	 * Stream the resolved file, or stop with the matching message.
	 *
	 * @param Outcome $outcome          Use case result.
	 * @param string  $no_record_message Message when the record does not exist.
	 * @return never
	 */
	private function serve( Outcome $outcome, string $no_record_message ): never {
		if ( ResolveDownload::RECORD_NOT_FOUND === $outcome->error ) {
			wp_die( esc_html( $no_record_message ) );
		}
		if ( ! $outcome->is_success() ) {
			wp_die( esc_html__( 'File not found.', 'lead-magnet-locker' ) );
		}

		$path = $outcome->string( 'path' );
		$name = basename( $path );

		header( 'Content-Type: ' . FileTypes::mime_type( $name ) );
		// Plain name for every client, plus the RFC 6266 form for names with special characters.
		header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', '\\' ), '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Streams the file without loading it into memory; not HTML, so nothing to escape.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}
}
