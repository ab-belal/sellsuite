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

        // Product meta boxes
        add_action('add_meta_boxes', array(Product_Meta::class, 'add_product_meta_box'));
        add_action('save_post_product', array(Product_Meta::class, 'save_product_meta_box'));

        // Product variations
        add_action('woocommerce_product_after_variable_attributes', array(Product_Meta::class, 'add_variation_options'), 10, 3);
        add_action('woocommerce_save_product_variation', array(Product_Meta::class, 'save_variation_meta'), 10, 2);

        // Product deletion
        add_action('delete_post', array(Product_Meta::class, 'on_product_delete'));

        // Order points handling
        Order_Handler::init();

        // Refund handling
        Refund_Handler::init();
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
