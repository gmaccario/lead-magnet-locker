<?php
/**
 * Use case: an administrator saves the schedule.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Application\Port\SettingsStore;
use LeadMagnet\Locker\Domain\Schedule;

/**
 * Stores the schedule, replacing an unknown timezone with UTC.
 */
final class SaveSchedule {

	/**
	 * Constructor.
	 *
	 * @param SettingsStore $settings Settings.
	 */
	public function __construct( private SettingsStore $settings ) {}

	/**
	 * Run the use case.
	 *
	 * @param bool   $enabled    Whether the window applies.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $start_time Start time.
	 * @param string $end_time   End time.
	 * @param string $timezone   Timezone identifier.
	 * @return Schedule The stored schedule.
	 */
	public function execute( bool $enabled, string $start_date, string $end_date, string $start_time, string $end_time, string $timezone ): Schedule {
		$schedule = new Schedule( $enabled, $start_date, $end_date, $start_time, $end_time, Schedule::valid_timezone( $timezone ) );
		$this->settings->save_schedule( $schedule );

		return $schedule;
	}
}
