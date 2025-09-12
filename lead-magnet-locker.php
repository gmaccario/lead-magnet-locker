<?php

/**
 * Plugin Name: Lead Magnet Locker
 * Description: Collect emails in exchange for secure digital downloads. Protect your files, grow your list, and deliver assets automatically.
 * Version: 1.1.0
 * Author: G. Maccario
 * Author URI: https://www.giuseppemaccario.com/
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lead-magnet-locker
 *
 * Requires PHP: 8.1
 * Requires at least: 6.7
 * Tested up to: 6.7.3
 */

declare(strict_types=1);

namespace LeadMagnetLocker;

use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerDownloader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerFileUploader;
use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include Composer autoloader if the autoload file exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Only log in debug mode, not in production
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('LeadMagnet - Autoloader file not found at: ' . __DIR__ . '/vendor/autoload.php');
    }
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Test if a class can be found
if (!class_exists('\LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap')) {
    // Only log in debug mode, not in production
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('LeadMagnet - Class NOT found by autoloader');
    }
    return;
}

class LeadMagnetLocker {

    private string $download_table_name;
    private string $settings_table_name;

    private LeadMagnetLockerFileUploader $fileUploader;
    private LeadMagnetLockerDownloader $downloader;

    public function __construct() {
        global $wpdb;

        $this->download_table_name = $wpdb->prefix . 'lead_magnet_downloads';
        $this->settings_table_name = $wpdb->prefix . 'lead_magnet_settings';

        $this->fileUploader = (new LeadMagnetLockerFileUploader())
            ->setTablesName($this->download_table_name);

        $this->downloader = (new LeadMagnetLockerDownloader())
            ->setTablesName($this->download_table_name);

        // Hook into WordPress
        (new LeadMagnetLockerPluginBootstrap($this, $this->fileUploader, $this->downloader))
            ->setTablesName($this->download_table_name, $this->settings_table_name)
            ->setMainFile(__FILE__)
            ->hook_into_wp();
    }

    /**
     * Get setting value
     */
    private function get_setting($key, $default = '') {
        global $wpdb;
        // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$this->settings_table_name} WHERE setting_key = %s",
            $key
        ));
        return $value !== null ? $value : $default;
    }

    /**
     * Update setting value
     */
    private function update_setting($key, $value): void
    {
        global $wpdb;
        $wpdb->replace(
            $this->settings_table_name,
            [
                'setting_key' => $key,
                'setting_value' => $value
            ],
            ['%s', '%s']
        );
    }

    /**
     * Check if the form should be visible based on date/time settings
     */
    private function is_form_visible(): bool
    {
        $enable_date_trigger = $this->get_setting('enable_date_trigger');

        if ($enable_date_trigger !== '1') {
            return true; // Always visible if the date trigger is disabled
        }

        $start_date = $this->get_setting('start_date');
        $end_date = $this->get_setting('end_date');
        $start_time = $this->get_setting('start_time');
        $end_time = $this->get_setting('end_time');
        $timezone = $this->get_setting('timezone', 'UTC');

        if (empty($start_date) || empty($end_date)) {
            return true; // Show if dates are not set
        }

        // Use WordPress timezone functions instead of PHP's date_default_timezone_set
        $wp_timezone = new \DateTimeZone($timezone);
        $now = new \DateTime(current_time('mysql'), $wp_timezone);
        $start_datetime = new \DateTime($start_date . ' ' . $start_time . ':00', $wp_timezone);
        $end_datetime = new \DateTime($end_date . ' ' . $end_time . ':59', $wp_timezone);

        return ($now >= $start_datetime && $now <= $end_datetime);
    }

    /**
     * Delete download record via AJAX
     */
    public function delete_download_record(): void
    {
        // Validate and sanitize nonce
        $nonce = isset($_POST['nonce']) ? wp_unslash(sanitize_text_field($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lead_magnet_admin_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Validate and sanitize record_id
        $record_id = isset($_POST['record_id']) ? intval($_POST['record_id']) : 0;
        if ($record_id <= 0) {
            wp_send_json_error(['message' => 'Invalid record ID']);
        }

        global $wpdb;
        $result = $wpdb->delete(
            $this->download_table_name,
            ['id' => $record_id],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error(['message' => 'Database error']);
        }

        wp_send_json_success(['message' => 'Record deleted successfully']);
    }

    /**
     * Enhanced admin page with file upload
     */
    public function admin_page(): void
    {
        global $wpdb;

        // Handle file upload with nonce verification
        $upload_message = '';
        if (isset($_POST['upload_file']) && isset($_FILES['lead_magnet_file']) &&
            check_admin_referer('lead_magnet_upload', 'upload_nonce')) {
            $upload_result = $this->fileUploader->handle_file_upload();
            $upload_message = $upload_result['message'];
        }

        // Handle file deletion with nonce verification
        if (isset($_POST['delete_file']) && isset($_POST['file_to_delete']) &&
            check_admin_referer('lead_magnet_delete', 'delete_nonce')) {
            $file_to_delete = wp_unslash(sanitize_file_name($_POST['file_to_delete']));
            $delete_result = $this->fileUploader->handle_file_deletion($file_to_delete);
            $upload_message = $delete_result['message'];
        }

        // Use prepared statements for database queries
        // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_downloads = $wpdb->get_var("SELECT COUNT(*) FROM {$this->download_table_name}");
        // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $unique_emails = $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM {$this->download_table_name}");
        // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $completed_downloads = $wpdb->get_var("SELECT COUNT(*) FROM {$this->download_table_name} WHERE downloaded_at IS NOT NULL");

        // Get uploaded files
        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/lead-magnet-files/';
        $files = [];
        if (is_dir($secure_dir)) {
            $all_files = array_diff(scandir($secure_dir), ['.', '..', '.htaccess']);
            foreach ($all_files as $file) {
                $file_path = $secure_dir . $file;
                $files[] = [
                    'name' => $file,
                    'size' => $this->fileUploader->format_file_size(filesize($file_path)),
                    'modified' => gmdate('Y-m-d H:i:s', filemtime($file_path)) // Use gmdate instead of date
                ];
            }
        }

        include_once('views/admin/admin-page.php');
    }

    /**
     * Settings page
     */
    public function settings_page(): void
    {
        if (isset($_POST['submit'])) {
            $this->update_setting('enable_date_trigger', isset($_POST['enable_date_trigger']) ? '1' : '0');
            $this->update_setting('start_date', sanitize_text_field($_POST['start_date']));
            $this->update_setting('end_date', sanitize_text_field($_POST['end_date']));
            $this->update_setting('start_time', sanitize_text_field($_POST['start_time']));
            $this->update_setting('end_time', sanitize_text_field($_POST['end_time']));
            $this->update_setting('timezone', sanitize_text_field($_POST['timezone']));

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        $enable_date_trigger = $this->get_setting('enable_date_trigger');
        $start_date = $this->get_setting('start_date');
        $end_date = $this->get_setting('end_date');
        $start_time = $this->get_setting('start_time');
        $end_time = $this->get_setting('end_time');
        $timezone = $this->get_setting('timezone');

        include_once('views/admin/settings-page.php');
    }

    /**
     * History page
     */
    public function history_page(): void
    {
        global $wpdb;

        // Handle bulk actions and pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Search functionality with proper validation and sanitization
        $search = '';
        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $search = wp_unslash(sanitize_text_field($_GET['s']));
        }

        // Build query with proper prepared statements
        $table_name = $wpdb->prefix . 'lead_magnet_downloads';

        if (!empty($search)) {
            // Get total count with search
            // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_items = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE email LIKE %s OR file_name LIKE %s",
                "%{$search}%",
                "%{$search}%"
            ));

            // Get records with search
            $records = $wpdb->get_results($wpdb->prepare(
            // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table_name} WHERE email LIKE %s OR file_name LIKE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                "%{$search}%",
                "%{$search}%",
                $per_page,
                $offset
            ));
        } else {
            // Get total count without search
            // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

            // Get records without search
            $records = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ));
        }

        $total_pages = ceil($total_items / $per_page);

        include_once('views/admin/history-page.php');
    }

    /**
     * Display the email capture form via shortcode
     * Usage: [lead_magnet file="your-file.pdf" title="Download Free Guide"]
     */
    public function display_form($atts): bool|string
    {
        // Check if the form should be visible based on date/time settings
        if (!$this->is_form_visible()) {
            $message = 'This download is currently unavailable.';
            return '<div class="lead-magnet-unavailable" style="padding: 20px; border: 2px solid #ccc; border-radius: 8px; text-align: center; color: #666;">' . esc_html($message) . '</div>';
        }

        $atts = shortcode_atts([
            'file' => '',
            'title' => 'Get Your Free Download',
            'description' => 'Enter your email address to receive your free download.',
            'button_text' => 'Get Download Link'
        ], $atts);

        if (empty($atts['file'])) {
            return '<p><strong>Error:</strong> Please specify a file in the shortcode.</p>';
        }

        // Check if a file exists in a secure directory
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/lead-magnet-files/' . $atts['file'];

        if (!file_exists($file_path)) {
            return '<p><strong>Error:</strong> File not found. Please upload the file first from the WordPress admin dashboard</p>';
        }

        ob_start();

        include_once('views/public/lead-magnet-form.php');

        return ob_get_clean();
    }

    /**
     * Handle email submission via AJAX
     */
    public function handle_email_submission(): void
    {
        // Verify nonce with proper validation and sanitization
        $nonce = isset($_POST['nonce']) ? wp_unslash(sanitize_text_field($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lead_magnet_nonce')) {
            wp_die('Security check failed');
        }

        // Check if the form should be visible
        if (!$this->is_form_visible()) {
            wp_send_json_error(['message' => 'This download is currently unavailable.']);
        }

        // Validate and sanitize form inputs
        $email = isset($_POST['email']) ? wp_unslash(sanitize_email($_POST['email'])) : '';
        $file = isset($_POST['file']) ? wp_unslash(sanitize_file_name($_POST['file'])) : '';

        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Please enter a valid email address.']);
        }

        // Check if a file exists
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/lead-magnet-files/' . $file;

        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'File not found.']);
        }

        // Generate unique download key
        $download_key = wp_generate_password(32, false);

        // Save to a database
        global $wpdb;
        $result = $wpdb->insert(
            $this->download_table_name,
            [
                'email' => $email,
                'file_name' => $file,
                'download_key' => $download_key
            ],
            ['%s', '%s', '%s']
        );

        if ($result === false) {
            wp_send_json_error(['message' => 'Database error. Please try again.']);
        }

        // Create download URL
        $download_url = home_url('/?lead_magnet_download=' . $download_key);

        wp_send_json_success([
            'message' => 'Success! Your download is ready.',
            'download_url' => $download_url
        ]);
    }
}

// Initialize the plugin
new LeadMagnetLocker();
