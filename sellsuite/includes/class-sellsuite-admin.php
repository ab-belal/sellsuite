<?php
namespace SellSuite;

/**
 * The admin-specific functionality of the plugin.
 */
class Admin {

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        // Add main menu page
        add_menu_page(
            __('SellSuite', 'sellsuite'),           // Page title
            __('SellSuite', 'sellsuite'),           // Menu title
            'manage_woocommerce',                    // Capability
            'sellsuite',                             // Menu slug
            array($this, 'render_dashboard_page'),  // Callback function
            'dashicons-store',                       // Icon (WooCommerce store icon)
            56                                       // Position (after WooCommerce which is at 55)
        );

        // Add Dashboard submenu (will replace the duplicate main menu item)
        add_submenu_page(
            'sellsuite',                             // Parent slug
            __('Dashboard', 'sellsuite'),            // Page title
            __('Dashboard', 'sellsuite'),            // Menu title
            'manage_woocommerce',                    // Capability
            'sellsuite',                             // Menu slug (same as parent to replace duplicate)
            array($this, 'render_dashboard_page')   // Callback function
        );

        // Add Settings submenu
        add_submenu_page(
            'sellsuite',                             // Parent slug
            __('Settings', 'sellsuite'),             // Page title
            __('Settings', 'sellsuite'),             // Menu title
            'manage_woocommerce',                    // Capability
            'sellsuite-settings',                    // Menu slug
            array($this, 'render_settings_page')    // Callback function
        );
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SellSuite Dashboard', 'sellsuite'); ?></h1>
            <div id="sellsuite-admin-app"></div>
        </div>
        <?php
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('SellSuite Settings', 'sellsuite'); ?></h1>
            <div id="sellsuite-settings-app"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_sellsuite' && $hook !== 'sellsuite_page_sellsuite-settings') {
            return;
        }

        // Enqueue WordPress components styles
        wp_enqueue_style('wp-components');

        // Enqueue custom admin styles
        wp_enqueue_style(
            'sellsuite-admin-css',
            SELLSUITE_PLUGIN_URL . 'assets/css/admin.css',
            array('wp-components'),
            SELLSUITE_VERSION
        );

        // Enqueue React app (will be built by webpack/gulp)
        wp_enqueue_script(
            'sellsuite-admin-js',
            SELLSUITE_PLUGIN_URL . 'admin/build/index.js',
            array('wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'),
            SELLSUITE_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script('sellsuite-admin-js', 'sellsuiteData', array(
            'apiUrl' => rest_url('sellsuite/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentPage' => $hook === 'sellsuite_page_sellsuite-settings' ? 'settings' : 'dashboard',
        ));
    }
}
