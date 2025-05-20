<?php
/**
 * Core functionality for media handling
 * 
 * @package BB_Custom_Media_Offload_MS
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BBCMO_Media_Core {
    
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
        $this->setup_bbcmo_core_hooks();
    }
    
    /**
     * Setup hooks
     */
    private function setup_bbcmo_core_hooks() {
        // Setup hooks for current site if enabled
        add_action('init', array($this, 'setup_bbcmo_site_hooks'));
    }
    
    /**
     * Setup site-specific hooks if enabled for current site
     */
    public function setup_bbcmo_site_hooks() {
        $current_blog_id = get_current_blog_id();
        
        // Check if this site is enabled
        if (empty($this->settings['enable_for_sites']) || in_array($current_blog_id, $this->settings['enable_for_sites'])) {
            // Intercept uploads to determine handling
            add_filter('wp_handle_upload', array($this, 'handle_bbcmo_upload'), 10, 2);
            
            // Track attachment metadata
            add_filter('wp_generate_attachment_metadata', array($this, 'track_bbcmo_attachment_metadata'), 10, 2);
            
            // Modify image URLs in content
            add_filter('wp_get_attachment_url', array($this, 'get_bbcmo_attachment_url'), 10, 2);
            
            // Track current attachment ID during upload
            add_filter('wp_insert_attachment_data', array($this, 'track_bbcmo_attachment_id'), 10, 1);
        }
    }
    
    /**
     * Track the current attachment ID
     */
    public function track_bbcmo_attachment_id($data) {
        if (!empty($data['ID'])) {
            $GLOBALS['bbcmo_current_attachment_id'] = $data['ID'];
        }
        return $data;
    }
    
    /**
     * Handle new uploads and determine whether to queue for offloading
     */
    public function handle_bbcmo_upload($upload, $context) {
        // Skip if settings incomplete
        if (empty($this->settings['bunny_api_key']) || empty($this->settings['storage_zone']) || empty($this->settings['cdn_url'])) {
            return $upload;
        }
        
        // Don't modify the upload initially - we'll queue it for later offloading
        // This ensures the user sees their upload immediately
        
        // Check if this is a file type we want to keep local
        $file_extension = pathinfo($upload['file'], PATHINFO_EXTENSION);
        $local_file_types = explode(',', $this->settings['local_file_types']);
        $local_file_types = array_map('trim', $local_file_types);
        
        // If it's a file type to keep local, mark it
        if (in_array(strtolower($file_extension), $local_file_types)) {
            // Store metadata to indicate this should stay local
            if (!empty($GLOBALS['bbcmo_current_attachment_id'])) {
                update_post_meta($GLOBALS['bbcmo_current_attachment_id'], '_bbcmo_keep_local', true);
            }
        } else {
            // Queue this file for offloading after delay
            BBCMO_Offload_Queue::queue_for_offload($upload['file']);
        }
        
        return $upload;
    }
    
    /**
     * Track the current attachment metadata
     */
    public function track_bbcmo_attachment_metadata($metadata, $attachment_id) {
        // Skip if settings incomplete
        if (empty($this->settings['bunny_api_key']) || empty($this->settings['storage_zone']) || empty($this->settings['cdn_url'])) {
            return $metadata;
        }
        
        // Set a global to track the current attachment ID
        $GLOBALS['bbcmo_current_attachment_id'] = $attachment_id;
        
        // Store the original file path for reference
        if (!empty($metadata['file'])) {
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/' . $metadata['file'];
            update_post_meta($attachment_id, '_bbcmo_original_path', $file_path);
            
            // Queue the thumbnails too
            if (!empty($metadata['sizes'])) {
                $base_dir = dirname($file_path);
                foreach ($metadata['sizes'] as $size => $size_info) {
                    if (!empty($size_info['file'])) {
                        BBCMO_Offload_Queue::queue_for_offload($base_dir . '/' . $size_info['file']);
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Modify attachment URLs to use CDN
     */
    public function get_bbcmo_attachment_url($url, $attachment_id) {
        // Check if this attachment should stay local
        if (get_post_meta($attachment_id, '_bbcmo_keep_local', true)) {
            return $url;
        }
        
        // Check if this attachment has been offloaded
        $cdn_url = get_post_meta($attachment_id, '_bbcmo_bunny_cdn_url', true);
        
        if ($cdn_url) {
            // Force HTTPS if enabled
            if ($this->settings['force_https']) {
                $cdn_url = str_replace('http://', 'https://', $cdn_url);
            }
            
            return $cdn_url;
        }
        
        return $url;
    }
    
    /**
     * Upload a file to Bunny.net
     * 
     * @param string $file_path Full path to the file
     * @param int $attachment_id WordPress attachment ID
     * @return bool Success or failure
     */
    public function upload_to_bunny($file_path, $attachment_id) {
        // Get settings
        $settings = BB_Custom_Media_Offload_MS()->get_settings();
        
        // Check if file exists
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Prepare the relative path for Bunny storage
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $file_path);
        
        // Create API endpoint URL
        $api_url = "https://storage.bunnycdn.com/{$settings['storage_zone']}/" . $relative_path;
        
        // Upload file
        $response = wp_remote_request(
            $api_url,
            array(
                'method' => 'PUT',
                'headers' => array(
                    'AccessKey' => $settings['bunny_api_key'],
                    'Content-Type' => mime_content_type($file_path)
                ),
                'body' => file_get_contents($file_path)
            )
        );
        
        // Check if upload was successful
        if (!is_wp_error($response) && in_array(wp_remote_retrieve_response_code($response), array(200, 201))) {
            // Store CDN URL in attachment metadata
            if ($attachment_id) {
                $cdn_url = trailingslashit($settings['cdn_url']) . $relative_path;
                
                // Force HTTPS if enabled
                if ($settings['force_https']) {
                    $cdn_url = str_replace('http://', 'https://', $cdn_url);
                }
                
                update_post_meta($attachment_id, '_bbcmo_bunny_cdn_url', $cdn_url);
                update_post_meta($attachment_id, '_bbcmo_offloaded', true);
                update_post_meta($attachment_id, '_bbcmo_offloaded_path', $relative_path);
                
                // Delete local file if enabled
                if ($settings['delete_local_after_offload'] && !get_post_meta($attachment_id, '_bbcmo_keep_local', true)) {
                    // Don't delete original file yet, as WordPress might still need it
                    // Instead, schedule deletion for later or provide an option to purge
                }
            }
            
            return true;
        }
        
        return false;
    }
}