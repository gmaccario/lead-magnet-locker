<?php
/**
 * In-memory SettingsStore, Clock and EmailValidator for unit tests.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Application\Fake;

use LeadMagnet\Locker\Application\Port\Clock;
use LeadMagnet\Locker\Application\Port\EmailValidator;
use LeadMagnet\Locker\Application\Port\SettingsStore;
use LeadMagnet\Locker\Domain\Schedule;

/**
 * Fake settings, also a fixed clock and a simple email validator.
 */
final class FakeSettings implements SettingsStore, Clock, EmailValidator {

	/**
	 * Stored schedule.
	 *
	 * @var Schedule
	 */
	public Schedule $schedule;

	/**
	 * Current time.
	 *
	 * @var string
	 */
	public string $now = '2026-06-15 12:00:00';

	public function __construct() {
		$this->schedule = Schedule::from_settings( array() );
	}

	public function schedule(): Schedule {
		return $this->schedule;
	}

	public function save_schedule( Schedule $schedule ): void {
		$this->schedule = $schedule;
	}

	public function now(): string {
		return $this->now;
	}

	public function is_valid( string $email ): bool {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}
