<?php
/**
 * Port: email address validation.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application\Port;

/**
 * Email validator.
 */
interface EmailValidator {

	/**
	 * Whether an email address is valid.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function is_valid( string $email ): bool;
}
