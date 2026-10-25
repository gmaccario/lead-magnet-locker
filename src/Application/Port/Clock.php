<?php
/**
 * Port: the current time.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application\Port;

/**
 * Clock.
 */
interface Clock {

	/**
	 * Current wall-clock time of the site (Y-m-d H:i:s).
	 *
	 * @return string
	 */
	public function now(): string;
}
