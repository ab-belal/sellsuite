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
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_notifications");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_notification_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_audit_log");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_point_expirations");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_expiry_rules");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_exchange_rates");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_currency_conversions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_currencies");

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

        // 4. Notifications table
        $notifications_table = $wpdb->prefix . 'sellsuite_notifications';
        $notifications_sql = "CREATE TABLE $notifications_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title text NOT NULL,
            data longtext,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_read (is_read),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 5. Notification logs table
        $notification_logs_table = $wpdb->prefix . 'sellsuite_notification_logs';
        $notification_logs_sql = "CREATE TABLE $notification_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            channel varchar(50) NOT NULL,
            success tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY recipient (recipient),
            KEY type (type),
            KEY channel (channel),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 6. Audit log table
        $audit_log_table = $wpdb->prefix . 'sellsuite_audit_log';
        $audit_log_sql = "CREATE TABLE $audit_log_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            admin_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            action_type varchar(50) NOT NULL,
            points_involved int(11) NOT NULL DEFAULT 0,
            notes text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY admin_id (admin_id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 7. PHASE 7: Point Expirations Table - Track expired points
        $point_expirations_table = $wpdb->prefix . 'sellsuite_point_expirations';
        $point_expirations_sql = "CREATE TABLE $point_expirations_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            ledger_id bigint(20) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'expired',
            expiry_reason text,
            notification_sent tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY ledger_id (ledger_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 8. PHASE 7: Expiry Rules Table - Configure expiry rules
        $expiry_rules_table = $wpdb->prefix . 'sellsuite_expiry_rules';
        $expiry_rules_sql = "CREATE TABLE $expiry_rules_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            expiry_days int(11) NOT NULL DEFAULT 365,
            grace_days int(11) NOT NULL DEFAULT 30,
            action_types longtext NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            priority int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY priority (priority)
        ) $charset_collate;";

        // 9. PHASE 8: Exchange Rates Table - Manage currency exchange rates
        $exchange_rates_table = $wpdb->prefix . 'sellsuite_exchange_rates';
        $exchange_rates_sql = "CREATE TABLE $exchange_rates_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            from_currency varchar(10) NOT NULL,
            to_currency varchar(10) NOT NULL,
            rate decimal(18, 8) NOT NULL DEFAULT 1.00000000,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY currency_pair (from_currency, to_currency),
            KEY status (status)
        ) $charset_collate;";

        // 10. PHASE 8: Currency Conversions Table - Track all conversions
        $currency_conversions_table = $wpdb->prefix . 'sellsuite_currency_conversions';
        $currency_conversions_sql = "CREATE TABLE $currency_conversions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            original_amount decimal(15, 2) NOT NULL,
            original_currency varchar(10) NOT NULL,
            converted_amount decimal(15, 2) NOT NULL,
            converted_currency varchar(10) NOT NULL,
            exchange_rate decimal(18, 8) NOT NULL,
            reason varchar(50) NOT NULL DEFAULT 'redemption',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY original_currency (original_currency),
            KEY converted_currency (converted_currency),
            KEY reason (reason),
            KEY created_at (created_at)
        ) $charset_collate;";

        // 11. PHASE 8: Currencies Table - Supported currencies configuration
        $currencies_table = $wpdb->prefix . 'sellsuite_currencies';
        $currencies_sql = "CREATE TABLE $currencies_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            symbol varchar(10) NOT NULL,
            name varchar(100) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($points_ledger_sql);
        dbDelta($redemptions_sql);
        dbDelta($old_points_sql);
        dbDelta($notifications_sql);
        dbDelta($notification_logs_sql);
        dbDelta($audit_log_sql);
        dbDelta($point_expirations_sql);
        dbDelta($expiry_rules_sql);
        dbDelta($exchange_rates_sql);
        dbDelta($currency_conversions_sql);
        dbDelta($currencies_sql);
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
