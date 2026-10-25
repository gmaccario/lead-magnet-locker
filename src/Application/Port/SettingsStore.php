<?php
/**
 * Port: where the settings are kept.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application\Port;

use LeadMagnet\Locker\Domain\Schedule;

/**
 * Settings storage.
 */
interface SettingsStore {

	/**
	 * The stored schedule.
	 *
	 * @return Schedule
	 */
	public function schedule(): Schedule;

	/**
	 * Store the schedule.
	 *
	 * @param Schedule $schedule Schedule.
	 * @return void
	 */
	public function save_schedule( Schedule $schedule ): void;
}
