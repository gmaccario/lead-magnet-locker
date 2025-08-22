<?php

/**
 * Plugin Name: Lead Magnet Locker
 * Description: Collect emails in exchange for secure digital downloads. Protect your files, grow your list, and deliver assets automatically.
 * Version: 1.1.0
 * Author: G. Maccario
 * Text Domain: lead-magnet-locker
 */

declare(strict_types=1);

namespace LeadMagnetLocker;

use LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include Composer autoloader if the autoload file exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    error_log('LeadMagnet - Autoloader file not found at: ' . __DIR__ . '/vendor/autoload.php');
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

// Test if a class can be found
if (!class_exists('\LeadMagnet\Locker\Infrastructure\LeadMagnetLockerPluginBootstrap')) {
    error_log('LeadMagnet - Class NOT found by autoloader');
    return;
}

class LeadMagnetLocker {

    private string $download_table_name;
    private string $settings_table_name;

    public function __construct() {
        global $wpdb;

        $this->download_table_name = $wpdb->prefix . 'lead_magnet_downloads';
        $this->settings_table_name = $wpdb->prefix . 'lead_magnet_settings';

        // Hook into WordPress
        (new LeadMagnetLockerPluginBootstrap($this))
                ->setTablesName($this->download_table_name, $this->settings_table_name)
                ->setMainFile(__FILE__)
                ->hook_into_wp();
    }

    /**
     * Get setting value
     */
    private function get_setting($key, $default = '') {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
                "SELECT setting_value FROM $this->settings_table_name WHERE setting_key = %s",
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

        // Set timezone
        $old_timezone = date_default_timezone_get();
        date_default_timezone_set($timezone);

        $now = current_time('Y-m-d H:i:s');
        $start_datetime = $start_date . ' ' . $start_time . ':00';
        $end_datetime = $end_date . ' ' . $end_time . ':59';

        $is_visible = ($now >= $start_datetime && $now <= $end_datetime);

        // Restore timezone
        date_default_timezone_set($old_timezone);

        return $is_visible;
    }

    /**
     * Delete download record via AJAX
     */
    public function delete_download_record(): void
    {
        if (!wp_verify_nonce($_POST['nonce'], 'lead_magnet_admin_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $record_id = intval($_POST['record_id']);

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
     * Main admin page
     */
    public function admin_page(): void
    {
        global $wpdb;

        $total_downloads = $wpdb->get_var("SELECT COUNT(*) FROM $this->download_table_name");
        $unique_emails = $wpdb->get_var("SELECT COUNT(DISTINCT email) FROM $this->download_table_name");
        $completed_downloads = $wpdb->get_var("SELECT COUNT(*) FROM $this->download_table_name WHERE downloaded_at IS NOT NULL");

        $upload_message = '';
        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/lead-magnet-files/';
        if (is_dir($secure_dir)) {
            $files = array_diff(scandir($secure_dir), ['.', '..', '.htaccess']);
            if (!empty($files)) {
                $upload_message = '<h3>Available Files:</h3><ul>';
                foreach ($files as $file) {
                    $upload_message .= '<li>' . esc_html($file) . '</li>';
                }
                $upload_message .= '</ul>';
            } else {
                $upload_message .= '<p><em>No files uploaded yet.</em></p>';
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

        // Search functionality
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where_clause = '';
        $search_params = [];

        if (!empty($search)) {
            $where_clause = " WHERE email LIKE %s OR file_name LIKE %s";
            $search_params = ["%$search%", "%$search%"];
        }

        // Get total count
        $total_items = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $this->download_table_name" . $where_clause,
                $search_params
        ));

        // Get records
        $records = $wpdb->get_results(query: $wpdb->prepare(
                "SELECT * FROM $this->download_table_name" . $where_clause . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($search_params, [$per_page, $offset])
        ));

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
            return '<div class="lead-magnet-unavailable" style="padding: 20px; border: 2px solid #ccc; border-radius: 8px; text-align: center; color: #666;">' . $message . '</div>';
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
            return '<p><strong>Error:</strong> File not found. Please upload the file to wp-content/uploads/lead-magnet-files/</p>';
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
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'lead_magnet_nonce')) {
            wp_die('Security check failed');
        }

        // Check if the form should be visible
        if (!$this->is_form_visible()) {
            wp_send_json_error(['message' => 'This download is currently unavailable.']);
        }

        $email = sanitize_email($_POST['email']);
        $file = sanitize_file_name($_POST['file']);

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

    /**
     * Handle secure file downloads
     */
    public function handle_download(): void
    {
        if (!isset($_GET['lead_magnet_download'])) {
            return;
        }

        $download_key = sanitize_text_field($_GET['lead_magnet_download']);

        // Get download record from a database
        global $wpdb;
        $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $this->download_table_name WHERE download_key = %s",
                $download_key
        ));

        if (!$record) {
            wp_die('Invalid download link.');
        }

        // Check if a file exists
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/lead-magnet-files/' . $record->file_name;

        if (!file_exists($file_path)) {
            wp_die('File not found.');
        }

        // Update download timestamp
        $wpdb->update(
                $this->download_table_name,
                ['downloaded_at' => current_time('mysql')],
                ['id' => $record->id],
                ['%s'],
                ['%d']
        );

        // Serve the file
        $file_info = pathinfo($file_path);
        $mime_type = $this->get_mime_type($file_info['extension']);

        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($file_path);
        exit;
    }

    /**
     * Get MIME type based on file extension
     */
    private function get_mime_type($extension): string
    {
        $mime_types = [
                'pdf' => 'application/pdf',
                'zip' => 'application/zip',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                'mp3' => 'audio/mpeg',
                'mp4' => 'video/mp4'
        ];

        return $mime_types[strtolower($extension)] ?? 'application/octet-stream';
    }
}

// Initialize the plugin
new LeadMagnetLocker();
