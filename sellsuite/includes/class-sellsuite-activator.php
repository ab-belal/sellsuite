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

        // Create custom user roles
        self::create_custom_roles();

        // Register custom endpoints before flushing rewrite rules
        // This ensures the endpoint is available immediately after activation
        add_rewrite_endpoint('products-info', EP_ROOT | EP_PAGES);

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

    /**
     * Create custom user roles and capabilities.
     *
     * This method creates the 'product_viewer' role with the custom
     * 'product_viewer' capability along with basic WordPress capabilities.
     */
    private static function create_custom_roles() {
        // Get the current role to check if it already exists
        $role = get_role('product_viewer');

        // Only create the role if it doesn't exist yet
        // This prevents duplicate role creation on re-activation
        if (null === $role) {
            add_role(
                'product_viewer',                    // Role slug (used internally)
                __('Product Viewer', 'sellsuite'),   // Display name (shown in admin)
                array(
                    // Basic WordPress capabilities
                    'read' => true,                  // Allows user to login and access dashboard
                    
                    // Custom capability for viewing product information
                    'product_viewer' => true,        // Your custom capability
                )
            );
        } else {
            // If role exists, ensure it has the latest capabilities
            // This is useful if you update capabilities in a plugin update
            $role->add_cap('product_viewer', true);
            $role->add_cap('read', true);
        }

        // Optional: Add the custom capability to other roles if needed
        // For example, administrators should have all capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('product_viewer', true);
        }

        // Optional: Add to shop_manager if WooCommerce is active
        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_role->add_cap('product_viewer', true);
        }
    }
}
