<?php
/**
 * Database setup on plugin activation.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

/**
 * Creates or upgrades the plugin tables and writes the default settings.
 */
final class Installer {

	/**
	 * Constructor.
	 *
	 * @param DownloadRepository $downloads Download records.
	 * @param SettingsRepository $settings  Settings.
	 */
	public function __construct(
		private DownloadRepository $downloads,
		private SettingsRepository $settings
	) {}

	/**
	 * Activation hook callback.
	 *
	 * @return void
	 */
	public function activate(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $this->downloads->schema() );
		dbDelta( $this->settings->schema() );

		$this->settings->install_defaults();
	}
}
