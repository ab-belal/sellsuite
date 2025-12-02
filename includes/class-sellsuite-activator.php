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

        // Drop old tables if they exist to start fresh
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_points_ledger");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_point_redemptions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_points");

        // 1. Points Ledger Table - Permanent audit log of all point transactions
        $points_ledger_table = $wpdb->prefix . 'sellsuite_points_ledger';
        $points_ledger_sql = "CREATE TABLE $points_ledger_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) DEFAULT NULL,
            product_id bigint(20) DEFAULT NULL,
            action_type varchar(50) NOT NULL DEFAULT 'manual',
            points_amount int(11) NOT NULL DEFAULT 0,
            status varchar(30) NOT NULL DEFAULT 'earned',
            description text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 2. Point Redemptions Table - Track point redemptions separately
        $redemptions_table = $wpdb->prefix . 'sellsuite_point_redemptions';
        $redemptions_sql = "CREATE TABLE $redemptions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            ledger_id bigint(20) NOT NULL,
            redeemed_points int(11) NOT NULL DEFAULT 0,
            discount_value decimal(10, 2) NOT NULL DEFAULT 0.00,
            conversion_rate decimal(10, 4) NOT NULL DEFAULT 1.0000,
            currency varchar(10) DEFAULT 'USD',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            notes text,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY ledger_id (ledger_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 3. Old points table (legacy support)
        $old_points_table = $wpdb->prefix . 'sellsuite_points';
        $old_points_sql = "CREATE TABLE $old_points_table (
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
        dbDelta($points_ledger_sql);
        dbDelta($redemptions_sql);
        dbDelta($old_points_sql);
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $default_options = array(
            // Points System Settings
            'points_enabled' => true,
            'conversion_rate' => 1,  // 1 point = 1 currency unit
            'max_redeemable_percentage' => 20,  // Can redeem up to 20% of order
            'enable_expiry' => false,
            'expiry_days' => 365,
            'point_calculation_method' => 'fixed',  // 'fixed' or 'percentage'
            'points_per_dollar' => 1,  // For fixed method
            'points_percentage' => 0,   // For percentage method (% of product price)
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
