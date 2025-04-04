<?php
/**
 * Plugin Name: Adjuntar imagen en pedidos Woocommerce
 * Plugin URI: https://aplicacionesweb.cl
 * Description: Permite adjuntar imágenes en el checkout para productos de categorías específicas y mostrarlas en los emails y panel de administración.
 * Version: 1.0.10
 * Author: Aplicaciones Web
 * Author URI: https://aplicacionesweb.cl
 * Text Domain: adjuntar-imagen-pedidos
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 8.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AIEP_VERSION', '1.0.10');
define('AIEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIEP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function aiep_check_woocommerce_active() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', 'aiep_woocommerce_notice');
        return false;
    }
    return true;
}

/**
 * Display a notice if WooCommerce is not active
 */
function aiep_woocommerce_notice() {
    ?>
    <div class="error">
        <p><?php _e('Adjuntar imagen en pedidos Woocommerce requiere que WooCommerce esté instalado y activado.', 'adjuntar-imagen-pedidos'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function aiep_init() {
    if (!aiep_check_woocommerce_active()) {
        return;
    }

    // Load plugin textdomain
    load_plugin_textdomain('adjuntar-imagen-pedidos', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Include required files
    require_once AIEP_PLUGIN_DIR . 'includes/class-admin-settings.php';
    require_once AIEP_PLUGIN_DIR . 'includes/class-checkout-fields.php';
    require_once AIEP_PLUGIN_DIR . 'includes/class-order-management.php';
    require_once AIEP_PLUGIN_DIR . 'includes/class-ajax-handler.php';
    require_once AIEP_PLUGIN_DIR . 'includes/class-cleanup.php';

    // Initialize plugin components
    new AIEP_Admin_Settings();
    new AIEP_Checkout_Fields();
    new AIEP_Order_Management();
    new AIEP_Ajax_Handler();
    new AIEP_Cleanup();
}
add_action('plugins_loaded', 'aiep_init');

/**
 * Register scripts and styles
 */
function aiep_register_scripts() {
    // Register styles
    wp_register_style(
        'aiep-uploadfile-style',
        AIEP_PLUGIN_URL . 'assets/css/uploadfile.css',
        array(),
        AIEP_VERSION
    );

    // Register scripts
    wp_register_script(
        'aiep-uploadfile-script',
        AIEP_PLUGIN_URL . 'assets/js/jquery.uploadfile.min.js',
        array('jquery'),
        AIEP_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'aiep_register_scripts');

/**
 * Plugin activation hook
 */
function aiep_activate() {
    // Create default options
    $default_options = array(
        'categories' => array(),
        'max_file_size' => 5, // MB
        'allowed_file_types' => array('jpg', 'jpeg', 'png'),
        'show_in_emails' => true,
        'required_message' => 'Por favor sube una imagen para este producto.',
        'instruction_message' => 'Recuerda que tu foto debe ser nítida, con buena luz y donde se pueda apreciar la receta completa. La fecha de esta no puede ser superior a 15 días.',
    );
    
    if (!get_option('aiep_settings')) {
        add_option('aiep_settings', $default_options);
    }
}
register_activation_hook(__FILE__, 'aiep_activate');

/**
 * Plugin deactivation hook
 */
function aiep_deactivate() {
    // Cleanup tasks if necessary
}
register_deactivation_hook(__FILE__, 'aiep_deactivate');

/**
 * Declare compatibility with WooCommerce HPOS
 */
function aiep_declare_hpos_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
}
add_action('before_woocommerce_init', 'aiep_declare_hpos_compatibility');
