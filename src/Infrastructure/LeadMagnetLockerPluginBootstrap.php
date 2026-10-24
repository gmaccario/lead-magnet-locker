<?php
/**
 * Lead Magnet Locker – main plugin bootstrap.
 *
 * @package LeadMagnetLocker
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnet\Locker\Application\LeadMagnetLocker;

/**
 * WordPress bootstrapper for Lead Magnet Locker.
 *
 * Registers hooks, shortcodes, admin pages, assets, and activation tasks.
 */
class LeadMagnetLockerPluginBootstrap {

	/**
	 * Absolute path to the main plugin file (…/lead-magnet-locker.php).
	 *
	 * @var string
	 */
	private string $main_file = '';

	/**
	 * Fully-qualified downloads table name (with $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $download_table_name = '';

	/**
	 * Fully-qualified settings table name (with $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $settings_table_name = '';

	/**
	 * Bootstrapper constructor.
	 *
	 * Wires the application core, file uploader, and downloader services.
	 *
	 * @param LeadMagnetLocker                $plugin           Main application instance.
	 * @param LeadMagnetLockerFileUploader    $file_uploader    Uploader service for admin file ops.
	 * @param LeadMagnetLockerDownloader      $downloader       Public downloader handler.
	 * @param LeadMagnetLockerHistoryExporter $history_exporter Download history CSV exporter.
	 * @param LeadMagnetLockerStorage         $storage          Protected file storage.
	 */
	public function __construct(
		private readonly LeadMagnetLocker $plugin,
		private readonly LeadMagnetLockerFileUploader $file_uploader,
		private readonly LeadMagnetLockerDownloader $downloader,
		private readonly LeadMagnetLockerHistoryExporter $history_exporter,
		private readonly LeadMagnetLockerStorage $storage,
	) {
	}

	/**
	 * Set database table names used by the plugin.
	 *
	 * @param string $download_table_name  Downloads table (prefixed).
	 * @param string $settings_table_name  Settings table (prefixed).
	 * @return static
	 */
	public function set_tables_name( string $download_table_name, string $settings_table_name ): static {
		$this->download_table_name = $download_table_name;
		$this->settings_table_name = $settings_table_name;

		return $this;
	}

	/**
	 * Set the main plugin file path.
	 *
	 * Used to resolve plugin URL/path for assets and activation hooks.
	 *
	 * @param string $main_file Absolute path to the main plugin file.
	 * @return static
	 */
	public function set_main_file( string $main_file ): static {
		$this->main_file = $main_file;

		return $this;
	}

	/**
	 * Hook all plugin components into WordPress.
	 *
	 * Registers shortcodes, actions, filters, and the activation hook.
	 *
	 * @return void
	 */
	public function hook_into_wp(): void {
		// Hook into WordPress.
		$this->add_shortcodes();
		$this->add_actions();
		$this->add_filters();

		// Activation hook.
		register_activation_hook( $this->main_file, array( $this, 'create_database_tables' ) );
	}

	/**
	 * Register public shortcode(s).
	 *
	 * @return void
	 */
	private function add_shortcodes(): void {
		add_shortcode( 'lead_magnet', array( $this->plugin, 'display_form' ) );
	}

	/**
	 * Register WordPress actions (public + admin).
	 *
	 * @return void
	 */
	private function add_actions(): void {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_leadmalo_submit_email', array( $this->plugin, 'handle_email_submission' ) );
		add_action( 'wp_ajax_nopriv_leadmalo_submit_email', array( $this->plugin, 'handle_email_submission' ) );
		add_action( 'init', array( $this->downloader, 'handle_download' ) );

		// AJAX handlers for file management.
		add_action( 'wp_ajax_lead_magnet_upload_file', array( $this->file_uploader, 'ajax_upload_file' ) );
		add_action( 'wp_ajax_lead_magnet_delete_file', array( $this->file_uploader, 'ajax_delete_file' ) );

		// Admin actions.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'storage_notice' ) );
		add_action( 'wp_ajax_leadmalo_delete_download_record', array( $this->plugin, 'leadmalo_delete_download_record' ) );
		add_action( 'admin_post_' . LeadMagnetLockerHistoryExporter::ACTION, array( $this->history_exporter, 'handle_export' ) );
		add_action( 'admin_post_' . LeadMagnetLockerDownloader::ADMIN_ACTION, array( $this->downloader, 'handle_admin_download' ) );
	}

	/**
	 * Register WordPress filters.
	 *
	 * @return void
	 */
	private function add_filters(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( $this->main_file ), array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function admin_enqueue_scripts( string $hook ): void {
		$plugin_pages = array( 'lead-magnet-locker', 'lead-magnet-settings', 'lead-magnet-history' );
		$is_plugin    = array_filter( $plugin_pages, static fn( string $page ): bool => str_contains( $hook, $page ) );
		if ( empty( $is_plugin ) ) {
			return;
		}

		wp_enqueue_style(
			'lead-magnet-admin',
			plugin_dir_url( $this->main_file ) . 'views/admin/assets/css/admin.css',
			array(),
			$this->asset_version( 'views/admin/assets/css/admin.css' )
		);

		// Enqueue your custom admin script.
		wp_enqueue_script(
			'lead-magnet-admin',
			plugin_dir_url( $this->main_file ) . 'views/admin/assets/js/admin.js', // create this file.
			array( 'jquery' ),
			$this->asset_version( 'views/admin/assets/js/admin.js' ),
			true
		);

		// Localize to your script handle.
		wp_localize_script(
			'lead-magnet-admin',
			'lead_magnet_admin_ajax',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'lead_magnet_admin_nonce' ),
				'confirm_delete'     => __( 'Are you sure you want to delete this record?', 'lead-magnet-locker' ),
				'wp_max_upload_size' => esc_html( wp_max_upload_size() ),
			)
		);
	}

	/**
	 * Asset version: plugin version plus file modification time, so browsers refetch edited files.
	 *
	 * @param string $relative_path Asset path relative to the plugin folder.
	 * @return string
	 */
	private function asset_version( string $relative_path ): string {
		$file = plugin_dir_path( $this->main_file ) . $relative_path;

		return file_exists( $file )
			? LML_PLUGIN_VERSION . '.' . filemtime( $file )
			: LML_PLUGIN_VERSION;
	}

	/**
	 * Register settings and defaults for the plugin.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		register_setting(
			'lead_magnet_settings',
			'lead_magnet_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(
					'enable_date_trigger' => '0',
					'start_date'          => '',
					'end_date'            => '',
					'start_time'          => '00:00',
					'end_time'            => '23:59',
					'timezone'            => ( get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC' ),
				),
			)
		);
	}

	/**
	 * Sanitize the legacy "lead_magnet_options" option (settings live in the custom table).
	 *
	 * @param mixed $options Raw option value.
	 * @return array Sanitized options.
	 */
	public function sanitize_options( mixed $options ): array {
		$options = is_array( $options ) ? $options : array();
		$clean   = array(
			'enable_date_trigger' => ! empty( $options['enable_date_trigger'] ) ? '1' : '0',
		);

		foreach ( array( 'start_date', 'end_date', 'start_time', 'end_time' ) as $key ) {
			$clean[ $key ] = isset( $options[ $key ] ) ? sanitize_text_field( (string) $options[ $key ] ) : '';
		}

		$timezone          = isset( $options['timezone'] ) ? (string) $options['timezone'] : '';
		$clean['timezone'] = in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : 'UTC';

		return $clean;
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			'Lead Magnet Locker',
			'Lead Magnet',
			'manage_options',
			'lead-magnet-locker',
			array( $this->plugin, 'admin_page' ),
			'dashicons-download',
			30
		);

		add_submenu_page(
			'lead-magnet-locker',
			'Settings',
			'Settings',
			'manage_options',
			'lead-magnet-settings',
			array( $this->plugin, 'settings_page' )
		);

		add_submenu_page(
			'lead-magnet-locker',
			'Download History',
			'History',
			'manage_options',
			'lead-magnet-history',
			array( $this->plugin, 'history_page' )
		);
	}

	/**
	 * Enqueue public scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_script( 'jquery' );
		wp_localize_script(
			'jquery',
			'lead_magnet_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'leadmalo_public_nonce' ),
			)
		);

		wp_register_style(
			'leadmalo-public',
			plugin_dir_url( $this->main_file ) . 'views/public/assets/css/public.css',
			array(),
			'1.0'
		);
		wp_enqueue_style(
			'leadmalo-public',
			LML_PLUGIN_URL . 'assets/css/lead-magnet.css',
			array(),
			file_exists( LML_PLUGIN_DIR . 'assets/css/lead-magnet.css' )
				? filemtime( LML_PLUGIN_DIR . 'assets/css/lead-magnet.css' )
				: null
		);

		wp_register_script(
			'leadmalo-public',
			plugin_dir_url( $this->main_file ) . 'views/public/assets/js/public.js',
			array( 'jquery' ),
			$this->asset_version( 'views/public/assets/js/public.js' ),
			true
		);
		wp_localize_script(
			'leadmalo-public',
			'leadmaloPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'leadmalo_public_nonce' ),
			)
		);
		wp_enqueue_script( 'leadmalo-public' );
	}

	/**
	 * Initialize the plugin runtime (runs on 'init').
	 *
	 * @return void
	 */
	public function init(): void {
		// Create and harden the storage folders, then move legacy files into the private one.
		$this->storage->ensure_directories();
		$this->storage->maybe_migrate();
	}

	/**
	 * Warn admins, on the plugin pages, about files that could not be moved to protected storage.
	 *
	 * @return void
	 */
	public function storage_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! str_contains( (string) $screen->id, 'lead-magnet' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$unprotected = $this->storage->unprotected_files();
		if ( empty( $unprotected ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
			esc_html__( 'Lead Magnet Locker:', 'lead-magnet-locker' ),
			esc_html(
				sprintf(
					/* translators: %d: number of files */
					_n(
						'%d file could not be moved to protected storage and may be reachable by direct URL on servers that ignore .htaccess (e.g. Nginx). Check the folder permissions; the move is retried automatically.',
						'%d files could not be moved to protected storage and may be reachable by direct URL on servers that ignore .htaccess (e.g. Nginx). Check the folder permissions; the move is retried automatically.',
						count( $unprotected ),
						'lead-magnet-locker'
					),
					count( $unprotected )
				)
			),
			esc_html( implode( ', ', $unprotected ) )
		);
	}

	/**
	 * Create database tables on activation.
	 *
	 * @return void
	 */
	public function create_database_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Download table.
		$sql = "CREATE TABLE $this->download_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            file_name varchar(255) NOT NULL,
            download_key varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            downloaded_at datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY download_key (download_key)
        ) $charset_collate;";

		// Settings table.
		$sql2 = "CREATE TABLE $this->settings_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value text,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql2 );

		// Insert default settings.
		$this->insert_default_settings();
	}


	/**
	 * Insert default settings
	 *
	 * @return void
	 */
	private function insert_default_settings(): void {
		global $wpdb;

		$default_settings = array(
			'enable_date_trigger' => '0',
			'start_date'          => '',
			'end_date'            => '',
			'start_time'          => '00:00',
			'end_time'            => '23:59',
			'timezone'            => ( get_option( 'timezone_string' ) ? get_option( 'timezone_string' ) : 'UTC' ),
		);

		foreach ( $default_settings as $key => $value ) {
			$wpdb->replace(
				$this->settings_table_name,
				array(
					'setting_key'   => $key,
					'setting_value' => $value,
				),
				array( '%s', '%s' )
			);
		}
	}


	/**
	 * Add a "Settings" link to the plugin entry on the Plugins admin page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links with the Settings link added.
	 */
	public function add_plugin_action_links( array $links = array() ): mixed {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=lead-magnet-locker' ) . '">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
