<?php
/**
 * Cleanup Class
 * 
 * Handles scheduled cleanup of orphaned image uploads
 */

if (!defined('WPINC')) {
    die;
}

class AIEP_Cleanup {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register cleanup hooks
        add_action('aiep_daily_cleanup', array($this, 'cleanup_orphaned_images'));
        
        // Register activation and deactivation hooks
        register_activation_hook(AIEP_PLUGIN_BASENAME, array($this, 'activate_cleanup_schedule'));
        register_deactivation_hook(AIEP_PLUGIN_BASENAME, array($this, 'deactivate_cleanup_schedule'));
    }
    
    /**
     * Setup cleanup schedule on plugin activation
     */
    public function activate_cleanup_schedule() {
        if (!wp_next_scheduled('aiep_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'aiep_daily_cleanup');
        }
    }
    
    /**
     * Remove cleanup schedule on plugin deactivation
     */
    public function deactivate_cleanup_schedule() {
        wp_clear_scheduled_hook('aiep_daily_cleanup');
    }
    
    /**
     * Cleanup orphaned image uploads
     */
    public function cleanup_orphaned_images() {
        // Get images uploaded through the plugin
        $args = array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'date_query'     => array(
                array(
                    'before'    => '24 hours ago',
                    'inclusive' => false,
                ),
            ),
            'meta_query'     => array(
                array(
                    'key'     => '_aiep_uploaded',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        );
        
        $images = get_posts($args);
        $deleted_count = 0;
        $deleted_images = array();
        
        if (!empty($images)) {
            foreach ($images as $image) {
                // Check if the image is used in any order
                $is_used = $this->is_image_used_in_orders($image->ID, $image->guid);
                
                if (!$is_used) {
                    // Get image details for logging
                    $image_url = wp_get_attachment_url($image->ID);
                    $image_path = get_attached_file($image->ID);
                    
                    // Delete the attachment
                    wp_delete_attachment($image->ID, true);
                    
                    // Log deletion
                    $deleted_count++;
                    $deleted_images[] = array(
                        'id'   => $image->ID,
                        'url'  => $image_url,
                        'path' => $image_path,
                        'time' => current_time('mysql'),
                    );
                }
            }
        }
        
        // Log cleanup results
        if ($deleted_count > 0) {
            $log = get_option('aiep_cleanup_log', array());
            $log[] = array(
                'timestamp'      => current_time('mysql'),
                'images_deleted' => $deleted_count,
                'details'        => $deleted_images,
            );
            
            // Limit log to last 10 entries
            if (count($log) > 10) {
                $log = array_slice($log, -10);
            }
            
            update_option('aiep_cleanup_log', $log);
        }
    }
    
    /**
     * Check if an image is used in any WooCommerce order
     */
    private function is_image_used_in_orders($attachment_id, $attachment_url) {
        global $wpdb;
        
        // Normalize URL variations
        $url_variations = array(
            $attachment_url,
            esc_url($attachment_url),
            str_replace('https://', 'http://', $attachment_url),
            str_replace('http://', 'https://', $attachment_url),
        );
        
        $placeholders = implode(',', array_fill(0, count($url_variations), '%s'));
        
        // Check for usage in post meta (traditional storage)
        $meta_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE (meta_key LIKE '_aiep_image_%') 
            AND meta_value IN ({$placeholders})",
            $url_variations
        );
        
        $meta_count = $wpdb->get_var($meta_query);
        
        if ($meta_count > 0) {
            return true;
        }
        
        // If WooCommerce is using HPOS, check the order meta table
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            
            // The table name may vary, but it's typically 'wc_order_meta'
            $table = $wpdb->prefix . 'wc_order_meta';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") == $table) {
                $hpos_query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} 
                    WHERE (meta_key LIKE '_aiep_image_%') 
                    AND meta_value IN ({$placeholders})",
                    $url_variations
                );
                
                $hpos_count = $wpdb->get_var($hpos_query);
                
                if ($hpos_count > 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
