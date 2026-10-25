<?php
/**
 * Download history as CSV (admin-post.php).
 *
 * @package LeadMagnetLocker
 * @since 1.2.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Presentation;

use LeadMagnet\Locker\Application\Port\DownloadRecords;

/**
 * Streams the (optionally searched) history as a CSV file.
 */
final class HistoryExport {

	/**
	 * Action name used by admin-post.php and the nonce.
	 */
	public const ACTION = 'leadmalo_export_history';

	/**
	 * Constructor.
	 *
	 * @param DownloadRecords $records Download records.
	 */
	public function __construct( private DownloadRecords $records ) {}

	/**
	 * Export URL, keeping the current search term.
	 *
	 * @param string $search Optional search term.
	 * @return string Nonce-protected admin-post.php URL.
	 */
	public static function url( string $search = '' ): string {
		$args = array( 'action' => self::ACTION );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION );
	}

	/**
	 * Stream the CSV (runs on admin_post_{action}).
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		check_admin_referer( self::ACTION );

		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$records = $this->records->search( $search );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lead-magnet-history-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

		// Streaming to php://output, not the filesystem.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		// UTF-8 BOM so spreadsheet apps detect the encoding.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $output, "\xEF\xBB\xBF" );

		$this->write_row( $output, array( 'ID', 'Email', 'File', 'Submitted', 'Downloaded', 'Status' ) );

		foreach ( $records as $record ) {
			$this->write_row(
				$output,
				array(
					$record->id,
					$record->email,
					$record->file_name,
					$record->created_at,
					$record->downloaded_at ?? '',
					$record->is_downloaded() ? 'Downloaded' : 'Pending',
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Write one CSV row, neutralising values a spreadsheet would run as formulas.
	 *
	 * @param resource     $output Output stream.
	 * @param array<mixed> $row    Row values.
	 * @return void
	 */
	private function write_row( $output, array $row ): void {
		$row = array_map(
			static function ( $value ): string {
				$value = (string) $value;

				return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
			},
			$row
		);

		fputcsv( $output, $row, ',', '"', '' );
	}
}
