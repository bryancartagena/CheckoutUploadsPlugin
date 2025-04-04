<?php
/**
 * Checkout Fields Class
 * 
 * Adds upload fields to the checkout page for selected categories
 */

if (!defined('WPINC')) {
    die;
}

class AIEP_Checkout_Fields {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('woocommerce_after_order_notes', array($this, 'add_image_upload_fields'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
        add_action('woocommerce_checkout_process', array($this, 'validate_image_uploads'));
    }

    /**
     * Check if any product in cart belongs to the configured categories
     */
    private function has_category_products_in_cart() {
        global $woocommerce;
        $options = get_option('aiep_settings');
        $selected_categories = isset($options['categories']) ? $options['categories'] : array();
        
        if (empty($selected_categories)) {
            return false;
        }

        $items = $woocommerce->cart->get_cart();
        $products_requiring_image = array();

        foreach ($items as $item => $values) {
            $terms = get_the_terms($values['product_id'], 'product_cat');
            
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    if (in_array($term->term_id, $selected_categories)) {
                        $products_requiring_image[] = array(
                            'id' => $values['product_id'],
                            'name' => $values['data']->get_name(),
                            'category' => $term->name
                        );
                        break;
                    }
                }
            }
        }
        
        return $products_requiring_image;
    }

    /**
     * Add image upload fields at checkout
     */
    public function add_image_upload_fields($checkout) {
        $products_requiring_image = $this->has_category_products_in_cart();
        
        if (!$products_requiring_image) {
            return;
        }
        
        $options = get_option('aiep_settings');
        $instruction_message = isset($options['instruction_message']) ? $options['instruction_message'] : '';
        
        echo '<div id="aiep_upload_fields">';
        echo '<h3>' . __('Imágenes requeridas', 'adjuntar-imagen-pedidos') . '</h3>';
        
        if (!empty($instruction_message)) {
            echo '<div class="aiep-instructions">' . wpautop($instruction_message) . '</div>';
        }
        
        foreach ($products_requiring_image as $index => $product) {
            echo '<div class="aiep-upload-container">';
            echo '<h4>' . sprintf(__('Sube una imagen para: %s', 'adjuntar-imagen-pedidos'), esc_html($product['name'])) . '</h4>';
            echo '<div id="aiep_fileuploader_' . esc_attr($index) . '" class="aiep-uploader">Upload</div>';
            echo '<input type="hidden" id="aiep_image_' . esc_attr($index) . '" name="aiep_image_' . esc_attr($index) . '" />';
            echo '<input type="hidden" name="aiep_product_id_' . esc_attr($index) . '" value="' . esc_attr($product['id']) . '" />';
            echo '<div class="aiep-error-message" id="aiep_error_' . esc_attr($index) . '"></div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add JavaScript for file uploads
        $this->add_upload_scripts($products_requiring_image);
    }

    /**
     * Add JavaScript for handling file uploads
     */
    private function add_upload_scripts($products) {
        $options = get_option('aiep_settings');
        $max_file_size = isset($options['max_file_size']) ? intval($options['max_file_size']) : 5;
        $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('jpg', 'jpeg', 'png');
        
        // For jQuery uploadFile plugin, use '*' to allow all file types
        // We'll do proper validation on the server side
        $extensions_str = '*';
        
        // Create nonce for security
        $nonce = wp_create_nonce('aiep_upload_nonce');
        
        // Build JavaScript
        $script = "
        jQuery(document).ready(function($) {
            " . $this->get_upload_javascript($products, $max_file_size, $extensions_str, $nonce) . "
        });
        ";
        
        wp_add_inline_script('aiep-uploadfile-script', $script);
    }
    
    /**
     * Generate JavaScript for file uploads
     */
    private function get_upload_javascript($products, $max_file_size, $extensions_str, $nonce) {
        $js = '';
        
        foreach ($products as $index => $product) {
            $js .= "
            $('#aiep_fileuploader_" . esc_js($index) . "').uploadFile({
                url: '" . admin_url('admin-ajax.php') . "',
                fileName: 'aiep_image',
                formData: {
                    action: 'aiep_upload_image',
                    nonce: '" . esc_js($nonce) . "',
                    product_id: '" . esc_js($product['id']) . "'
                },
                dragDrop: true,
                allowedTypes: '" . esc_js($extensions_str) . "',
                maxFileSize: " . (intval($max_file_size) * 1024 * 1024) . ",
                showDelete: true,
                showPreview: true,
                uploadStr: 'Subir archivo',
                dragDropStr: '<span>Arrastrar y soltar archivos</span>',
                abortStr: 'Abortar',
                cancelStr: 'Cancelar',
                doneStr: 'Listo',
                deleteStr: 'Eliminar',
                multiDragErrorStr: 'No se permiten multiples archivos',
                extErrorStr: 'Extensiones permitidas:',
                sizeErrorStr: 'Tamaño máximo permitido:',
                uploadErrorStr: 'No se permite subir',
                previewHeight: '100px',
                previewWidth: '100px',
                multiple: false,
                onSelect: function(files) {
                    // Remove all existing files
                    $('.ajax-file-upload-statusbar', $('#aiep_fileuploader_" . esc_js($index) . "').parent()).remove();
                    return true;
                },
                onSuccess: function(files, data, xhr, pd) {
                    try {
                        // Check if data is already an object
                        let response = typeof data === 'object' ? data : JSON.parse(data);
                        
                        if (response.error) {
                            $('#aiep_error_" . esc_js($index) . "').html(response.error);
                            $('#aiep_image_" . esc_js($index) . "').val('');
                        } else if (response.url) {
                            // This will handle both formats (with or without success property)
                            $('#aiep_image_" . esc_js($index) . "').val(response.url);
                            $('#aiep_error_" . esc_js($index) . "').html('');
                        } else {
                            // Unexpected response format
                            $('#aiep_error_" . esc_js($index) . "').html('Formato de respuesta no válido');
                            $('#aiep_image_" . esc_js($index) . "').val('');
                        }
                    } catch (e) {
                        console.error('AIEP parse error:', e, data);
                        $('#aiep_error_" . esc_js($index) . "').html('Error al procesar la respuesta del servidor');
                        $('#aiep_image_" . esc_js($index) . "').val('');
                    }
                },
                onError: function(files, status, errMsg, pd) {
                    $('#aiep_error_" . esc_js($index) . "').html('Error: ' + errMsg);
                    $('#aiep_image_" . esc_js($index) . "').val('');
                }
            });
            ";
        }
        
        return $js;
    }
    
    /**
     * Enqueue scripts and styles for checkout
     */
    public function enqueue_checkout_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        // Only enqueue if we have products requiring images
        $products_requiring_image = $this->has_category_products_in_cart();
        if ($products_requiring_image) {
            wp_enqueue_style('aiep-uploadfile-style');
            wp_enqueue_script('aiep-uploadfile-script');
            
            // Add custom styles
            $custom_css = "
            .aiep-instructions {
                margin-bottom: 20px;
                padding: 10px;
                background-color: #f8f8f8;
                border-left: 4px solid #ddd;
            }
            .aiep-upload-container {
                margin-bottom: 30px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 5px;
            }
            .aiep-error-message {
                color: #b94a48;
                margin-top: 10px;
                font-weight: bold;
            }
            .ajax-file-upload {
                background: #0073aa;
                border: none;
                box-shadow: none;
                font-weight: normal;
            }
            ";
            wp_add_inline_style('aiep-uploadfile-style', $custom_css);
        }
    }
    
    /**
     * Validate image uploads at checkout
     */
    public function validate_image_uploads() {
        $products_requiring_image = $this->has_category_products_in_cart();
        
        if (!$products_requiring_image) {
            return;
        }
        
        $options = get_option('aiep_settings');
        $required_message = isset($options['required_message']) ? $options['required_message'] : __('Por favor sube una imagen para este producto.', 'adjuntar-imagen-pedidos');
        
        foreach ($products_requiring_image as $index => $product) {
            if (empty($_POST['aiep_image_' . $index])) {
                wc_add_notice(sprintf(
                    __('<strong>%s</strong> %s', 'adjuntar-imagen-pedidos'),
                    $product['name'],
                    $required_message
                ), 'error');
            }
        }
    }
}
