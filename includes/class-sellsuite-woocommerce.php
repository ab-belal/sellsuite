<?php
namespace SellSuite;

/**
 * WooCommerce integration functionality.
 *
 * Handles all WooCommerce-specific hooks and filters.
 */
class WooCommerce_Integration {

    public function __construct() {
        // Template override: let plugin provide WooCommerce templates from templates/woocommerce/
        add_filter('woocommerce_locate_template', array($this, 'locate_plugin_template'), 10, 3);
    }

    public function locate_plugin_template($template, $template_name, $template_path) {
        $plugin_template = SELLSUITE_PLUGIN_DIR . 'templates/woocommerce/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        $plugin_template_with_path = SELLSUITE_PLUGIN_DIR . 'templates/woocommerce/' . ltrim($template_path, '/') . $template_name;
        if (file_exists($plugin_template_with_path)) {
            return $plugin_template_with_path;
        }

        return $template;
    }

}
