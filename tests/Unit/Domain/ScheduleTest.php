<?php
/**
 * Schedule visibility rules.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Domain;

use LeadMagnet\Locker\Domain\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Schedule tests.
 */
final class ScheduleTest extends TestCase {

	/**
	 * Schedule from settings, with a window from 2026-01-10 09:00 to 2026-01-20 17:00.
	 *
	 * @param array<string, string> $overrides Settings to change.
	 * @return Schedule
	 */
	private function schedule( array $overrides = array() ): Schedule {
		return Schedule::from_settings(
			$overrides + array(
				'enable_date_trigger' => '1',
				'start_date'          => '2026-01-10',
				'end_date'            => '2026-01-20',
				'start_time'          => '09:00',
				'end_time'            => '17:00',
				'timezone'            => 'Europe/Rome',
			)
		);
	}

	#[DataProvider( 'moments' )]
	public function test_window_bounds_are_inclusive( string $now, bool $open ): void {
		$this->assertSame( $open, $this->schedule()->is_open( $now ) );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function moments(): array {
		return array(
			'before start'     => array( '2026-01-10 08:59:59', false ),
			'at start'         => array( '2026-01-10 09:00:00', true ),
			'inside'           => array( '2026-01-15 03:00:00', true ),
			'last second'      => array( '2026-01-20 17:00:59', true ),
			'after end'        => array( '2026-01-20 17:01:00', false ),
			'a year later'     => array( '2027-01-15 12:00:00', false ),
		);
	}

	public function test_a_disabled_trigger_is_always_open(): void {
		$this->assertTrue( $this->schedule( array( 'enable_date_trigger' => '0' ) )->is_open( '2030-01-01 00:00:00' ) );
		$this->assertTrue( $this->schedule( array( 'enable_date_trigger' => '' ) )->is_open( '2030-01-01 00:00:00' ) );
	}

	#[DataProvider( 'incomplete_windows' )]
	public function test_incomplete_or_malformed_windows_fail_open( array $overrides ): void {
		$this->assertTrue( $this->schedule( $overrides )->is_open( '2030-01-01 00:00:00' ) );
	}

	/**
	 * @return array<string, array{array<string, string>}>
	 */
	public static function incomplete_windows(): array {
		return array(
			'no start date'    => array( array( 'start_date' => '' ) ),
			'no end date'      => array( array( 'end_date' => '' ) ),
			'"0" end date'     => array( array( 'end_date' => '0' ) ),
			'malformed date'   => array( array( 'end_date' => 'not a date' ) ),
			'malformed time'   => array( array( 'end_time' => '99:99:99' ) ),
		);
	}

	public function test_an_unknown_timezone_falls_back_to_utc(): void {
		$this->assertSame( 'UTC', $this->schedule( array( 'timezone' => 'Mars/Olympus_Mons' ) )->timezone );
		$this->assertSame( 'UTC', Schedule::valid_timezone( '' ) );
		$this->assertSame( 'America/New_York', Schedule::valid_timezone( 'America/New_York' ) );
	}

	public function test_missing_settings_mean_a_disabled_trigger(): void {
		$schedule = Schedule::from_settings( array() );

		$this->assertFalse( $schedule->enabled );
		$this->assertTrue( $schedule->is_open( '2026-01-01 00:00:00' ) );
	}
}
