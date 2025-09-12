<?php

declare(strict_types=1);

namespace LeadMagnet\Locker\Infrastructure;

use LeadMagnetLocker\LeadMagnetLocker;

class LeadMagnetLockerPluginBootstrap {

    private string $main_file = '';
    private string $download_table_name = '';
    private string $settings_table_name = '';

    public function __construct(
        private readonly LeadMagnetLocker $plugin,
        private readonly LeadMagnetLockerFileUploader $fileUploader,
        private readonly LeadMagnetLockerDownloader $downloader,
    ) {
        
    }

    public function setTablesName(string $download_table_name, string $settings_table_name): static
    {
        $this->download_table_name = $download_table_name;
        $this->settings_table_name = $settings_table_name;

        return $this;
    }

    public function setMainFile(string $main_file): static
    {
        $this->main_file = $main_file;

        return $this;
    }

    public function hook_into_wp(): void
    {
        // Hook into WordPress
        $this->add_shortcodes();
        $this->add_actions();
        $this->add_filters();

        // Activation hook
        register_activation_hook($this->main_file, [$this, 'create_database_tables']);
    }

    private function add_shortcodes(): void
    {
        add_shortcode('lead_magnet', [$this->plugin, 'display_form']);
    }

    private function add_actions(): void
    {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_submit_email', [$this->plugin, 'handle_email_submission']);
        add_action('wp_ajax_nopriv_submit_email', [$this->plugin, 'handle_email_submission']);
        add_action('init', [$this->downloader, 'handle_download']);

        // AJAX handlers for file management
        add_action('wp_ajax_lead_magnet_upload_file', [$this->fileUploader, 'ajax_upload_file']);
        add_action('wp_ajax_lead_magnet_delete_file', [$this->fileUploader, 'ajax_delete_file']);

        // Admin actions
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_ajax_delete_download_record', [$this->plugin, 'delete_download_record']);
    }

    private function add_filters(): void
    {
        add_filter('plugin_action_links_' . plugin_basename($this->main_file), [$this, 'add_plugin_action_links']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts(string $hook): void
    {
        if (!str_contains($hook, 'lead-magnet-locker') && !str_contains($hook, 'lead-magnet-history')) {
            return;
        }

        // Enqueue your custom admin script
        wp_enqueue_script(
            'lead-magnet-admin',
            plugin_dir_url($this->main_file) . 'views/admin/assets/js/admin.js', // create this file
            ['jquery'],
            '1.0',
            true
        );

        // Localize to your script handle
        wp_localize_script('lead-magnet-admin', 'lead_magnet_admin_ajax', [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('lead_magnet_admin_nonce'),
            'confirm_delete' => __('Are you sure you want to delete this record?', 'lead-magnet-locker'),
        ]);
    }

    /**
     * Admin init
     */
    public function admin_init(): void
    {
        register_setting(
            'lead_magnet_settings',
            'lead_magnet_options',
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default'           => [
                    'enable_date_trigger' => '0',
                    'start_date'          => '',
                    'end_date'            => '',
                    'start_time'          => '00:00',
                    'end_time'            => '23:59',
                    'timezone'            => get_option('timezone_string', 'UTC'),
                ],
            ]
        );
    }


    /**
     * Add admin menu
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            'Lead Magnet Locker',
            'Lead Magnet',
            'manage_options',
            'lead-magnet-locker',
            [$this->plugin, 'admin_page'],
            'dashicons-download',
            30
        );

        add_submenu_page(
            'lead-magnet-locker',
            'Settings',
            'Settings',
            'manage_options',
            'lead-magnet-settings',
            [$this->plugin, 'settings_page']
        );

        add_submenu_page(
            'lead-magnet-locker',
            'Download History',
            'History',
            'manage_options',
            'lead-magnet-history',
            [$this->plugin, 'history_page']
        );
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'lead_magnet_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lead_magnet_nonce')
        ]);
    }

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        // Create uploads directory for secure files
        $this->create_secure_directory();
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
        }
    }

    /**
     * Create database tables on activation
     */
    public function create_database_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Download table
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

        // Settings table
        $sql2 = "CREATE TABLE $this->settings_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value text,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);

        // Insert default settings
        $this->insert_default_settings();
    }

    /**
     * Insert default settings
     */
    private function insert_default_settings(): void
    {
        global $wpdb;

        $default_settings = [
            'enable_date_trigger' => '0',
            'start_date' => '',
            'end_date' => '',
            'start_time' => '00:00',
            'end_time' => '23:59',
            'timezone' => get_option('timezone_string', 'UTC')
        ];

        foreach ($default_settings as $key => $value) {
            $wpdb->replace(
                $this->settings_table_name,
                [
                    'setting_key' => $key,
                    'setting_value' => $value
                ],
                ['%s', '%s']
            );
        }
    }

    /**
     * Add settings link to plugin page
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=lead-magnet-settings') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
