<?php
/**
 * SaveSchedule use case.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Tests\Unit\Application;

use LeadMagnet\Locker\Application\SaveSchedule;
use LeadMagnet\Locker\Tests\Unit\Application\Fake\FakeSettings;
use PHPUnit\Framework\TestCase;

/**
 * SaveSchedule tests.
 */
final class SaveScheduleTest extends TestCase {

	public function test_values_are_stored_and_unknown_timezones_become_utc(): void {
		$settings = new FakeSettings();
		$save     = new SaveSchedule( $settings );

		$save->execute( true, '2026-01-01', '2026-01-31', '09:00', '17:00', 'Europe/Rome' );
		$this->assertTrue( $settings->schedule->enabled );
		$this->assertSame( '2026-01-31', $settings->schedule->end_date );
		$this->assertSame( 'Europe/Rome', $settings->schedule->timezone );

		$save->execute( false, '', '', '', '', 'Mars/Olympus_Mons' );
		$this->assertFalse( $settings->schedule->enabled );
		$this->assertSame( 'UTC', $settings->schedule->timezone );
	}
}
