<?php
/**
 * Download records (the {prefix}lead_magnet_downloads table).
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Application\Port\DownloadRecords;
use LeadMagnet\Locker\Domain\DownloadRecord;
use LeadMagnet\Locker\Domain\DownloadStatistics;

// Custom table: no WordPress API or cache layer applies.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Download records in the plugin's own table (wpdb). All SQL for them lives here.
 */
final class DownloadRepository implements DownloadRecords {

	/**
	 * Table name without the prefix.
	 */
	public const TABLE = 'lead_magnet_downloads';

	/**
	 * Prefixed table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb Database.
	 */
	public function __construct( private \wpdb $wpdb ) {
		$this->table = $wpdb->prefix . self::TABLE;
	}

	/**
	 * Prefixed table name.
	 *
	 * @return string
	 */
	public function table(): string {
		return $this->table;
	}

	/**
	 * CREATE TABLE statement for dbDelta().
	 *
	 * @return string
	 */
	public function schema(): string {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dbDelta() takes a plain statement.
		return "CREATE TABLE {$this->table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            download_key varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            downloaded_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY download_key (download_key)
        ) {$this->wpdb->get_charset_collate()};";
	}

	/**
	 * Store a submission.
	 *
	 * @param string $email        Email address.
	 * @param string $file_name    File name.
	 * @param string $download_key Download key.
	 * @return bool
	 */
	public function create( string $email, string $file_name, string $download_key ): bool {
		$wpdb = $this->wpdb;

		return false !== $wpdb->insert(
			$this->table,
			array(
				'email'        => $email,
				'file_name'    => $file_name,
				'download_key' => $download_key,
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Record by download key.
	 *
	 * @param string $download_key Download key.
	 * @return DownloadRecord|null
	 */
	public function find_by_key( string $download_key ): ?DownloadRecord {
		$wpdb = $this->wpdb;

		return $this->record( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE download_key = %s', $this->table, $download_key ), ARRAY_A ) );
	}

	/**
	 * Record by ID.
	 *
	 * @param int $id Record ID.
	 * @return DownloadRecord|null
	 */
	public function find( int $id ): ?DownloadRecord {
		$wpdb = $this->wpdb;

		return $this->record( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table, $id ), ARRAY_A ) );
	}

	/**
	 * Set downloaded_at to the current site time.
	 *
	 * @param int $id Record ID.
	 * @return void
	 */
	public function mark_downloaded( int $id ): void {
		$wpdb = $this->wpdb;

		$wpdb->update(
			$this->table,
			array( 'downloaded_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a record.
	 *
	 * @param int $id Record ID.
	 * @return bool False on a database error (deleting a missing record is not an error).
	 */
	public function delete( int $id ): bool {
		$wpdb = $this->wpdb;

		return false !== $wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Number of records for a file.
	 *
	 * @param string $file_name File name.
	 * @return int
	 */
	public function count_for_file( string $file_name ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE file_name = %s', $this->table, $file_name ) );
	}

	/**
	 * Dashboard statistics.
	 *
	 * @return DownloadStatistics
	 */
	public function statistics(): DownloadStatistics {
		$wpdb = $this->wpdb;

		return new DownloadStatistics(
			(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table ) ),
			(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT email) FROM %i', $this->table ) ),
			(int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE downloaded_at IS NOT NULL', $this->table ) )
		);
	}

	/**
	 * Number of records matching a search ("" matches all).
	 *
	 * @param string $search Searched in email and file name, literally.
	 * @return int
	 */
	public function count( string $search = '' ): int {
		$wpdb = $this->wpdb;

		if ( '' === $search ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table ) );
		}

		$like = $this->like( $search );

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE email LIKE %s OR file_name LIKE %s', $this->table, $like, $like ) );
	}

	/**
	 * Records matching a search, newest first.
	 *
	 * @param string   $search Searched in email and file name, literally ("" matches all).
	 * @param int|null $limit  Page size, or null for all records.
	 * @param int      $offset Offset.
	 * @return list<DownloadRecord>
	 */
	public function search( string $search = '', ?int $limit = null, int $offset = 0 ): array {
		$wpdb = $this->wpdb;

		$sql  = 'SELECT * FROM %i';
		$args = array( $this->table );
		if ( '' !== $search ) {
			$sql   .= ' WHERE email LIKE %s OR file_name LIKE %s';
			$args[] = $this->like( $search );
			$args[] = $this->like( $search );
		}

		$sql .= ' ORDER BY created_at DESC';
		if ( null !== $limit ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = $limit;
			$args[] = $offset;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql holds only literals and placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		$records = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$records[] = DownloadRecord::from_row( $row );
		}

		return $records;
	}

	/**
	 * Entity from a row, or null.
	 *
	 * @param array<string, mixed>|null $row Row (ARRAY_A).
	 * @return DownloadRecord|null
	 */
	private function record( ?array $row ): ?DownloadRecord {
		return is_array( $row ) ? DownloadRecord::from_row( $row ) : null;
	}

	/**
	 * LIKE pattern that matches the search literally.
	 *
	 * @param string $search Search.
	 * @return string
	 */
	private function like( string $search ): string {
		$wpdb = $this->wpdb;

		return '%' . $wpdb->esc_like( $search ) . '%';
	}
}
