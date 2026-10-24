<?php
/**
 * Download history CSV exporter for Lead Magnet Locker.
 *
 * Validates the request, fetches the download records (optionally
 * filtered by the history search term), and streams them as CSV.
 *
 * @package LeadMagnetLocker
 * @since 1.2.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

/**
 * History exporter service class.
 *
 * Responsible for exporting the download history to a CSV file.
 */
class LeadMagnetLockerHistoryExporter {

	/**
	 * Action name used by admin-post.php and the nonce.
	 */
	public const ACTION = 'leadmalo_export_history';

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
	 * Build the export URL, keeping the current search term (if any).
	 *
	 * @param string $search Current history search term.
	 * @return string Nonce-protected admin-post.php URL.
	 */
	public function get_export_url( string $search = '' ): string {
		$args = array( 'action' => self::ACTION );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION );
	}

	/**
	 * Handle the CSV export request (runs on admin_post_{action}).
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ) );
		}

		check_admin_referer( self::ACTION );

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		global $wpdb;
		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$records = $wpdb->get_results(
				$wpdb->prepare(
					// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$this->download_table_name} WHERE email LIKE %s OR file_name LIKE %s ORDER BY created_at DESC",
					$like,
					$like
				)
			);
		} else {
			// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$records = $wpdb->get_results( "SELECT * FROM {$this->download_table_name} ORDER BY created_at DESC" );
		}

		$filename = 'lead-magnet-history-' . gmdate( 'Y-m-d-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Streaming to php://output, not the filesystem.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$output = fopen( 'php://output', 'w' );

		// UTF-8 BOM so spreadsheet apps detect the encoding.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fwrite( $output, "\xEF\xBB\xBF" );

		$this->write_row( $output, array( 'ID', 'Email', 'File', 'Submitted', 'Downloaded', 'Status' ) );

		foreach ( (array) $records as $record ) {
			$this->write_row(
				$output,
				array(
					$record->id,
					$record->email,
					$record->file_name,
					$record->created_at,
					$record->downloaded_at ?? '',
					$record->downloaded_at ? 'Downloaded' : 'Pending',
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Write one CSV row, neutralizing values that spreadsheets would run as formulas.
	 *
	 * @param resource $output Output stream.
	 * @param array    $row    Row values.
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
