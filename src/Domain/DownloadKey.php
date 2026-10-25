<?php
/**
 * Secret key of a download link.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Generates download keys: 32 random letters and digits, as since 1.0.
 */
final class DownloadKey {

	private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	private const LENGTH   = 32;

	/**
	 * A new random key (cryptographically secure).
	 *
	 * @return string
	 */
	public static function generate(): string {
		$key = '';
		$max = strlen( self::ALPHABET ) - 1;
		for ( $i = 0; $i < self::LENGTH; $i++ ) {
			$key .= self::ALPHABET[ random_int( 0, $max ) ];
		}

		return $key;
	}
}
