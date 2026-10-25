<?php
/**
 * When the lead magnet forms are shown.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Optional visibility window: from start date + time to end date + time.
 */
final class Schedule {

	/**
	 * Constructor.
	 *
	 * @param bool   $enabled    Whether the window applies.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @param string $start_time Start time (H:i).
	 * @param string $end_time   End time (H:i).
	 * @param string $timezone   Timezone identifier (falls back to UTC when unknown).
	 */
	public function __construct(
		public readonly bool $enabled,
		public readonly string $start_date,
		public readonly string $end_date,
		public readonly string $start_time,
		public readonly string $end_time,
		public readonly string $timezone
	) {}

	/**
	 * Build from the stored settings (string values, as saved by the settings page).
	 *
	 * @param array<string, string> $settings Settings keyed by name.
	 * @return self
	 */
	public static function from_settings( array $settings ): self {
		return new self(
			'1' === ( $settings['enable_date_trigger'] ?? '' ),
			$settings['start_date'] ?? '',
			$settings['end_date'] ?? '',
			$settings['start_time'] ?? '',
			$settings['end_time'] ?? '',
			self::valid_timezone( $settings['timezone'] ?? '' )
		);
	}

	/**
	 * A known timezone identifier, or UTC.
	 *
	 * @param string $timezone Timezone identifier.
	 * @return string
	 */
	public static function valid_timezone( string $timezone ): string {
		return in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : 'UTC';
	}

	/**
	 * Whether forms are visible at a given moment.
	 *
	 * Visible when the window is disabled, incomplete, or malformed (fail open),
	 * otherwise only between start and end, both inclusive.
	 *
	 * @param string $now Current wall-clock time (Y-m-d H:i:s), read in the schedule's timezone.
	 * @return bool
	 */
	public function is_open( string $now ): bool {
		// empty() semantics, as in earlier versions: "" and "0" both count as not set.
		if ( ! $this->enabled || empty( $this->start_date ) || empty( $this->end_date ) ) {
			return true;
		}

		try {
			$timezone = new \DateTimeZone( $this->timezone );
			$current  = new \DateTimeImmutable( $now, $timezone );
			$start    = new \DateTimeImmutable( $this->start_date . ' ' . $this->start_time . ':00', $timezone );
			$end      = new \DateTimeImmutable( $this->end_date . ' ' . $this->end_time . ':59', $timezone );
		} catch ( \Exception ) {
			return true;
		}

		return $current >= $start && $current <= $end;
	}
}
