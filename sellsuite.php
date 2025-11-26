<?php
/**
 * Plugin Name: SellSuite
 * Plugin URI: https://example.com/sellsuite
 * Description: A modular WooCommerce extension that enhances store management with customer loyalty points, product management, and customer dashboard.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sellsuite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('SELLSUITE_VERSION', '1.0.0');
define('SELLSUITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SELLSUITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SELLSUITE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_sellsuite() {
    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-activator.php';
    SellSuite\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_sellsuite() {
    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-deactivator.php';
    SellSuite\Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_sellsuite');
register_deactivation_hook(__FILE__, 'deactivate_sellsuite');

/**
 * Check if WooCommerce is active
 */
function sellsuite_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'sellsuite_woocommerce_missing_notice');
        deactivate_plugins(SELLSUITE_PLUGIN_BASENAME);
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function sellsuite_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('SellSuite requires WooCommerce to be installed and active. The plugin has been deactivated.', 'sellsuite'); ?></p>
    </div>
    <?php
}

/**
 * Begin execution of the plugin.
 */
function run_sellsuite() {
    if (!sellsuite_check_woocommerce()) {
        return;
    }

    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-loader.php';
    
    $plugin = new SellSuite\Loader();
    $plugin->run();
}

add_action('plugins_loaded', 'run_sellsuite');

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});


add_action('wp_ajax_sellsuite_load_products', 'sellsuite_load_products');
add_action('wp_ajax_nopriv_sellsuite_load_products', 'sellsuite_load_products');

function sellsuite_load_products() {
    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-product-renderer.php';
    SellSuite_Product_Renderer::handle_ajax_load_products();
}
