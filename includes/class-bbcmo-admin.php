<?php
/**
 * Admin functionality
 * 
 * @package BB_Custom_Media_Offload_MS
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BBCMO_Media_Admin {
    
    // Settings
    private $settings = array();
    
    /**
     * Constructor
     * 
     * @param array $settings Settings passed directly to avoid circular dependencies
     */
    public function __construct($settings = null) {
        // If settings provided, use them directly (avoiding circular dependency)
        if ($settings !== null) {
            $this->settings = $settings;
        } 
        // Otherwise fall back to getting from main class (for backward compatibility)
        else {
            $this->settings = BB_Custom_Media_Offload_MS()->get_settings();
        }
        
        // Set up hooks
        $this->setup_bbcmo_admin_hooks();
    }
    
    /**
     * Setup admin hooks
     */
    private function setup_bbcmo_admin_hooks() {
        // Network admin menu
        add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        
        // Save network settings
        add_action('network_admin_edit_bbcmo_save_settings', array($this, 'save_network_settings'));
        
        // Site admin menu
        add_action('admin_menu', array($this, 'add_site_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_bbcmo_admin_assets'));
        
        // Ajax actions
        add_action('wp_ajax_bbcmo_process_now', array($this, 'ajax_process_now'));
        add_action('wp_ajax_bbcmo_retry_failed', array($this, 'ajax_retry_failed'));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_bbcmo_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'bb-custom-media-offload-ms') === false) {
            return;
        }
        
        wp_enqueue_style(
            'bbcmo-admin-styles',
            BBCMO_PLUGIN_URL . 'assets/css/bbcmo-admin.css',
            array(),
            BBCMO_VERSION
        );
        
        wp_enqueue_script(
            'bbcmo-admin-scripts',
            BBCMO_PLUGIN_URL . 'assets/js/bbcmo-admin.js',
            array('jquery'),
            BBCMO_VERSION,
            true
        );
        
        wp_localize_script('bbcmo-admin-scripts', 'bbcmo_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bbcmo_admin_nonce'),
            'processing_text' => __('Processing...', 'bb-custom-media-offload-ms'),
            'process_success' => __('Queue processing initiated.', 'bb-custom-media-offload-ms'),
            'retry_success' => __('Failed items queued for retry.', 'bb-custom-media-offload-ms'),
            'error_message' => __('An error occurred. Please try again.', 'bb-custom-media-offload-ms')
        ));
    }
    
    /**
     * Add menu item to BAE menu in network admin
     */
    public function add_network_admin_menu() {
        // Make sure the parent exists (fallback in case BAE isn't active)
        if (!menu_page_url('buddyboss-advanced-enhancements', false)) {
            // Create parent menu if it doesn't exist
            add_menu_page(
                __('BuddyBoss Advanced Enhancements', 'bb-custom-media-offload-ms'),
                __('BB Advanced', 'bb-custom-media-offload-ms'),
                'manage_network_options',
                'buddyboss-advanced-enhancements',
                function() {
                    echo '<div class="wrap">';
                    echo '<h1>' . __('BuddyBoss Advanced Enhancements', 'bb-custom-media-offload-ms') . '</h1>';
                    echo '<p>' . __('Welcome to BuddyBoss Advanced Enhancements. Use the submenu to access specific features.', 'bb-custom-media-offload-ms') . '</p>';
                    echo '</div>';
                },
                'dashicons-buddicons-buddypress-logo',
                3
            );
        }
        
        // Add this plugin as a submenu
        add_submenu_page(
            'buddyboss-advanced-enhancements', // Parent slug
            __('Media Offload', 'bb-custom-media-offload-ms'),
            __('Media Offload', 'bb-custom-media-offload-ms'),
            'manage_network_options',
            'bb-custom-media-offload-ms',
            array($this, 'render_network_settings_page')
        );
    }
    
    /**
     * Add site admin menu for status
     */
    public function add_site_admin_menu() {
        // Only add if this site is enabled
        $current_blog_id = get_current_blog_id();
        
        if (empty($this->settings['enable_for_sites']) || in_array($current_blog_id, $this->settings['enable_for_sites'])) {
            add_submenu_page(
                'options-general.php',
                __('Media Offload Status', 'bb-custom-media-offload-ms'),
                __('Media Offload', 'bb-custom-media-offload-ms'),
                'manage_options',
                'bb-custom-media-offload-ms',
                array($this, 'render_site_status_page')
            );
        }
    }
    
    /**
     * Render network settings page
     */
    public function render_network_settings_page() {
        // Load sites for multisite selector
        $sites = get_sites(array(
            'number' => 100, // Adjust as needed
        ));
        ?>
        <div class="wrap bbcmo-admin-wrap">
            <h1><?php _e('BB Custom Media Offload Settings', 'bb-custom-media-offload-ms'); ?></h1>
            
            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings updated successfully.', 'bb-custom-media-offload-ms'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="edit.php?action=bbcmo_save_settings">
                <?php wp_nonce_field('bbcmo_network_settings', 'bbcmo_nonce'); ?>
                
                <div class="bbcmo-settings-section">
                    <h2><?php _e('Bunny.net Integration', 'bb-custom-media-offload-ms'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Bunny.net API Key', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <input type="password" name="bunny_api_key" 
                                    value="<?php echo esc_attr($this->settings['bunny_api_key']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Storage Zone Name', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <input type="text" name="storage_zone" 
                                    value="<?php echo esc_attr($this->settings['storage_zone']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('CDN URL', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <input type="url" name="cdn_url" 
                                    value="<?php echo esc_attr($this->settings['cdn_url']); ?>" class="regular-text">
                                <p class="description"><?php _e('Example: https://yourzone.b-cdn.net', 'bb-custom-media-offload-ms'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Force HTTPS', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="force_https" value="1" 
                                        <?php checked($this->settings['force_https']); ?>>
                                    <?php _e('Always use HTTPS for CDN URLs', 'bb-custom-media-offload-ms'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="bbcmo-settings-section">
                    <h2><?php _e('File Storage Settings', 'bb-custom-media-offload-ms'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('NFS Base Path', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <input type="text" name="nfs_base_path" 
                                    value="<?php echo esc_attr($this->settings['nfs_base_path']); ?>" class="regular-text">
                                <p class="description"><?php _e('Default shared storage path for uploads. Leave empty to use standard WordPress upload directory.', 'bb-custom-media-offload-ms'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Local File Types', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <input type="text" name="local_file_types" 
                                    value="<?php echo esc_attr($this->settings['local_file_types']); ?>" class="regular-text">
                                <p class="description"><?php _e('Comma-separated list of file extensions to keep local (e.g. json,svg)', 'bb-custom-media-offload-ms'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Delete Local After Offload', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="delete_local_after_offload" value="1" 
                                        <?php checked($this->settings['delete_local_after_offload']); ?>>
                                    <?php _e('Delete local files after successful offload to Bunny.net (WARNING: Make sure your CDN is working before enabling)', 'bb-custom-media-offload-ms'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Offload Delay (seconds)', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <input type="number" name="offload_delay" 
                                    value="<?php echo esc_attr($this->settings['offload_delay']); ?>" class="small-text">
                                <p class="description"><?php _e('How long to wait before offloading files after upload', 'bb-custom-media-offload-ms'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="bbcmo-settings-section">
                    <h2><?php _e('Site Configuration', 'bb-custom-media-offload-ms'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable for Sites', 'bb-custom-media-offload-ms'); ?></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><?php _e('Enable for Sites', 'bb-custom-media-offload-ms'); ?></legend>
                                    <?php foreach ($sites as $site) : 
                                        $blog_details = get_blog_details($site->blog_id);
                                        $checked = in_array($site->blog_id, $this->settings['enable_for_sites'] ?? array());
                                    ?>
                                        <label>
                                            <input type="checkbox" name="enable_for_sites[]" value="<?php echo $site->blog_id; ?>" <?php checked($checked); ?>>
                                            <?php echo $blog_details->blogname . ' (' . $blog_details->domain . $blog_details->path . ')'; ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save Network Settings', 'bb-custom-media-offload-ms')); ?>
            </form>
            
            <div class="bbcmo-settings-section">
                <h2><?php _e('Offload Queue Status (Network-wide)', 'bb-custom-media-offload-ms'); ?></h2>
                <?php BBCMO_Offload_Queue::render_queue_status(); ?>
            </div>
            
            <div class="bbcmo-settings-section bbcmo-card">
                <h2><?php _e('NFS Recommendations', 'bb-custom-media-offload-ms'); ?></h2>
                <h3><?php _e('Recommended NFS Solutions', 'bb-custom-media-offload-ms'); ?></h3>
                <p><?php _e('For WordPress multisite with media offloading, we recommend:', 'bb-custom-media-offload-ms'); ?></p>
                
                <ul class="bbcmo-list">
                    <li><strong><?php _e('Digital Ocean NFS Server', 'bb-custom-media-offload-ms'); ?></strong> - <?php _e('Set up a dedicated droplet as NFS server with DO Volume attached', 'bb-custom-media-offload-ms'); ?></li>
                    <li><strong><?php _e('Amazon EFS', 'bb-custom-media-offload-ms'); ?></strong> - <?php _e('Scalable NFS solution with AWS integration', 'bb-custom-media-offload-ms'); ?></li>
                    <li><strong><?php _e('GlusterFS', 'bb-custom-media-offload-ms'); ?></strong> - <?php _e('Open-source distributed file system', 'bb-custom-media-offload-ms'); ?></li>
                </ul>
                
                <h4><?php _e('Digital Ocean NFS Server Setup', 'bb-custom-media-offload-ms'); ?></h4>
                <ol class="bbcmo-list">
                    <li><?php _e('Create a dedicated Ubuntu droplet for NFS server', 'bb-custom-media-offload-ms'); ?></li>
                    <li><?php _e('Attach a Volume to this NFS server droplet', 'bb-custom-media-offload-ms'); ?></li>
                    <li><?php _e('Install NFS server: <code>apt install nfs-kernel-server</code>', 'bb-custom-media-offload-ms'); ?></li>
                    <li><?php _e('Configure exports to allow access from your WordPress servers', 'bb-custom-media-offload-ms'); ?></li>
                    <li><?php _e('On WordPress servers: <code>apt install nfs-common</code>', 'bb-custom-media-offload-ms'); ?></li>
                    <li><?php _e('Mount NFS share to same location on all WordPress servers', 'bb-custom-media-offload-ms'); ?></li>
                    <li><?php _e('Set that mount point as the NFS Base Path in settings above', 'bb-custom-media-offload-ms'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render site status page
     */
    public function render_site_status_page() {
        $blog_id = get_current_blog_id();
        ?>
        <div class="wrap bbcmo-admin-wrap">
            <h1><?php _e('Media Offload Status', 'bb-custom-media-offload-ms'); ?></h1>
            
            <div class="bbcmo-card">
                <h2><?php _e('Current Configuration', 'bb-custom-media-offload-ms'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('CDN URL', 'bb-custom-media-offload-ms'); ?></th>
                        <td><?php echo esc_html($this->settings['cdn_url']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Local File Types', 'bb-custom-media-offload-ms'); ?></th>
                        <td><?php echo esc_html($this->settings['local_file_types']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Offload Delay', 'bb-custom-media-offload-ms'); ?></th>
                        <td><?php echo esc_html($this->settings['offload_delay']); ?> <?php _e('seconds', 'bb-custom-media-offload-ms'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Storage Path', 'bb-custom-media-offload-ms'); ?></th>
                        <td><?php echo esc_html($this->settings['nfs_base_path'] ?: wp_upload_dir()['basedir']); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="bbcmo-settings-section">
                <h2><?php _e('Offload Queue for This Site', 'bb-custom-media-offload-ms'); ?></h2>
                <?php BBCMO_Offload_Queue::render_queue_status($blog_id); ?>
            </div>
            
            <div class="bbcmo-settings-section">
                <h2><?php _e('Offload Statistics', 'bb-custom-media-offload-ms'); ?></h2>
                <?php $this->render_site_statistics($blog_id); ?>
            </div>
            
            <div class="bbcmo-card bbcmo-info-card">
                <h3><?php _e('Need to change settings?', 'bb-custom-media-offload-ms'); ?></h3>
                <p><?php _e('Settings for Media Offload are managed at the network level. Please contact a super admin to adjust configuration.', 'bb-custom-media-offload-ms'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save network settings
     */
    public function save_network_settings() {
        // Check permissions
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'bb-custom-media-offload-ms'));
        }
        
        // Verify nonce
        check_admin_referer('bbcmo_network_settings', 'bbcmo_nonce');
        
        // Get settings from form
        $settings = array(
            'bunny_api_key' => sanitize_text_field($_POST['bunny_api_key'] ?? ''),
            'storage_zone' => sanitize_text_field($_POST['storage_zone'] ?? ''),
            'cdn_url' => esc_url_raw($_POST['cdn_url'] ?? ''),
            'force_https' => isset($_POST['force_https']) ? 1 : 0,
            'nfs_base_path' => untrailingslashit(sanitize_text_field($_POST['nfs_base_path'] ?? '')),
            'local_file_types' => sanitize_text_field($_POST['local_file_types'] ?? 'json'),
            'delete_local_after_offload' => isset($_POST['delete_local_after_offload']) ? 1 : 0,
            'offload_delay' => intval($_POST['offload_delay'] ?? 300),
            'enable_for_sites' => isset($_POST['enable_for_sites']) ? array_map('intval', $_POST['enable_for_sites']) : array(),
        );
        
        // Save settings
        update_site_option('bbcmo_settings', $settings);
        
        // Redirect back to settings page
        wp_redirect(add_query_arg(array('page' => 'bb-custom-media-offload-ms', 'updated' => 'true'), network_admin_url('admin.php')));
        exit;
    }
    
    /**
     * Render site statistics
     */
    private function render_site_statistics($blog_id = null) {
        global $wpdb;
        
        $blog_id = $blog_id ?: get_current_blog_id();
        
        // Count total offloaded files
        $offloaded_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_bbcmo_offloaded' AND meta_value = '1'"
            )
        );
        
        // Count total local-only files
        $local_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_bbcmo_keep_local' AND meta_value = '1'"
            )
        );
        
        // Get total media attachments
        $total_attachments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'"
            )
        );
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Metric', 'bb-custom-media-offload-ms'); ?></th>
                    <th><?php _e('Count', 'bb-custom-media-offload-ms'); ?></th>
                    <th><?php _e('Percentage', 'bb-custom-media-offload-ms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Total Media Attachments', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo intval($total_attachments); ?></td>
                    <td>100%</td>
                </tr>
                <tr>
                    <td><?php _e('Files Offloaded to CDN', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo intval($offloaded_count); ?></td>
                    <td><?php echo $total_attachments ? round(($offloaded_count / $total_attachments) * 100, 1) : 0; ?>%</td>
                </tr>
                <tr>
                    <td><?php _e('Files Kept Local', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo intval($local_count); ?></td>
                    <td><?php echo $total_attachments ? round(($local_count / $total_attachments) * 100, 1) : 0; ?>%</td>
                </tr>
                <tr>
                    <td><?php _e('Files Pending Offload', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo intval($total_attachments - $offloaded_count - $local_count); ?></td>
                    <td><?php echo $total_attachments ? round((($total_attachments - $offloaded_count - $local_count) / $total_attachments) * 100, 1) : 0; ?>%</td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Ajax handler for processing queue now
     */
    public function ajax_process_now() {
        // Check nonce
        check_ajax_referer('bbcmo_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'bb-custom-media-offload-ms')));
        }
        
        // Get blog ID if provided
        $blog_id = isset($_POST['blog_id']) ? intval($_POST['blog_id']) : null;
        
        // Process the queue
        BBCMO_Offload_Queue::process_queue($blog_id);
        
        wp_send_json_success(array('message' => __('Queue processing initiated.', 'bb-custom-media-offload-ms')));
    }
    
    /**
     * Ajax handler for retrying failed items
     */
    public function ajax_retry_failed() {
        // Check nonce
        check_ajax_referer('bbcmo_admin_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'bb-custom-media-offload-ms')));
        }
        
        // Get blog ID if provided
        $blog_id = isset($_POST['blog_id']) ? intval($_POST['blog_id']) : null;
        
        // Retry failed items
        BBCMO_Offload_Queue::retry_failed($blog_id);
        
        wp_send_json_success(array('message' => __('Failed items queued for retry.', 'bb-custom-media-offload-ms')));
    }
}