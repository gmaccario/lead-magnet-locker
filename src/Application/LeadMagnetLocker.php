<?php
/**
 * Main application class for Lead Magnet Locker.
 *
 * Coordinates bootstrapping, admin pages, AJAX handlers,
 * and shortcode rendering.
 *
 * @file
 * @package LeadMagnetLocker
 * @subpackage Application
 * @since 1.1.0
 */

namespace LeadMagnet\Locker\Application;

use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerDownloader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerFileUploader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerHistoryExporter;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerStorage;

/**
 * Main plugin class.
 *
 * Responsible for registering hooks, rendering admin pages,
 * handling AJAX endpoints, and outputting public shortcodes.
 */
class LeadMagnetLocker {
	/**
	 * Name of the downloads table (with $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $download_table_name;
	/**
	 * Name of the settings table (with $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $settings_table_name;

	/**
	 * File uploader service.
	 *
	 * @var LeadMagnetLockerFileUploader
	 */

	private LeadMagnetLockerFileUploader $file_uploader;

	/**
	 * Secure downloader service.
	 *
	 * @var LeadMagnetLockerDownloader
	 */
	private LeadMagnetLockerDownloader $downloader;

	/**
	 * Protected file storage service.
	 *
	 * @var LeadMagnetLockerStorage
	 */
	private LeadMagnetLockerStorage $storage;

	/**
	 * Download history CSV exporter service.
	 *
	 * @var LeadMagnetLockerHistoryExporter
	 */
	private LeadMagnetLockerHistoryExporter $history_exporter;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;

		$this->download_table_name = $wpdb->prefix . 'lead_magnet_downloads';
		$this->settings_table_name = $wpdb->prefix . 'lead_magnet_settings';

		$this->storage = new LeadMagnetLockerStorage();

		$this->file_uploader = ( new LeadMagnetLockerFileUploader() )
			->set_tables_name( $this->download_table_name )
			->set_storage( $this->storage );

		$this->downloader = ( new LeadMagnetLockerDownloader() )
			->set_tables_name( $this->download_table_name )
			->set_storage( $this->storage );

		$this->history_exporter = ( new LeadMagnetLockerHistoryExporter() )
			->set_tables_name( $this->download_table_name );

		// Hook into WordPress.
		( new LeadMagnetLockerPluginBootstrap( $this, $this->file_uploader, $this->downloader, $this->history_exporter, $this->storage ) )
			->set_tables_name( $this->download_table_name, $this->settings_table_name )
			->set_main_file( LML_PLUGIN_FILE )
			->hook_into_wp();
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param string $default_value Default value if not set.
	 * @return string
	 */
	private function get_setting( string $key, string $default_value = '' ) {
		global $wpdb;
		// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT setting_value FROM {$this->settings_table_name} WHERE setting_key = %s",
				$key
			)
		);
		return ( null !== $value )
			? $value
			: $default_value;
	}

	/**
	 * Update setting value.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Setting value.
	 * @return void
	 */
	private function update_setting( string $key, string $value ): void {
		global $wpdb;
		$wpdb->replace(
			$this->settings_table_name,
			array(
				'setting_key'   => $key,
				'setting_value' => $value,
			),
			array( '%s', '%s' )
		);
	}


	/**
	 * Get the configured timezone, falling back to UTC when empty or invalid.
	 *
	 * @return string A valid timezone identifier.
	 */
	private function get_timezone(): string {
		$timezone = $this->get_setting( 'timezone' );

		return in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : 'UTC';
	}

	/**
	 * Check if the form should be visible based on date/time settings.
	 *
	 * @return bool
	 * @throws \DateInvalidTimeZoneException
	 */
	private function is_form_visible(): bool {
		$enable_date_trigger = $this->get_setting( 'enable_date_trigger' );

		if ( '1' !== $enable_date_trigger ) {
			return true; // Always visible if the date trigger is disabled.
		}

		$start_date = $this->get_setting( 'start_date' );
		$end_date   = $this->get_setting( 'end_date' );
		$start_time = $this->get_setting( 'start_time' );
		$end_time   = $this->get_setting( 'end_time' );
		$timezone   = $this->get_timezone();

		if ( empty( $start_date ) || empty( $end_date ) ) {
			return true; // Show if dates are not set.
		}

		// Use WordPress timezone functions instead of PHP's date_default_timezone_set.
		try {
			$wp_timezone    = new \DateTimeZone( $timezone );
			$now            = new \DateTime( current_time( 'mysql' ), $wp_timezone );
			$start_datetime = new \DateTime( $start_date . ' ' . $start_time . ':00', $wp_timezone );
			$end_datetime   = new \DateTime( $end_date . ' ' . $end_time . ':59', $wp_timezone );
		} catch ( \Exception $e ) {
			return true; // Malformed schedule values: fail open, as when dates are not set.
		}

		return ( $now >= $start_datetime && $now <= $end_datetime );
	}


	/**
	 * Delete download record via AJAX
	 *
	 * @return void
	 */
	public function leadmalo_delete_download_record(): void {
		// Validate and sanitize nonce.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( sanitize_text_field( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'lead_magnet_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Validate and sanitize record_id.
		$record_id = isset( $_POST['record_id'] ) ? intval( $_POST['record_id'] ) : 0;
		if ( 0 >= $record_id ) {
			wp_send_json_error( array( 'message' => 'Invalid record ID' ) );
		}

		global $wpdb;
		$result = $wpdb->delete(
			$this->download_table_name,
			array( 'id' => $record_id ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'Database error' ) );
		}

		wp_send_json_success( array( 'message' => 'Record deleted successfully' ) );
	}


	/**
	 * Enhanced admin page with file upload
	 *
	 * @return void
	 */
	public function admin_page(): void {
		global $wpdb;

		// Handle file upload with nonce verification.
		$upload_message = '';
		if ( isset( $_POST['upload_file'] ) && isset( $_FILES['lead_magnet_file'] ) &&
			check_admin_referer( 'lead_magnet_upload', 'upload_nonce' ) ) {
			$upload_result  = $this->file_uploader->handle_file_upload();
			$upload_message = $upload_result['message'];
		}

		// Handle file deletion with nonce verification.
		if ( isset( $_POST['delete_file'] ) && isset( $_POST['file_to_delete'] ) &&
			check_admin_referer( 'lead_magnet_delete', 'delete_nonce' ) ) {
			$file_to_delete = wp_unslash( sanitize_file_name( $_POST['file_to_delete'] ) );
			$delete_result  = $this->file_uploader->handle_file_deletion( $file_to_delete );
			$upload_message = $delete_result['message'];
		}

		// Use prepared statements for database queries.
		// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_downloads = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->download_table_name}" );
		// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique_emails = $wpdb->get_var( "SELECT COUNT(DISTINCT email) FROM {$this->download_table_name}" );
		// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$completed_downloads = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->download_table_name} WHERE downloaded_at IS NOT NULL" );

		// Protect files added to the legacy folder since the last migration (e.g. via FTP).
		if ( $this->storage->unprotected_files() ) {
			$this->storage->migrate();
		}

		// Get uploaded files.
		$files = array();
		foreach ( $this->storage->list_files() as $file ) {
			$file_path = $this->storage->path( $file );
			if ( null === $file_path ) {
				continue;
			}
			$files[] = array(
				'name'     => $file,
				'size'     => $this->file_uploader->format_file_size( (int) filesize( $file_path ) ),
				'modified' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $file_path ) ), // Use gmdate instead of date.
			);
		}

		$view = LML_PLUGIN_DIR . 'views/admin/admin-page.php';
		if ( is_readable( $view ) ) {
			include_once $view;
		} else {
			// Fails safely in admin with a helpful message.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Missing admin page view file.', 'lead-magnet-locker' ) . '</p></div>';
		}
	}


	/**
	 * Settings page
	 *
	 * @return void
	 */
	public function settings_page(): void {
		if ( isset( $_POST['submit'] ) ) {
			// Security: nonce + capability.
			if ( ! isset( $_POST['settings_nonce'] ) || ! wp_verify_nonce( wp_unslash( sanitize_text_field( $_POST['settings_nonce'] ) ), 'lead_magnet_settings' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'lead-magnet-locker' ) );
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'lead-magnet-locker' ) );
			}

			$this->update_setting( 'enable_date_trigger', isset( $_POST['enable_date_trigger'] ) ? '1' : '0' );
			$this->update_setting( 'start_date', sanitize_text_field( $_POST['start_date'] ) );
			$this->update_setting( 'end_date', sanitize_text_field( $_POST['end_date'] ) );
			$this->update_setting( 'start_time', sanitize_text_field( $_POST['start_time'] ) );
			$this->update_setting( 'end_time', sanitize_text_field( $_POST['end_time'] ) );
			$timezone = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : '';
			$this->update_setting( 'timezone', in_array( $timezone, timezone_identifiers_list(), true ) ? $timezone : 'UTC' );

			echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
		}

		$enable_date_trigger = $this->get_setting( 'enable_date_trigger' );
		$start_date          = $this->get_setting( 'start_date' );
		$end_date            = $this->get_setting( 'end_date' );
		$start_time          = $this->get_setting( 'start_time' );
		$end_time            = $this->get_setting( 'end_time' );
		$timezone            = $this->get_timezone(); // Default: UTC (Greenwich Mean Time).

		$view = LML_PLUGIN_DIR . 'views/admin/settings-page.php';
		if ( is_readable( $view ) ) {
			include_once $view;
		} else {
			// Fails safely in admin with a helpful message.
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Missing admin page view file.', 'lead-magnet-locker' ) . '</p></div>';
		}
	}

	/**
	 * History page
	 *
	 * @return void
	 */
	public function history_page(): void {
		global $wpdb;

		// Handle bulk actions and pagination.
		$per_page = 20;
		$page     = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset   = ( $page - 1 ) * $per_page;

		// Search functionality with proper validation and sanitization.
		$search = '';
		if ( ! empty( $_GET['s'] ) ) {
			// Verify nonce passed in the GET form to validate the origin of the request.
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( sanitize_text_field( $_GET['_wpnonce'] ) ), 'lead_magnet_history_search' ) ) {
				$search = wp_unslash( sanitize_text_field( $_GET['s'] ) );
			}
		}

		// Build query with proper prepared statements.
		$table_name = $wpdb->prefix . 'lead_magnet_downloads';

		if ( ! empty( $search ) ) {
			// Escape LIKE wildcards so "%" and "_" are searched literally.
			$like = '%' . $wpdb->esc_like( $search ) . '%';

			// Get total count with search.
			// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_items = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE email LIKE %s OR file_name LIKE %s",
					$like,
					$like
				)
			);

			// Get records with search.
			$records = $wpdb->get_results(
				$wpdb->prepare(
				// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT * FROM {$table_name} WHERE email LIKE %s OR file_name LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$like,
					$like,
					$per_page,
					$offset
				)
			);
		} else {
			// Get total count without search.
			// Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

			// Get records without search.
			$records = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);
		}

		$total_pages = ceil( $total_items / $per_page );

		$view = LML_PLUGIN_DIR . 'views/admin/history-page.php';
		if ( is_readable( $view ) ) {
			include_once $view;
		} else {
			// Fails safely in admin with a helpful message
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Missing admin page view file.', 'lead-magnet-locker' ) . '</p></div>';
		}
	}

	/**
	 * Shortcode error message, shown to administrators only.
	 *
	 * Visitors get nothing instead of a technical message in the middle of the content.
	 *
	 * @param string $message Error message.
	 * @return string HTML for administrators, empty string for everyone else.
	 */
	private function shortcode_error( string $message ): string {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		return '<p class="lead-magnet-error-admin"><strong>Lead Magnet Locker:</strong> ' . esc_html( $message ) . ' <em>(' . esc_html__( 'Only administrators see this message.', 'lead-magnet-locker' ) . ')</em></p>';
	}

	/**
	 * Display the email capture form via shortcode
	 * Usage: [lead_magnet file="your-file.pdf" title="Download Free Guide"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string|bool
	 * @throws \DateInvalidTimeZoneException
	 */
	public function display_form( array|string $atts ): bool|string {
		// Check if the form should be visible based on date/time settings.
		if ( ! $this->is_form_visible() ) {
			$message = 'This download is currently unavailable.';
			return '<div class="lead-magnet-unavailable" style="padding: 20px; border: 2px solid #ccc; border-radius: 8px; text-align: center; color: #666;">' . esc_html( $message ) . '</div>';
		}

		$atts = shortcode_atts(
			array(
				'file'        => '',
				'title'       => 'Get Your Free Download',
				'description' => 'Enter your email address to receive your free download.',
				'button_text' => 'Get Download Link',
			),
			$atts
		);

		if ( empty( $atts['file'] ) ) {
			return $this->shortcode_error( 'Please specify a file in the shortcode.' );
		}

		// Resolve the file inside the protected storage (blocks "../").
		$file_path = $this->storage->path( (string) $atts['file'] );
		if ( null === $file_path ) {
			return $this->shortcode_error( 'File not found. Please upload the file first from the WordPress admin dashboard.' );
		}

		// The form carries an opaque token, never the file name.
		$file_token = $this->storage->token( basename( $file_path ) );

		ob_start();

		$view = LML_PLUGIN_DIR . 'views/public/lead-magnet-form.php';
		if ( is_readable( $view ) ) {
			include_once $view;
		} else {
			// Fails safely with a helpful message for admins only.
			echo $this->shortcode_error( 'Missing form view file.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in shortcode_error().
		}

		return ob_get_clean();
	}

	/**
	 * Handle email submission via AJAX
	 *
	 * @return void
	 * @throws \DateInvalidTimeZoneException
	 */
	public function handle_email_submission(): void {
		// Verify nonce with proper validation and sanitization.
		$nonce = isset( $_POST['nonce'] ) ? wp_unslash( sanitize_text_field( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'leadmalo_public_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check if the form should be visible.
		if ( ! $this->is_form_visible() ) {
			wp_send_json_error( array( 'message' => 'This download is currently unavailable.' ) );
		}

		// Validate and sanitize form inputs.
		$email = isset( $_POST['email'] ) ? wp_unslash( sanitize_email( $_POST['email'] ) ) : '';
		$token = isset( $_POST['lmf'] ) ? sanitize_text_field( wp_unslash( $_POST['lmf'] ) ) : '';
		$file  = '' !== $token ? (string) $this->storage->name_from_token( $token ) : '';

		/**
		 * Accept the pre-1.3.0 "file" field so pages cached before the update keep working.
		 *
		 * Safe once files live in the private folder: knowing a name gives no URL access.
		 *
		 * @param bool $accept Whether to accept the legacy field. Default true.
		 */
		if ( '' === $file && '' === $token && isset( $_POST['file'] ) && apply_filters( 'lead_magnet_accept_legacy_file_field', true ) ) {
			$file = sanitize_file_name( wp_unslash( $_POST['file'] ) );
		}

		// Validate email.
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}

		// Check if a file exists.
		$file_path = '' !== $file ? $this->storage->path( $file ) : null;
		if ( null === $file_path ) {
			wp_send_json_error( array( 'message' => 'File not found.' ) );
		}
		$file = basename( $file_path );

		// Generate unique download key.
		$download_key = wp_generate_password( 32, false );

		// Save to a database.
		global $wpdb;
		$result = $wpdb->insert(
			$this->download_table_name,
			array(
				'email'        => $email,
				'file_name'    => $file,
				'download_key' => $download_key,
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => 'Database error. Please try again.' ) );
		}

		// Create download URL.
		$download_nonce = wp_create_nonce( 'lead_magnet_download_' . $download_key );
		$download_url   = add_query_arg(
			array(
				'lead_magnet_download' => $download_key,
				'lmd-nonce'            => $download_nonce,
			),
			home_url( '/' )
		);

		wp_send_json_success(
			array(
				'message'      => 'Success! Your download is ready.',
				'download_url' => $download_url,
			)
		);
	}
}
