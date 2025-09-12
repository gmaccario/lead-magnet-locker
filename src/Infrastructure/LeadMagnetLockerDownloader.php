<?php

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

class LeadMagnetLockerDownloader
{
    private string $download_table_name = '';

    public function setTablesName(string $download_table_name): static
    {
        $this->download_table_name = $download_table_name;

        return $this;
    }

    /**
     * Handle secure file downloads
     */
    public function handle_download(): void
    {
        if (!isset($_GET['lead_magnet_download'])) {
            return;
        }

        // Verify nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['nonce'])), 'lead_magnet_download')) {
            wp_die('Security check failed.');
        }

        // Properly unslash and sanitize the download key
        $download_key = sanitize_text_field(wp_unslash($_GET['lead_magnet_download']));

        // Get download record from the database with proper table name handling
        global $wpdb;
        $table_name = $wpdb->prefix . $this->download_table_name;
        // Table names cannot be parameterized, but we ensure it's safe by using wpdb->prefix
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table_name}` WHERE download_key = %s",
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

        // Update download timestamp with proper table name
        $wpdb->update(
            $table_name,
            ['downloaded_at' => current_time('mysql')],
            ['id' => $record->id],
            ['%s'],
            ['%d']
        );

        // Serve the file - readfile() is appropriate for streaming downloads
        $file_info = pathinfo($file_path);
        $mime_type = $this->get_mime_type($file_info['extension']);

        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // readfile() streams the file directly without loading into memory
        // and doesn't require escaping as it's not HTML output
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
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
