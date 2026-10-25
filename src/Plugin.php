<?php
/**
 * Composition root of Lead Magnet Locker.
 *
 * @package LeadMagnetLocker
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker;

use LeadMagnet\Locker\Application\DeleteFile;
use LeadMagnet\Locker\Application\ListFiles;
use LeadMagnet\Locker\Application\ResolveDownload;
use LeadMagnet\Locker\Application\SaveSchedule;
use LeadMagnet\Locker\Application\SubmitEmail;
use LeadMagnet\Locker\Application\UploadFile;
use LeadMagnet\Locker\Infrastructure\DownloadRepository;
use LeadMagnet\Locker\Infrastructure\Installer;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;
use LeadMagnet\Locker\Infrastructure\SettingsRepository;
use LeadMagnet\Locker\Infrastructure\WpClock;
use LeadMagnet\Locker\Infrastructure\WpEmailValidator;
use LeadMagnet\Locker\Presentation\AdminController;
use LeadMagnet\Locker\Presentation\DownloadEndpoint;
use LeadMagnet\Locker\Presentation\FileActions;
use LeadMagnet\Locker\Presentation\HistoryExport;
use LeadMagnet\Locker\Presentation\PluginPaths;
use LeadMagnet\Locker\Presentation\ShortcodeController;
use LeadMagnet\Locker\Presentation\SubmissionEndpoint;

/**
 * Builds every service once and attaches the plugin to WordPress.
 *
 * The only class that knows all layers. Constructing it has no side effects;
 * register() adds the hooks.
 */
final class Plugin {

	/**
	 * Protected file storage.
	 *
	 * @var LeadMagnetLockerStorage
	 */
	private LeadMagnetLockerStorage $storage;

	/**
	 * Activation.
	 *
	 * @var Installer
	 */
	private Installer $installer;

	/**
	 * Admin pages.
	 *
	 * @var AdminController
	 */
	private AdminController $admin;

	/**
	 * Shortcode and front-end assets.
	 *
	 * @var ShortcodeController
	 */
	private ShortcodeController $shortcode;

	/**
	 * Public form submissions.
	 *
	 * @var SubmissionEndpoint
	 */
	private SubmissionEndpoint $submission;

	/**
	 * File downloads.
	 *
	 * @var DownloadEndpoint
	 */
	private DownloadEndpoint $download;

	/**
	 * File upload and delete.
	 *
	 * @var FileActions
	 */
	private FileActions $file_actions;

	/**
	 * CSV export.
	 *
	 * @var HistoryExport
	 */
	private HistoryExport $export;

	/**
	 * Plugin files.
	 *
	 * @var PluginPaths
	 */
	private PluginPaths $paths;

	/**
	 * Constructor.
	 *
	 * @param \wpdb  $wpdb      Database.
	 * @param string $main_file Absolute path of lead-magnet-locker.php.
	 * @param string $version   Plugin version.
	 */
	public function __construct( \wpdb $wpdb, string $main_file, string $version ) {
		// Infrastructure (adapters of the application ports).
		$this->storage = new LeadMagnetLockerStorage();
		$records       = new DownloadRepository( $wpdb );
		$settings      = new SettingsRepository( $wpdb );
		$clock         = new WpClock();

		$this->installer = new Installer( $records, $settings );
		$this->paths     = new PluginPaths( $main_file, $version );

		// Presentation, on top of the use cases.
		$this->file_actions = new FileActions( new UploadFile( $this->storage ), new DeleteFile( $this->storage, $records ) );
		$this->download     = new DownloadEndpoint( new ResolveDownload( $records, $this->storage ) );
		$this->export       = new HistoryExport( $records );
		$this->shortcode    = new ShortcodeController( $this->storage, $settings, $clock, $this->paths );
		$this->submission   = new SubmissionEndpoint(
			new SubmitEmail( $settings, $clock, new WpEmailValidator(), $this->storage, $records ),
			$this->storage
		);
		$this->admin        = new AdminController(
			$this->file_actions,
			new ListFiles( $this->storage ),
			new SaveSchedule( $settings ),
			$records,
			$settings,
			$this->storage,
			$clock,
			$this->paths
		);
	}

	/**
	 * Attach the plugin to WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		register_activation_hook( $this->paths->main_file, array( $this->installer, 'activate' ) );

		add_shortcode( ShortcodeController::TAG, array( $this->shortcode, 'render' ) );

		// Storage first, so downloads on the same 'init' find migrated files.
		add_action( 'init', array( $this, 'init_storage' ) );
		add_action( 'init', array( $this->download, 'handle_visitor' ) );
		add_action( 'wp_enqueue_scripts', array( $this->shortcode, 'enqueue_assets' ) );

		add_action( 'wp_ajax_' . SubmissionEndpoint::ACTION, array( $this->submission, 'handle' ) );
		add_action( 'wp_ajax_nopriv_' . SubmissionEndpoint::ACTION, array( $this->submission, 'handle' ) );
		add_action( 'wp_ajax_lead_magnet_upload_file', array( $this->file_actions, 'ajax_upload' ) );
		add_action( 'wp_ajax_lead_magnet_delete_file', array( $this->file_actions, 'ajax_delete' ) );
		add_action( 'wp_ajax_' . AdminController::ACTION_DELETE_RECORD, array( $this->admin, 'delete_record' ) );

		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this->admin, 'storage_notice' ) );
		add_action( 'admin_post_' . HistoryExport::ACTION, array( $this->export, 'handle' ) );
		add_action( 'admin_post_' . DownloadEndpoint::ADMIN_ACTION, array( $this->download, 'handle_admin' ) );

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
