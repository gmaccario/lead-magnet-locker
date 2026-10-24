<?php
/**
 * Plugin Name: Lead Magnet Locker
 * Description: Collect emails in exchange for secure digital downloads. Protect your files, grow your list, and deliver assets automatically.
 * Version: 1.3.0
 * Author: G. Maccario
 * Author URI: https://www.giuseppemaccario.com/
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lead-magnet-locker
 *
 * Requires PHP: 8.1
 * Requires at least: 6.7
 * Tested up to: 7.1
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnetLocker;

use LeadMagnet\Locker\Application\LeadMagnetLocker;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include Composer autoloader if the autoload file exists.
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	// Only log in debug mode, not in production.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'LeadMagnet - Autoloader file not found at: ' . __DIR__ . '/vendor/autoload.php' );
	}
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Test if a class can be found.
if ( ! class_exists( '\LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap' ) ) {
	// Only log in debug mode, not in production.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'LeadMagnet - Class NOT found by autoloader' );
	}
	return;
}

if ( ! defined( 'LML_PLUGIN_VERSION' ) ) {
	define( 'LML_PLUGIN_VERSION', '1.3.0' );
}
if ( ! defined( 'LML_PLUGIN_FILE' ) ) {
	define( 'LML_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'LML_PLUGIN_DIR' ) ) {
	define( 'LML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) ); // .../wp-content/plugins/lead-magnet-locker/
}
if ( ! defined( 'LML_PLUGIN_URL' ) ) {
	define( 'LML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Public helper functions for themes and other plugins.
require_once __DIR__ . '/functions.php';

// Initialize the plugin.
new LeadMagnetLocker();
