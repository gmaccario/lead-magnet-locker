<?php

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Ensure WP_Filesystem is loaded and initialized.
 *
 * @return WP_Filesystem_Base|false
 */
function leadmalo_get_wp_filesystem() {
    global $wp_filesystem;

    if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
        return $wp_filesystem;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    // Try to initialize with direct access; WordPress will prompt for creds if needed (in admin).
    $creds = request_filesystem_credentials( admin_url() );
    if ( ! WP_Filesystem( $creds ) ) {
        return false; // Fall back paths below still use safe WP wrappers where possible.
    }

    return $wp_filesystem;
}

/**
 * Recursive remove for the plugin's upload directory using WP_Filesystem.
 *
 * @param string $dir Absolute path to directory to remove.
 * @return void
 */
function leadmalo_rrmdir( $dir ): void
{
    $fs = leadmalo_get_wp_filesystem();

    // If FS is available, prefer it (with recursive rmdir).
    if ( $fs && $fs->is_dir( $dir ) ) {
        // Make sure we can touch it before delete (quietly attempt chmod; ignore failures).
        $fs->chmod( $dir, FS_CHMOD_DIR );

        // Many FS transports support recursive removal directly.
        $fs->rmdir( $dir, true );
        return;
    }

    // Fallback (no FS): still avoid discouraged functions by using WP helpers where possible.
    if ( is_dir( $dir ) ) {
        // Walk the directory tree manually.
        $items = scandir( $dir );
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                if ( $item === '.' || $item === '..' ) {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $item;

                if ( is_dir( $path ) ) {
                    leadmalo_rrmdir( $path ); // recurse
                } else {
                    // Use WP core helper for files.
                    wp_delete_file( $path );
                }
            }
        }

        // Finally remove the (now empty) directory. If WP_Filesystem wasn't available,
        // we have to use PHP as a last resort—but by now only directories remain.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
        @rmdir( $dir );
    }
}

/**
 * Cleanup logic per site.
 */
function leadmalo_cleanup_site() {
    global $wpdb;

    $download_table = $wpdb->prefix . 'lead_magnet_downloads';
    $settings_table = $wpdb->prefix . 'lead_magnet_settings';

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are composed from $wpdb->prefix
    $wpdb->query( "DROP TABLE IF EXISTS `$download_table`" );
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    $wpdb->query( "DROP TABLE IF EXISTS `$settings_table`" );

    // Delete any related options (the plugin stores settings in a custom table, but be safe)
    delete_option( 'lead_magnet_options' );

    // Remove uploads directory used by the plugin
    $upload_dir = wp_upload_dir();
    if ( ! empty( $upload_dir['basedir'] ) ) {
        $secure_dir = rtrim( $upload_dir['basedir'], DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'lead-magnet-files';

        // Extra safety: ensure we only delete the exact plugin folder under uploads
        if ( is_dir( $secure_dir ) && str_ends_with( $secure_dir, 'lead-magnet-files' ) ) {
            // If you previously checked writability with is_writable(), switch to wp_is_writable().
            if ( function_exists( 'wp_is_writable' ) ) {
                if ( wp_is_writable( $secure_dir ) ) {
                    leadmalo_rrmdir( $secure_dir );
                } else {
                    // Try to make it writable using WP_Filesystem before delete.
                    $fs = leadmalo_get_wp_filesystem();
                    if ( $fs ) {
                        $fs->chmod( $secure_dir, FS_CHMOD_DIR );
                        leadmalo_rrmdir( $secure_dir );
                    } else {
                        // As a last ditch, attempt deletion anyway (rrmdir handles fallbacks).
                        leadmalo_rrmdir( $secure_dir );
                    }
                }
            } else {
                // Older WP: fallback directly to removal (rrmdir handles FS where available).
                leadmalo_rrmdir( $secure_dir );
            }
        }
    }
}

// --- Multisite aware cleanup (unchanged) ---
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
    global $wpdb;

    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );

    if ( is_array( $blog_ids ) ) {
        $current_blog_id = get_current_blog_id();

        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( (int) $blog_id );
            leadmalo_cleanup_site();
        }

        // Restore current blog context
        switch_to_blog( (int) $current_blog_id );
    }
} else {
    leadmalo_cleanup_site();
}
