<?php
/**
 * Public helper functions for themes and other plugins.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lead_magnet_locker_file_exists' ) ) {
	/**
	 * Whether a lead magnet file is available for the [lead_magnet] shortcode.
	 *
	 * Use this instead of checking a path under uploads/: since 1.3.0 files
	 * live in a private, randomly named folder.
	 *
	 * @param string $file File name, as used in the shortcode's "file" attribute.
	 * @return bool
	 */
	function lead_magnet_locker_file_exists( string $file ): bool {
		return null !== ( new LeadMagnetLockerStorage() )->path( $file );
	}
}
