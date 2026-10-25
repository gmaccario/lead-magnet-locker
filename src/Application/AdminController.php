<?php
/**
 * Admin pages: dashboard, settings and download history.
 *
 * @package LeadMagnetLocker
 * @subpackage Application
 * @since 1.3.0
 */

declare(strict_types=1);

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Domain\ByteSize;
use LeadMagnet\Locker\Domain\Schedule;
use LeadMagnet\Locker\Infrastructure\DownloadRepository;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerDownloader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerFileUploader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerHistoryExporter;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;
use LeadMagnet\Locker\Infrastructure\PluginPaths;
use LeadMagnet\Locker\Infrastructure\SettingsRepository;

/**
 * Admin menu, pages, assets and notices.
 */
final class AdminController {

	public const PAGE_DASHBOARD = 'lead-magnet-locker';
	public const PAGE_SETTINGS  = 'lead-magnet-settings';
	public const PAGE_HISTORY   = 'lead-magnet-history';

	/**
	 * AJAX action that deletes a download record.
	 */
	public const ACTION_DELETE_RECORD = 'leadmalo_delete_download_record';

	/**
	 * Nonce action for the admin AJAX calls.
	 */
	private const NONCE = 'lead_magnet_admin_nonce';

	/**
	 * History rows per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Constructor.
	 *
	 * @param LeadMagnetLockerStorage         $storage    Protected file storage.
	 * @param DownloadRepository              $downloads  Download records.
	 * @param SettingsRepository              $settings   Settings.
	 * @param LeadMagnetLockerFileUploader    $uploader   Upload and delete.
	 * @param LeadMagnetLockerDownloader      $downloader Admin download links.
	 * @param LeadMagnetLockerHistoryExporter $exporter   CSV export links.
	 * @param PluginPaths                     $paths      Plugin files.
	 */
	public function __construct(
		private LeadMagnetLockerStorage $storage,
		private DownloadRepository $downloads,
		private SettingsRepository $settings,
		private LeadMagnetLockerFileUploader $uploader,
		private LeadMagnetLockerDownloader $downloader,
		private LeadMagnetLockerHistoryExporter $exporter,
		private PluginPaths $paths
	) {}

	/**
	 * Register the menu and its pages (runs on admin_menu).
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page( 'Lead Magnet Locker', 'Lead Magnet', 'manage_options', self::PAGE_DASHBOARD, array( $this, 'dashboard_page' ), 'dashicons-download', 30 );
		add_submenu_page( self::PAGE_DASHBOARD, 'Settings', 'Settings', 'manage_options', self::PAGE_SETTINGS, array( $this, 'settings_page' ) );
		add_submenu_page( self::PAGE_DASHBOARD, 'Download History', 'History', 'manage_options', self::PAGE_HISTORY, array( $this, 'history_page' ) );
	}

	/**
	 * Dashboard: statistics, upload form, file list.
	 *
	 * @return void
	 */
	public function dashboard_page(): void {
		$upload_message = '';

		// check_admin_referer() stops the request when the nonce is invalid.
		if ( isset( $_POST['upload_file'], $_FILES['lead_magnet_file'] ) ) {
			check_admin_referer( 'lead_magnet_upload', 'upload_nonce' );
			$upload_message = $this->uploader->handle_file_upload()->message;
		}

		if ( isset( $_POST['delete_file'], $_POST['file_to_delete'] ) ) {
			check_admin_referer( 'lead_magnet_delete', 'delete_nonce' );
			$upload_message = $this->uploader->handle_file_deletion( sanitize_file_name( wp_unslash( $_POST['file_to_delete'] ) ) )->message;
		}

		// Protect files added to the legacy folder since the last migration (e.g. via FTP).
		if ( $this->storage->unprotected_files() ) {
			$this->storage->migrate();
		}

		$files = array();
		foreach ( $this->storage->list_files() as $name ) {
			$file_path = $this->storage->path( $name );
			if ( null !== $file_path ) {
				$files[] = array(
					'name'     => $name,
					'size'     => ByteSize::format( (int) filesize( $file_path ) ),
					'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $file_path ) ),
				);
			}
		}

		$statistics = $this->downloads->statistics();

		$this->render(
			'admin/admin-page.php',
			array(
				'upload_message'      => $upload_message,
				'total_downloads'     => $statistics['total'],
				'unique_emails'       => $statistics['unique_emails'],
				'completed_downloads' => $statistics['completed'],
				'max_upload_size'     => ByteSize::format( (int) wp_max_upload_size() ),
				'files'               => $files,
			)
		);
	}

	/**
	 * Settings: the visibility schedule.
	 *
	 * @return void
	 */
	public function settings_page(): void {
		if ( isset( $_POST['submit'] ) ) {
			if ( ! isset( $_POST['settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['settings_nonce'] ) ), 'lead_magnet_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'lead-magnet-locker' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ) );
			}

			$values = array( 'enable_date_trigger' => isset( $_POST['enable_date_trigger'] ) ? '1' : '0' );
			foreach ( array( 'start_date', 'end_date', 'start_time', 'end_time', 'timezone' ) as $key ) {
				$values[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			}
			$values['timezone'] = Schedule::valid_timezone( $values['timezone'] );

			$this->settings->save_schedule( $values );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved successfully!', 'lead-magnet-locker' ) . '</p></div>';
		}

		$schedule = $this->settings->schedule();

		$this->render(
			'admin/settings-page.php',
			array(
				'is_visible'          => $schedule->is_open( current_time( 'mysql' ) ),
				'enable_date_trigger' => $schedule->enabled ? '1' : '0',
				'start_date'          => $schedule->start_date,
				'end_date'            => $schedule->end_date,
				'start_time'          => $schedule->start_time,
				'end_time'            => $schedule->end_time,
				'timezone'            => $schedule->timezone,
			)
		);
	}

	/**
	 * Download history with search and pagination.
	 *
	 * @return void
	 */
	public function history_page(): void {
		$page   = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
		$search = '';

		// The search form carries a nonce; a search without one is ignored.
		if ( ! empty( $_GET['s'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'lead_magnet_history_search' ) ) {
			$search = sanitize_text_field( wp_unslash( $_GET['s'] ) );
		}

		$records     = $this->downloads->search( $search, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$total_items = $this->downloads->count( $search );

		$download_urls = array();
		foreach ( $records as $record ) {
			$download_urls[ $record->id ] = $this->downloader->get_admin_download_url( $record->id );
		}

		// Pagination keeps the search (and its nonce).
		$base_url = admin_url( 'admin.php?page=' . self::PAGE_HISTORY );
		if ( '' !== $search ) {
			$base_url = add_query_arg(
				array(
					's'        => rawurlencode( $search ),
					'_wpnonce' => wp_create_nonce( 'lead_magnet_history_search' ),
				),
				$base_url
			);
		}

		$this->render(
			'admin/history-page.php',
			array(
				'records'       => $records,
				'download_urls' => $download_urls,
				'export_url'    => $this->exporter->get_export_url( $search ),
				'search'        => $search,
				'page'          => $page,
				'total_items'   => $total_items,
				'total_pages'   => (int) ceil( $total_items / self::PER_PAGE ),
				'base_url'      => $base_url,
			)
		);
	}

	/**
	 * AJAX: delete a download record.
	 *
	 * @return void
	 */
	public function delete_record(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$record_id = isset( $_POST['record_id'] ) ? intval( $_POST['record_id'] ) : 0;
		if ( 0 >= $record_id ) {
			wp_send_json_error( array( 'message' => 'Invalid record ID' ) );
		}

		if ( ! $this->downloads->delete( $record_id ) ) {
			wp_send_json_error( array( 'message' => 'Database error' ) );
		}

		wp_send_json_success( array( 'message' => 'Record deleted successfully' ) );
	}

	/**
	 * Admin styles and scripts on the plugin pages (runs on admin_enqueue_scripts).
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$is_plugin_page = false;
		foreach ( array( self::PAGE_DASHBOARD, self::PAGE_SETTINGS, self::PAGE_HISTORY ) as $page ) {
			$is_plugin_page = $is_plugin_page || str_contains( $hook, $page );
		}
		if ( ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style( 'lead-magnet-admin', $this->paths->url( 'views/admin/assets/css/admin.css' ), array(), $this->paths->asset_version( 'views/admin/assets/css/admin.css' ) );
		wp_enqueue_script( 'lead-magnet-admin', $this->paths->url( 'views/admin/assets/js/admin.js' ), array( 'jquery' ), $this->paths->asset_version( 'views/admin/assets/js/admin.js' ), true );
		wp_localize_script(
			'lead-magnet-admin',
			'lead_magnet_admin_ajax',
			array(
				'ajax_url'           => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( self::NONCE ),
				'confirm_delete'     => __( 'Are you sure you want to delete this record?', 'lead-magnet-locker' ),
				'wp_max_upload_size' => (string) wp_max_upload_size(),
			)
		);
	}

	/**
	 * Warn when files are still in the legacy, guessable folder (runs on admin_notices).
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
	 * "Settings" link on the Plugins screen (filter plugin_action_links_{file}).
	 *
	 * @param array<int|string, string> $links Action links.
	 * @return array<int|string, string>
	 */
	public function plugin_action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE_DASHBOARD ) ) . '">' . esc_html__( 'Settings', 'lead-magnet-locker' ) . '</a>' );

		return $links;
	}

	/**
	 * Render an admin view, or a notice when it is missing.
	 *
	 * @param string               $view View path relative to views/.
	 * @param array<string, mixed> $vars Variables for the view.
	 * @return void
	 */
	private function render( string $view, array $vars ): void {
		if ( ! $this->paths->render( $view, $vars ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Missing admin page view file.', 'lead-magnet-locker' ) . '</p></div>';
		}
	}
}
