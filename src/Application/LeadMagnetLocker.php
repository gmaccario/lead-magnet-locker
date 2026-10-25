<?php
/**
 * Lead Magnet Locker application entry point.
 *
 * @file
 * @package LeadMagnetLocker
 * @subpackage Application
 * @since 1.1.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Infrastructure\DownloadRepository;
use LeadMagnet\Locker\Infrastructure\Installer;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerDownloader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerFileUploader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerHistoryExporter;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;
use LeadMagnet\Locker\Infrastructure\PluginPaths;
use LeadMagnet\Locker\Infrastructure\SettingsRepository;

/**
 * Composition root: builds the services once and wires them to WordPress.
 *
 * Constructing it has no side effects; register() adds the hooks.
 */
final class LeadMagnetLocker {

	/**
	 * Hook registrar.
	 *
	 * @var LeadMagnetLockerPluginBootstrap
	 */
	private LeadMagnetLockerPluginBootstrap $bootstrap;

	/**
	 * Constructor.
	 *
	 * @param \wpdb  $wpdb      Database.
	 * @param string $main_file Absolute path of lead-magnet-locker.php.
	 * @param string $version   Plugin version.
	 */
	public function __construct( \wpdb $wpdb, string $main_file, string $version ) {
		$paths      = new PluginPaths( $main_file, $version );
		$storage    = new LeadMagnetLockerStorage();
		$downloads  = new DownloadRepository( $wpdb );
		$settings   = new SettingsRepository( $wpdb );
		$uploader   = new LeadMagnetLockerFileUploader( $storage, $downloads );
		$downloader = new LeadMagnetLockerDownloader( $storage, $downloads );
		$exporter   = new LeadMagnetLockerHistoryExporter( $downloads );

		$this->bootstrap = new LeadMagnetLockerPluginBootstrap(
			new AdminController( $storage, $downloads, $settings, $uploader, $downloader, $exporter, $paths ),
			new ShortcodeController( $storage, $settings, $paths ),
			new SubmissionHandler( $storage, $downloads, $settings, $downloader ),
			$downloader,
			$uploader,
			$exporter,
			$storage,
			new Installer( $downloads, $settings ),
			$paths
		);
	}

	/**
	 * Attach the plugin to WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->bootstrap->hook_into_wp();
	}
}
