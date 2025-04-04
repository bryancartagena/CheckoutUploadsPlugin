<?php
/**
 * Admin Settings Class
 * 
 * Handles the admin settings page for the plugin.
 */

if (!defined('WPINC')) {
    die;
}

class AIEP_Admin_Settings {
    /**
     * Initialize the class
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add options page to admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Adjuntar imagen en pedidos', 'adjuntar-imagen-pedidos'),
            __('Adjuntar imagen en pedidos', 'adjuntar-imagen-pedidos'),
            'manage_options',
            'adjuntar-imagen-pedidos',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'aiep_settings_group',
            'aiep_settings',
            array($this, 'sanitize_settings')
        );

        // General settings section
        add_settings_section(
            'aiep_general_section',
            __('Configuración general', 'adjuntar-imagen-pedidos'),
            array($this, 'render_general_section'),
            'aiep_settings'
        );

        // Categories field
        add_settings_field(
            'aiep_categories',
            __('Categorías', 'adjuntar-imagen-pedidos'),
            array($this, 'render_categories_field'),
            'aiep_settings',
            'aiep_general_section'
        );

        // File settings
        add_settings_field(
            'aiep_file_settings',
            __('Configuración de archivos', 'adjuntar-imagen-pedidos'),
            array($this, 'render_file_settings_field'),
            'aiep_settings',
            'aiep_general_section'
        );

        // Messages settings
        add_settings_field(
            'aiep_messages',
            __('Mensajes', 'adjuntar-imagen-pedidos'),
            array($this, 'render_messages_field'),
            'aiep_settings',
            'aiep_general_section'
        );

        // Display settings
        add_settings_field(
            'aiep_display_settings',
            __('Configuración de visualización', 'adjuntar-imagen-pedidos'),
            array($this, 'render_display_settings_field'),
            'aiep_settings',
            'aiep_general_section'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize categories array
        $sanitized['categories'] = isset($input['categories']) ? array_map('intval', $input['categories']) : array();
        
        // Sanitize max file size
        $sanitized['max_file_size'] = isset($input['max_file_size']) ? absint($input['max_file_size']) : 5;
        
        // Sanitize allowed file types
        $sanitized['allowed_file_types'] = isset($input['allowed_file_types']) ? array_map('sanitize_text_field', $input['allowed_file_types']) : array('jpg', 'jpeg', 'png');
        
        // Sanitize show in emails
        $sanitized['show_in_emails'] = isset($input['show_in_emails']) ? (bool) $input['show_in_emails'] : true;
        
        // Sanitize messages
        $sanitized['required_message'] = isset($input['required_message']) ? sanitize_text_field($input['required_message']) : '';
        $sanitized['instruction_message'] = isset($input['instruction_message']) ? wp_kses_post($input['instruction_message']) : '';
        
        return $sanitized;
    }

    /**
     * Render the settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('aiep_settings_group');
                do_settings_sections('aiep_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . __('Configure las opciones para adjuntar imágenes en el checkout de WooCommerce.', 'adjuntar-imagen-pedidos') . '</p>';
    }

    /**
     * Render categories field
     */
    public function render_categories_field() {
        $options = get_option('aiep_settings');
        $selected_categories = isset($options['categories']) ? $options['categories'] : array();
        
        $product_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        if (!empty($product_categories)) {
            echo '<select name="aiep_settings[categories][]" multiple="multiple" style="width: 300px; height: 150px;">';
            foreach ($product_categories as $category) {
                $selected = in_array($category->term_id, $selected_categories) ? 'selected="selected"' : '';
                echo '<option value="' . esc_attr($category->term_id) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . __('Seleccione las categorías de productos que requerirán una imagen adjunta en el checkout.', 'adjuntar-imagen-pedidos') . '</p>';
        } else {
            echo '<p>' . __('No hay categorías de productos disponibles.', 'adjuntar-imagen-pedidos') . '</p>';
        }
    }

    /**
     * Render file settings field
     */
    public function render_file_settings_field() {
        $options = get_option('aiep_settings');
        $max_file_size = isset($options['max_file_size']) ? $options['max_file_size'] : 5;
        $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('jpg', 'jpeg', 'png');
        
        // Max file size
        echo '<label for="aiep_max_file_size">' . __('Tamaño máximo de archivo (MB):', 'adjuntar-imagen-pedidos') . '</label> ';
        echo '<input type="number" id="aiep_max_file_size" name="aiep_settings[max_file_size]" value="' . esc_attr($max_file_size) . '" min="1" max="20" step="1" />';
        echo '<p class="description">' . __('El tamaño máximo de archivo permitido en MB.', 'adjuntar-imagen-pedidos') . '</p>';
        
        // Allowed file types
        echo '<fieldset style="margin-top: 15px;">';
        echo '<legend>' . __('Tipos de archivos permitidos:', 'adjuntar-imagen-pedidos') . '</legend>';
        
        $file_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
        foreach ($file_types as $type) {
            $checked = in_array($type, $allowed_file_types) ? 'checked="checked"' : '';
            echo '<label><input type="checkbox" name="aiep_settings[allowed_file_types][]" value="' . esc_attr($type) . '" ' . $checked . ' /> ' . strtoupper(esc_html($type)) . '</label><br />';
        }
        echo '</fieldset>';
    }

    /**
     * Render messages field
     */
    public function render_messages_field() {
        $options = get_option('aiep_settings');
        $required_message = isset($options['required_message']) ? $options['required_message'] : '';
        $instruction_message = isset($options['instruction_message']) ? $options['instruction_message'] : '';
        
        // Required message
        echo '<label for="aiep_required_message">' . __('Mensaje de campo requerido:', 'adjuntar-imagen-pedidos') . '</label><br />';
        echo '<input type="text" id="aiep_required_message" name="aiep_settings[required_message]" value="' . esc_attr($required_message) . '" class="regular-text" />';
        echo '<p class="description">' . __('Mensaje mostrado cuando no se ha subido una imagen.', 'adjuntar-imagen-pedidos') . '</p>';
        
        // Instruction message
        echo '<label for="aiep_instruction_message" style="margin-top: 15px; display: block;">' . __('Mensaje de instrucciones:', 'adjuntar-imagen-pedidos') . '</label><br />';
        wp_editor(
            $instruction_message,
            'aiep_instruction_message',
            array(
                'textarea_name' => 'aiep_settings[instruction_message]',
                'textarea_rows' => 5,
                'media_buttons' => false,
                'teeny' => true,
            )
        );
        echo '<p class="description">' . __('Instrucciones mostradas al cliente antes de subir la imagen.', 'adjuntar-imagen-pedidos') . '</p>';
    }

    /**
     * Render display settings field
     */
    public function render_display_settings_field() {
        $options = get_option('aiep_settings');
        $show_in_emails = isset($options['show_in_emails']) ? $options['show_in_emails'] : true;
        
        echo '<label>';
        echo '<input type="checkbox" name="aiep_settings[show_in_emails]" value="1" ' . ($show_in_emails ? 'checked="checked"' : '') . ' />';
        echo ' ' . __('Mostrar imágenes en correos electrónicos', 'adjuntar-imagen-pedidos');
        echo '</label>';
        echo '<p class="description">' . __('Si está marcado, las imágenes se mostrarán en los correos electrónicos de confirmación de pedido.', 'adjuntar-imagen-pedidos') . '</p>';
    }
}
