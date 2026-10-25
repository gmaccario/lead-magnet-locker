<?php
/**
 * WordPress hooks for Lead Magnet Locker.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Application\AdminController;
use LeadMagnet\Locker\Application\ShortcodeController;
use LeadMagnet\Locker\Application\SubmissionHandler;

/**
 * The single place where the plugin's services are attached to WordPress.
 */
class LeadMagnetLockerPluginBootstrap {

	/**
	 * Constructor.
	 *
	 * @param AdminController                 $admin      Admin pages.
	 * @param ShortcodeController             $shortcode  Shortcode and front-end assets.
	 * @param SubmissionHandler               $submission Public form submissions.
	 * @param LeadMagnetLockerDownloader      $downloader File downloads.
	 * @param LeadMagnetLockerFileUploader    $uploader   File upload and delete.
	 * @param LeadMagnetLockerHistoryExporter $exporter   CSV export.
	 * @param LeadMagnetLockerStorage         $storage    Protected file storage.
	 * @param Installer                       $installer  Activation.
	 * @param PluginPaths                     $paths      Plugin files.
	 */
	public function __construct(
		private AdminController $admin,
		private ShortcodeController $shortcode,
		private SubmissionHandler $submission,
		private LeadMagnetLockerDownloader $downloader,
		private LeadMagnetLockerFileUploader $uploader,
		private LeadMagnetLockerHistoryExporter $exporter,
		private LeadMagnetLockerStorage $storage,
		private Installer $installer,
		private PluginPaths $paths
	) {}

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public function hook_into_wp(): void {
		register_activation_hook( $this->paths->main_file, array( $this->installer, 'activate' ) );

		add_shortcode( ShortcodeController::TAG, array( $this->shortcode, 'render' ) );

		// Storage first, so downloads on the same 'init' find migrated files.
		add_action( 'init', array( $this, 'init_storage' ) );
		add_action( 'init', array( $this->downloader, 'handle_download' ) );
		add_action( 'wp_enqueue_scripts', array( $this->shortcode, 'enqueue_assets' ) );

		add_action( 'wp_ajax_' . SubmissionHandler::ACTION, array( $this->submission, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . SubmissionHandler::ACTION, array( $this->submission, 'handle' ) );
		add_action( 'wp_ajax_lead_magnet_upload_file', array( $this->uploader, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_lead_magnet_delete_file', array( $this->uploader, 'ajax_delete_file' ) );
		add_action( 'wp_ajax_' . AdminController::ACTION_DELETE_RECORD, array( $this->admin, 'delete_record' ) );

		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this->admin, 'storage_notice' ) );
		add_action( 'admin_post_' . LeadMagnetLockerHistoryExporter::ACTION, array( $this->exporter, 'handle_export' ) );
		add_action( 'admin_post_' . LeadMagnetLockerDownloader::ADMIN_ACTION, array( $this->downloader, 'handle_admin_download' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( $this->paths->main_file ), array( $this->admin, 'plugin_action_links' ) );
	}

	/**
	 * Create and harden the storage folders, then move legacy files into the private one.
	 *
	 * @return void
	 */
	public function init_storage(): void {
		$this->storage->ensure_directories();
		$this->storage->maybe_migrate();
	}
}
