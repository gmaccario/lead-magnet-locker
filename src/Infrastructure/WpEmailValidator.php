<?php
/**
 * Email validator adapter: WordPress rules.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Application\Port\EmailValidator;

/**
 * Validates with is_email(), as earlier versions did.
 */
final class WpEmailValidator implements EmailValidator {

	/**
	 * Whether an email address is valid.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public function is_valid( string $email ): bool {
		return false !== is_email( $email );
	}
}
