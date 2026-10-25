<?php
/**
 * Plugin settings (the {prefix}lead_magnet_settings table).
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Application\Port\SettingsStore;
use LeadMagnet\Locker\Domain\Schedule;

// Custom table: no WordPress API or cache layer applies.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Settings in the plugin's own key/value table (wpdb).
 */
final class SettingsRepository implements SettingsStore {

	/**
	 * Table name without the prefix.
	 */
	public const TABLE = 'lead_magnet_settings';

	/**
	 * Settings that make up the schedule.
	 */
	public const SCHEDULE_KEYS = array( 'enable_date_trigger', 'start_date', 'end_date', 'start_time', 'end_time', 'timezone' );

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
	 * CREATE TABLE statement for dbDelta().
	 *
	 * @return string
	 */
	public function schema(): string {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dbDelta() takes a plain statement.
		return "CREATE TABLE {$this->table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value text,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) {$this->wpdb->get_charset_collate()};";
	}

	/**
	 * Values written on activation.
	 *
	 * @return array<string, string>
	 */
	public function defaults(): array {
		$timezone = (string) get_option( 'timezone_string' );

		return array(
			'enable_date_trigger' => '0',
			'start_date'          => '',
			'end_date'            => '',
			'start_time'          => '00:00',
			'end_time'            => '23:59',
			'timezone'            => '' !== $timezone ? $timezone : 'UTC',
		);
	}

	/**
	 * A setting value.
	 *
	 * @param string $key           Setting key.
	 * @param string $default_value Returned when the setting is not stored.
	 * @return string
	 */
	public function get( string $key, string $default_value = '' ): string {
		$wpdb = $this->wpdb;

		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT setting_value FROM %i WHERE setting_key = %s', $this->table, $key ) );

		return null !== $value ? (string) $value : $default_value;
	}

	/**
	 * Store a setting value.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Value.
	 * @return void
	 */
	public function set( string $key, string $value ): void {
		$wpdb = $this->wpdb;

		$wpdb->replace(
			$this->table,
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * The stored schedule.
	 *
	 * @return Schedule
	 */
	public function schedule(): Schedule {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %i plus the six SCHEDULE_KEYS.
			$wpdb->prepare(
				'SELECT setting_key, setting_value FROM %i WHERE setting_key IN (%s, %s, %s, %s, %s, %s)',
				$this->table,
				...self::SCHEDULE_KEYS
			),
			ARRAY_A
		);

		$settings = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$settings[ (string) $row['setting_key'] ] = (string) $row['setting_value'];
		}

		return Schedule::from_settings( $settings );
	}

	/**
	 * Store the schedule.
	 *
	 * @param Schedule $schedule Schedule.
	 * @return void
	 */
	public function save_schedule( Schedule $schedule ): void {
		$this->set( 'enable_date_trigger', $schedule->enabled ? '1' : '0' );
		$this->set( 'start_date', $schedule->start_date );
		$this->set( 'end_date', $schedule->end_date );
		$this->set( 'start_time', $schedule->start_time );
		$this->set( 'end_time', $schedule->end_time );
		$this->set( 'timezone', $schedule->timezone );
	}

	/**
	 * Write the default settings (activation).
	 *
	 * @return void
	 */
	public function install_defaults(): void {
		foreach ( $this->defaults() as $key => $value ) {
			$this->set( $key, $value );
		}
	}
}
