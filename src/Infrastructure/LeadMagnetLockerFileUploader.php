<?php

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

class LeadMagnetLockerFileUploader
{
    private string $download_table_name = '';
    public function setTablesName(string $download_table_name): static
    {
        $this->download_table_name = $download_table_name;

        return $this;
    }

    /**
     * AJAX handler for file upload
     */
    public function ajax_upload_file(): void
    {
        $result = $this->handle_file_upload();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for file deletion
     */
    public function ajax_delete_file(): void
    {
        $filename = sanitize_file_name($_POST['filename']);
        $result = $this->handle_file_deletion($filename);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Handle file upload
     */
    public function handle_file_upload(): array
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['upload_nonce'], 'lead_magnet_upload')) {
            return [
                'success' => false,
                'message' => 'Security check failed.'
            ];
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => 'Insufficient permissions.'
            ];
        }

        // Check for upload errors
        if ($_FILES['lead_magnet_file']['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Upload error: ' . $this->get_upload_error_message($_FILES['lead_magnet_file']['error'])
            ];
        }

        $file = $_FILES['lead_magnet_file'];
        $file_name = sanitize_file_name($file['name']);
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];

        // Validate file type
        $allowed_types = [
            'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'jpg', 'jpeg', 'png', 'gif', 'txt', 'mp3', 'mp4'
        ];

        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_types)) {
            return ['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types)];
        }

        // Check file size (default 10MB limit)
        $max_size = apply_filters('lead_magnet_max_file_size', 10 * 1024 * 1024); // 10MB
        if ($file_size > $max_size) {
            return ['success' => false, 'message' => 'File too large. Maximum size: ' . $this->format_file_size($max_size)];
        }

        // Create the secure directory if it doesn't exist
        $this->create_secure_directory();

        // Set destination
        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/lead-magnet-files/';
        $destination = $secure_dir . $file_name;

        // Check if the file already exists
        if (file_exists($destination)) {
            // Generate unique filename
            $file_info = pathinfo($file_name);
            $counter = 1;
            do {
                $new_name = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
                $destination = $secure_dir . $new_name;
                $counter++;
            } while (file_exists($destination));
            $file_name = $new_name;
        }

        // Move uploaded file
        if (move_uploaded_file($file_tmp, $destination)) {
            return ['success' => true, 'message' => 'File uploaded successfully: ' . $file_name];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file.'];
        }
    }

    /**
     * Handle file deletion
     */
    public function handle_file_deletion(string $filename): array
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['delete_nonce'], 'lead_magnet_delete')) {
            return ['success' => false, 'message' => 'Security check failed.'];
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return ['success' => false, 'message' => 'Insufficient permissions.'];
        }

        $filename = sanitize_file_name($filename);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/lead-magnet-files/' . $filename;

        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'File not found.'];
        }

        // Check if the file is being used in any downloads
        global $wpdb;
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $this->download_table_name WHERE file_name = %s",
            $filename
        ));

        if ($usage_count > 0) {
            return ['success' => false, 'message' => 'Cannot delete file. It has been used in ' . $usage_count . ' downloads.'];
        }

        if (unlink($file_path)) {
            return ['success' => true, 'message' => 'File deleted successfully: ' . $filename];
        }

        return ['success' => false, 'message' => 'Failed to delete file.'];
    }

    /**
     * Create a secure directory for file storage
     */
    private function create_secure_directory(): void
    {
        $upload_dir = wp_upload_dir();
        $secure_dir = $upload_dir['basedir'] . '/lead-magnet-files/';

        if (!file_exists($secure_dir)) {
            wp_mkdir_p($secure_dir);

            // Create .htaccess to prevent direct access
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($secure_dir . '.htaccess', $htaccess_content);

            // Create index.php for additional security
            $index_content = "<?php\n// Silence is golden.\n";
            file_put_contents($secure_dir . 'index.php', $index_content);
        }
    }

    /**
     * Get to upload an error message
     */
    private function get_upload_error_message(int $error_code): string
    {
        return match ($error_code) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
            default => 'Unknown upload error',
        };
    }

    /**
     * Format file size for display
     */
    public function format_file_size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
