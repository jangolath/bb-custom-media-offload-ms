<?php
/**
 * Plugin Name: BB Custom Media Offload MS
 * Description: Smart media offloading to Bunny.net for WordPress Multisite with support for local and nfs file retention
 * Version: 1.0.0
 * Author: BuddyBoss Advanced Enhancements
 * Network: true
 * Text Domain: bb-custom-media-offload-ms
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('BBCMO_VERSION', '1.0.0');
define('BBCMO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BBCMO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBCMO_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BBCMO_QUEUE_TABLE', 'bbcmo_offload_queue');

/**
 * Main plugin class
 */
class BB_Custom_Media_Offload_MS {
    
    // Singleton instance
    private static $instance = null;
    
    // Plugin components
    private $admin = null;
    private $core = null;
    private $queue = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to enforce singleton
     */
    private function __construct() {
        $this->includes();
        $this->init_components();
        $this->setup_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once BBCMO_PLUGIN_DIR . 'includes/class-bbcmo-admin.php';
        require_once BBCMO_PLUGIN_DIR . 'includes/class-bbcmo-media-core.php';
        require_once BBCMO_PLUGIN_DIR . 'includes/class-bbcmo-offload-queue.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->admin = new BBCMO_Media_Admin();
        $this->core = new BBCMO_Media_Core();
        $this->queue = new BBCMO_Offload_Queue();
    }
    
    /**
     * Setup plugin hooks
     */
    private function setup_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create queue table
        $this->queue->create_queue_table();
        
        // Initialize default settings if not exists
        if (empty(get_site_option('bbcmo_settings'))) {
            $defaults = array(
                'bunny_api_key' => '',
                'storage_zone' => '',
                'cdn_url' => '',
                'offload_delay' => 300,
                'local_file_types' => 'json',
                'nfs_base_path' => wp_upload_dir()['basedir'],
                'delete_local_after_offload' => false,
                'force_https' => true,
                'enable_for_sites' => array(),
            );
            
            update_site_option('bbcmo_settings', $defaults);
        }
        
        // Schedule offload task
        if (!wp_next_scheduled('bbcmo_process_media_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'bbcmo_process_media_queue');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled task
        $timestamp = wp_next_scheduled('bbcmo_process_media_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'bbcmo_process_media_queue');
        }
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'bb-custom-media-offload-ms',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Get settings
     */
    public function get_settings() {
        $defaults = array(
            'bunny_api_key' => '',
            'storage_zone' => '',
            'cdn_url' => '',
            'offload_delay' => 300,
            'local_file_types' => 'json',
            'nfs_base_path' => wp_upload_dir()['basedir'],
            'delete_local_after_offload' => false,
            'force_https' => true,
            'enable_for_sites' => array(),
        );
        
        $saved_settings = get_site_option('bbcmo_settings', array());
        return wp_parse_args($saved_settings, $defaults);
    }
}

/**
 * Returns the main instance of the plugin
 */
function BB_Custom_Media_Offload_MS() {
    return BB_Custom_Media_Offload_MS::get_instance();
}

// Initialize the plugin
add_action('plugins_loaded', 'BB_Custom_Media_Offload_MS');

/**
 * Add custom cron schedule
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['five_minutes'] = array(
        'interval' => 300,
        'display' => __('Every Five Minutes', 'bb-custom-media-offload-ms')
    );
    return $schedules;
});