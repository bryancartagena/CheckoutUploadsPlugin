<?php
/**
 * AJAX Handler Class
 * 
 * Handles AJAX requests for file uploads
 */

if (!defined('WPINC')) {
    die;
}

class AIEP_Ajax_Handler {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('wp_ajax_aiep_upload_image', array($this, 'handle_image_upload'));
        add_action('wp_ajax_nopriv_aiep_upload_image', array($this, 'handle_image_upload'));
    }

    /**
     * Handle AJAX image upload
     */
    public function handle_image_upload() {
        $response = array('error' => '');

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aiep_upload_nonce')) {
            $response['error'] = __('Error de seguridad. Por favor, recarga la página e intenta de nuevo.', 'adjuntar-imagen-pedidos');
            wp_send_json($response);
            return;
        }

        // Check if file is uploaded
        if (empty($_FILES['aiep_image'])) {
            $response['error'] = __('No se ha subido ninguna imagen.', 'adjuntar-imagen-pedidos');
            wp_send_json($response);
            return;
        }

        // Get plugin settings
        $options = get_option('aiep_settings');
        $max_file_size = isset($options['max_file_size']) ? intval($options['max_file_size']) * 1024 * 1024 : 5 * 1024 * 1024; // Convert to bytes
        $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('jpg', 'jpeg', 'png');

        $file = $_FILES['aiep_image'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $response['error'] = $this->get_upload_error_message($file['error']);
            wp_send_json($response);
            return;
        }

        // Check file size
        if ($file['size'] > $max_file_size) {
            $response['error'] = sprintf(
                __('El archivo es demasiado grande. El tamaño máximo permitido es %s MB.', 'adjuntar-imagen-pedidos'),
                $options['max_file_size']
            );
            wp_send_json($response);
            return;
        }

        // Check file type
        $file_info = pathinfo($file['name']);
        $file_type = wp_check_filetype($file['name']);
        $ext = strtolower($file_info['extension']); // Use pathinfo instead of wp_check_filetype
        
        // Get allowed file types from settings
        $options = get_option('aiep_settings', array());
        $allowed_types = isset($options['allowed_file_types']) ? (array) $options['allowed_file_types'] : array('jpg', 'jpeg', 'png');
        
        // Convert to lowercase for comparison
        $allowed_types = array_map('strtolower', $allowed_types);
        $is_valid_extension = in_array($ext, $allowed_types);
                
        // If it's a valid image extension, let it pass, otherwise use the configured allowed types
        if (!$is_valid_extension) {
            // Format the list of allowed types for the error message
            $allowed_types_text = implode(', ', array_map('strtoupper', $allowed_types));
            $response['error'] = sprintf(
                __('Tipo de archivo no permitido. Por favor, sube una imagen en formato %s.', 'adjuntar-imagen-pedidos'),
                $allowed_types_text
            );
            wp_send_json($response);
            return;
        }

        // Upload to WordPress media library
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Get product ID if set
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $product_name = '';
        
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_name = $product->get_name();
            }
        }

        // Set attachment title
        if (!empty($product_name)) {
            $_POST['post_title'] = sprintf(
                __('Imagen para pedido - %s', 'adjuntar-imagen-pedidos'),
                $product_name
            );
        } else {
            $_POST['post_title'] = __('Imagen para pedido', 'adjuntar-imagen-pedidos');
        }

        // Upload file
        $attachment_id = media_handle_upload('aiep_image', 0);

        if (is_wp_error($attachment_id)) {
            $response['error'] = $attachment_id->get_error_message();
            wp_send_json($response);
            return;
        }

        // Get attachment URL
        $attachment_url = wp_get_attachment_url($attachment_id);

        // Mark the image as uploaded by this plugin for cleanup tracking
        update_post_meta($attachment_id, '_aiep_uploaded', '1');
        
        // Set post parent if product ID is set
        if ($product_id) {
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => $product_id
            ));
        }

        // Return success response
        $response = array(
            'success' => true,
            'url' => $attachment_url,
            'id' => $attachment_id
        );

        wp_send_json($response);
    }

    /**
     * Get upload error message
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('El archivo excede el tamaño máximo permitido.', 'adjuntar-imagen-pedidos');
            
            case UPLOAD_ERR_PARTIAL:
                return __('El archivo se subió parcialmente. Por favor, intenta de nuevo.', 'adjuntar-imagen-pedidos');
            
            case UPLOAD_ERR_NO_FILE:
                return __('No se ha subido ningún archivo.', 'adjuntar-imagen-pedidos');
            
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return __('Error del servidor al subir el archivo. Por favor, contacta al administrador.', 'adjuntar-imagen-pedidos');
            
            default:
                return __('Error desconocido al subir el archivo. Por favor, intenta de nuevo.', 'adjuntar-imagen-pedidos');
        }
    }
}
