<?php
/**
 * Clock adapter: WordPress site time.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Application\Port\Clock;

/**
 * Reads the time with current_time( 'mysql' ), as earlier versions did.
 */
final class WpClock implements Clock {

	/**
	 * Current wall-clock time of the site.
	 *
	 * @return string
	 */
	public function now(): string {
		return current_time( 'mysql' );
	}
}
