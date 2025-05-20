<?php
/**
 * Queue management for offload process
 * 
 * @package BB_Custom_Media_Offload_MS
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BBCMO_Offload_Queue {
    
    // Settings
    private $settings = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load settings
        $this->settings = BB_Custom_Media_Offload_MS()->get_settings();
        
        // Set up hooks
        $this->setup_bbcmo_queue_hooks();
    }
    
    /**
     * Setup hooks
     */
    private function setup_bbcmo_queue_hooks() {
        // Schedule offload task
        add_action('bbcmo_process_media_queue', array($this, 'process_bbcmo_offload_queue'));
    }
    
    /**
     * Create the queue database table
     */
    public function create_queue_table() {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . BBCMO_QUEUE_TABLE;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            blog_id bigint(20) NOT NULL,
            attachment_id bigint(20) NOT NULL,
            file_path varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            scheduled_time datetime NOT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY blog_id (blog_id),
            KEY attachment_id (attachment_id),
            KEY status (status),
            KEY scheduled_time (scheduled_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Add file to offload queue with delay
     * 
     * @param string $file_path Full path to the file
     */
    public static function queue_for_offload($file_path) {
        global $wpdb;
        
        // Get settings
        $settings = BB_Custom_Media_Offload_MS()->get_settings();
        
        $blog_id = get_current_blog_id();
        $attachment_id = $GLOBALS['bbcmo_current_attachment_id'] ?? 0;
        
        // Don't queue if marked to keep local
        if ($attachment_id && get_post_meta($attachment_id, '_bbcmo_keep_local', true)) {
            return;
        }
        
        $scheduled_time = date('Y-m-d H:i:s', time() + $settings['offload_delay']);
        
        $wpdb->insert(
            $wpdb->base_prefix . BBCMO_QUEUE_TABLE,
            array(
                'blog_id' => $blog_id,
                'attachment_id' => $attachment_id,
                'file_path' => $file_path,
                'status' => 'pending',
                'scheduled_time' => $scheduled_time
            )
        );
    }
    
    /**
     * Process the offload queue
     */
    public function process_bbcmo_offload_queue() {
        // Get settings
        $settings = BB_Custom_Media_Offload_MS()->get_settings();
        
        // Skip if settings incomplete
        if (empty($settings['bunny_api_key']) || empty($settings['storage_zone']) || empty($settings['cdn_url'])) {
            return;
        }
        
        // Process the queue
        self::process_queue();
    }
    
    /**
     * Process items in the queue
     * 
     * @param int|null $blog_id Optional. Process only items for a specific blog
     */
    public static function process_queue($blog_id = null) {
        global $wpdb;
        
        // Get settings
        $settings = BB_Custom_Media_Offload_MS()->get_settings();
        
        // Skip if settings incomplete
        if (empty($settings['bunny_api_key']) || empty($settings['storage_zone']) || empty($settings['cdn_url'])) {
            return;
        }
        
        $table_name = $wpdb->base_prefix . BBCMO_QUEUE_TABLE;
        
        // Prepare WHERE clause for specific blog if provided
        $blog_where = $blog_id ? $wpdb->prepare("AND blog_id = %d", $blog_id) : '';
        
        // Get up to 20 files ready for processing
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} 
                WHERE status = 'pending' AND scheduled_time <= %s {$blog_where}
                ORDER BY scheduled_time ASC LIMIT 20",
                current_time('mysql')
            )
        );
        
        if (!$items) {
            return;
        }
        
        // Get core instance for upload method
        $core = new BBCMO_Media_Core();
        
        foreach ($items as $item) {
            // Mark as processing
            $wpdb->update(
                $table_name,
                array('status' => 'processing'),
                array('id' => $item->id)
            );
            
            // Switch to the blog context
            switch_to_blog($item->blog_id);
            
            // Upload to Bunny.net
            $result = $core->upload_to_bunny($item->file_path, $item->attachment_id);
            
            // Restore original blog context
            restore_current_blog();
            
            if ($result) {
                // Success - remove from queue
                $wpdb->delete(
                    $table_name,
                    array('id' => $item->id)
                );
            } else {
                // Failure - increment attempts and reschedule
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'pending',
                        'scheduled_time' => date('Y-m-d H:i:s', time() + 900), // Retry in 15 minutes
                        'attempts' => $item->attempts + 1
                    ),
                    array('id' => $item->id)
                );
                
                // If too many attempts, mark as failed
                if ($item->attempts >= 5) {
                    $wpdb->update(
                        $table_name,
                        array('status' => 'failed'),
                        array('id' => $item->id)
                    );
                }
            }
        }
    }
    
    /**
     * Retry failed items
     * 
     * @param int|null $blog_id Optional. Retry only items for a specific blog
     */
    public static function retry_failed($blog_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . BBCMO_QUEUE_TABLE;
        
        // Prepare WHERE clause for specific blog if provided
        $blog_where = $blog_id ? $wpdb->prepare("AND blog_id = %d", $blog_id) : '';
        
        // Reset failed items to pending
        $wpdb->query(
            "UPDATE {$table_name} 
            SET status = 'pending', 
                attempts = 0, 
                scheduled_time = '" . date('Y-m-d H:i:s', time() + 60) . "'
            WHERE status = 'failed' {$blog_where}"
        );
    }
    
    /**
     * Render queue status
     * 
     * @param int|null $blog_id Optional. Show status only for a specific blog
     */
    public static function render_queue_status($blog_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->base_prefix . BBCMO_QUEUE_TABLE;
        
        // Prepare WHERE clause for specific blog if provided
        $blog_where = $blog_id ? $wpdb->prepare("AND blog_id = %d", $blog_id) : '';
        
        $pending_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' {$blog_where}"
        );
        
        $processing_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'processing' {$blog_where}"
        );
        
        $failed_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'failed' {$blog_where}"
        );
        
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Status', 'bb-custom-media-offload-ms'); ?></th>
                    <th><?php _e('Count', 'bb-custom-media-offload-ms'); ?></th>
                    <th><?php _e('Actions', 'bb-custom-media-offload-ms'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php _e('Pending', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo $pending_count; ?></td>
                    <td>
                        <button class="button bbcmo-process-now" data-blog="<?php echo $blog_id; ?>"><?php _e('Process Now', 'bb-custom-media-offload-ms'); ?></button>
                    </td>
                </tr>
                <tr>
                    <td><?php _e('Processing', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo $processing_count; ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td><?php _e('Failed', 'bb-custom-media-offload-ms'); ?></td>
                    <td><?php echo $failed_count; ?></td>
                    <td>
                        <button class="button bbcmo-retry-failed" data-blog="<?php echo $blog_id; ?>"><?php _e('Retry Failed', 'bb-custom-media-offload-ms'); ?></button>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}