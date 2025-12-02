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

        // PHASE 7: Point expiry scheduled processing
        add_action('sellsuite_process_point_expirations', array($this, 'process_all_expirations'));

        // PHASE 8: Currency exchange rate caching and updates
        add_action('sellsuite_update_exchange_rates', array($this, 'refresh_exchange_rates'));
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

    /**
     * PHASE 7: Process all point expirations for all users.
     *
     * This is called by the WordPress scheduled cron job
     * and processes expired points for all users in the system.
     */
    public function process_all_expirations() {
        try {
            global $wpdb;

            // Get all users with points
            $table = $wpdb->prefix . 'sellsuite_points_ledger';

            $user_ids = $wpdb->get_col(
                "SELECT DISTINCT user_id FROM {$table} WHERE status = 'earned'"
            );

            if (empty($user_ids)) {
                return;
            }

            foreach ($user_ids as $user_id) {
                Expiry_Handler::process_user_expirations($user_id);
            }

            do_action('sellsuite_expirations_processed', count($user_ids));

        } catch (Exception $e) {
            error_log('SellSuite Expiration Processing Error: ' . $e->getMessage());
        }
    }

    /**
     * PHASE 8: Refresh exchange rates.
     *
     * This is called by the WordPress scheduled cron job
     * and can be extended to fetch rates from external APIs.
     */
    public function refresh_exchange_rates() {
        try {
            // This can be extended to fetch rates from external services
            // For now, it just triggers the action for logging/tracking
            do_action('sellsuite_exchange_rates_refreshed');

        } catch (Exception $e) {
            error_log('SellSuite Exchange Rate Refresh Error: ' . $e->getMessage());
        }
    }

}
