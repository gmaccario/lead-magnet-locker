<?php
/**
 * Human-readable file sizes.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Domain;

/**
 * Formats byte counts as "1.50 MB".
 */
final class ByteSize {

	private const UNITS = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );

	/**
	 * Format a byte count with two decimals and a binary unit.
	 *
	 * @param int $bytes Byte count (negative values count as 0).
	 * @return string
	 */
	public static function format( int $bytes ): string {
		$bytes = max( $bytes, 0 );
		$power = (int) floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$power = min( $power, count( self::UNITS ) - 1 );

		return number_format( $bytes / ( 1024 ** $power ), 2 ) . ' ' . self::UNITS[ $power ];
	}
}
