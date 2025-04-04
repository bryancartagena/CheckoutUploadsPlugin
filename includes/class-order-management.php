<?php
/**
 * Order Management Class
 * 
 * Handles saving and displaying image URLs in orders.
 */

if (!defined('WPINC')) {
    die;
}

class AIEP_Order_Management {
    /**
     * Initialize the class
     */
    public function __construct() {
        // For traditional orders (post meta)
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_image_urls_to_order'));
        
        // For HPOS compatibility
        add_action('woocommerce_checkout_create_order', array($this, 'save_image_urls_to_hpos_order'), 10, 2);
        
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_images_in_admin_order'), 10, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'display_images_in_emails'), 10, 4);
    }

    /**
     * Save image URLs to order meta (for traditional order storage)
     */
    public function save_image_urls_to_order($order_id) {
        $image_count = 0;
        
        // Loop through uploaded image fields
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'aiep_image_') === 0 && !empty($value)) {
                $index = str_replace('aiep_image_', '', $key);
                $product_id = isset($_POST['aiep_product_id_' . $index]) ? sanitize_text_field($_POST['aiep_product_id_' . $index]) : '';
                
                // Save image URL
                update_post_meta($order_id, '_aiep_image_' . $image_count, sanitize_url($value));
                
                // Save product ID if available
                if (!empty($product_id)) {
                    update_post_meta($order_id, '_aiep_product_id_' . $image_count, $product_id);
                }
                
                $image_count++;
            }
        }
        
        // Save total image count
        if ($image_count > 0) {
            update_post_meta($order_id, '_aiep_image_count', $image_count);
        }
    }
    
    /**
     * Save image URLs to order meta (for HPOS order storage)
     */
    public function save_image_urls_to_hpos_order($order, $data) {
        $image_count = 0;
        
        // Loop through uploaded image fields
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'aiep_image_') === 0 && !empty($value)) {
                $index = str_replace('aiep_image_', '', $key);
                $product_id = isset($_POST['aiep_product_id_' . $index]) ? sanitize_text_field($_POST['aiep_product_id_' . $index]) : '';
                
                // Save image URL
                $order->update_meta_data('_aiep_image_' . $image_count, sanitize_url($value));
                
                // Save product ID if available
                if (!empty($product_id)) {
                    $order->update_meta_data('_aiep_product_id_' . $image_count, $product_id);
                }
                
                $image_count++;
            }
        }
        
        // Save total image count
        if ($image_count > 0) {
            $order->update_meta_data('_aiep_image_count', $image_count);
        }
    }

    /**
     * Display uploaded images in admin order page
     */
    public function display_images_in_admin_order($order) {
        $order_id = $order->get_id();
        $image_count = $this->get_order_meta($order, '_aiep_image_count');
        
        if (empty($image_count)) {
            return;
        }
        
        echo '<div class="aiep-order-images">';
        echo '<h3>' . __('Imágenes adjuntas', 'adjuntar-imagen-pedidos') . '</h3>';
        
        for ($i = 0; $i < $image_count; $i++) {
            $image_url = $this->get_order_meta($order, '_aiep_image_' . $i);
            $product_id = $this->get_order_meta($order, '_aiep_product_id_' . $i);
            
            if (!empty($image_url)) {
                $product_name = '';
                if (!empty($product_id)) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $product_name = $product->get_name();
                    }
                }
                
                echo '<div class="aiep-image-container">';
                if (!empty($product_name)) {
                    echo '<p><strong>' . esc_html($product_name) . '</strong></p>';
                }
                echo '<a href="' . esc_url($image_url) . '" target="_blank">';
                echo '<img src="' . esc_url($image_url) . '" alt="' . __('Imagen adjunta', 'adjuntar-imagen-pedidos') . '" style="max-width: 200px; max-height: 200px; margin-bottom: 10px;" />';
                echo '</a>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        
        // Add some CSS
        echo '<style>
            .aiep-order-images {
                margin-top: 20px;
                padding: 10px;
                background: #f8f8f8;
                border-radius: 4px;
            }
            .aiep-image-container {
                display: inline-block;
                margin-right: 15px;
                margin-bottom: 15px;
                vertical-align: top;
                text-align: center;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>';
    }

    /**
     * Display uploaded images in order emails
     */
    public function display_images_in_emails($order, $sent_to_admin, $plain_text, $email) {
        $options = get_option('aiep_settings');
        $show_in_emails = isset($options['show_in_emails']) ? $options['show_in_emails'] : true;
        
        if (!$show_in_emails) {
            return;
        }
        
        $order_id = $order->get_id();
        $image_count = get_post_meta($order_id, '_aiep_image_count', true);
        
        if (empty($image_count)) {
            return;
        }
        
        if ($plain_text) {
            echo "\n\n" . __('Imágenes adjuntas a este pedido:', 'adjuntar-imagen-pedidos') . "\n\n";
            return;
        }
        
        echo '<div style="margin-top: 20px; margin-bottom: 20px; padding: 10px; background-color: #f8f8f8;">';
        echo '<h2 style="margin-bottom: 10px;">' . __('Imágenes adjuntas', 'adjuntar-imagen-pedidos') . '</h2>';
        echo '<p><i>' . __('En el correo solamente se mostrarán hasta 3 imágenes', 'adjuntar-imagen-pedidos') . '</i></p>';
        
        // Limit to 3 images for emails to avoid large emails
        $max_images = min(3, $image_count);
        
        for ($i = 0; $i < $max_images; $i++) {
            $image_url = $this->get_order_meta($order, '_aiep_image_' . $i);
            $product_id = $this->get_order_meta($order, '_aiep_product_id_' . $i);
            
            if (!empty($image_url)) {
                $product_name = '';
                if (!empty($product_id)) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $product_name = $product->get_name();
                    }
                }
                
                echo '<div style="margin-bottom: 20px;">';
                if (!empty($product_name)) {
                    echo '<p><strong>' . esc_html($product_name) . '</strong></p>';
                }
                echo '<img src="' . esc_url($image_url) . '" alt="' . __('Imagen adjunta', 'adjuntar-imagen-pedidos') . '" style="max-width: 100%; height: auto; max-height: 300px; border: 1px solid #ddd;" />';
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Helper function to get order meta data - compatible with both HPOS and traditional storage
     *
     * @param WC_Order $order The order object
     * @param string $key The meta key to retrieve
     * @return mixed The meta value
     */
    private function get_order_meta($order, $key) {
        // Check if we can use the WC_Order method (HPOS compatible)
        if (method_exists($order, 'get_meta')) {
            return $order->get_meta($key);
        }
        
        // Fallback to traditional meta
        return get_post_meta($order->get_id(), $key, true);
    }
}
