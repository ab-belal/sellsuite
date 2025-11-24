<?php
namespace SellSuite;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

    /**
     * Activate the plugin.
     *
     * Check for WooCommerce and create necessary database tables.
     */
    public static function activate() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(SELLSUITE_PLUGIN_BASENAME);
            wp_die(
                esc_html__('SellSuite requires WooCommerce to be installed and active.', 'sellsuite'),
                esc_html__('Plugin Activation Error', 'sellsuite'),
                array('back_link' => true)
            );
        }

        // Create database tables
        self::create_tables();

        // Set default options
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create plugin database tables.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'sellsuite_points';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points int(11) NOT NULL DEFAULT 0,
            order_id bigint(20) DEFAULT NULL,
            action_type varchar(50) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $default_options = array(
            'points_enabled' => true,
            'points_per_dollar' => 1,
            'points_redemption_rate' => 100,
            'points_expiry_days' => 365,
        );

        if (!get_option('sellsuite_settings')) {
            add_option('sellsuite_settings', $default_options);
        }
    }
}
