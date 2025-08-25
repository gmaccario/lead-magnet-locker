<?php
/**
 * Uninstall routine for Lead Magnet Locker
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// We will remove:
// - Custom DB tables: {prefix}lead_magnet_downloads, {prefix}lead_magnet_settings
// - Option (if any): lead_magnet_options
// - Uploads directory used by the plugin: wp-uploads/lead-magnet-files/

/**
 * Recursively delete a directory.
 */
function lml_rrmdir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            lml_rrmdir($path);
        } else {
            // Attempt to make writable then unlink
            if (!is_writable($path)) {
                @chmod($path, 0664);
            }
            @unlink($path);
        }
    }

    // Attempt to make writable and remove the directory
    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }
    @rmdir($dir);
}

/**
 * Perform cleanup for a single site (works for single and multisite when switch_to_blog is used).
 */
function lml_cleanup_site(): void {
    global $wpdb;

    // Drop custom tables
    $download_table = $wpdb->prefix . 'lead_magnet_downloads';
    $settings_table = $wpdb->prefix . 'lead_magnet_settings';

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `$download_table`");
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query("DROP TABLE IF EXISTS `$settings_table`");

    // Delete any related options (the plugin stores settings in a custom table, but be safe)
    delete_option('lead_magnet_options');

    // Remove uploads directory used by the plugin
    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['basedir'])) {
        $secure_dir = rtrim($upload_dir['basedir'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lead-magnet-files';

        // Extra safety: ensure we only delete the exact plugin folder under uploads
        if (is_dir($secure_dir) && str_ends_with($secure_dir, 'lead-magnet-files')) {
            lml_rrmdir($secure_dir);
        }
    }
}

if (function_exists('is_multisite') && is_multisite()) {
    global $wpdb;
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");

    if (is_array($blog_ids)) {
        $current_blog_id = get_current_blog_id();
        foreach ($blog_ids as $blog_id) {
            switch_to_blog((int) $blog_id);
            lml_cleanup_site();
        }
        // Restore current blog context
        switch_to_blog((int) $current_blog_id);
    }
} else {
    lml_cleanup_site();
}
