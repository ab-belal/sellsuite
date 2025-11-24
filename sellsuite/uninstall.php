<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file runs when a user clicks "Delete" on the plugin from the WordPress admin.
 * It should clean up all plugin data including custom roles, options, and database tables.
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove custom user roles and capabilities
 */
function sellsuite_remove_custom_roles() {
    // Remove the custom capability from existing roles
    $admin_role = get_role('administrator');
    if ($admin_role) {
        $admin_role->remove_cap('product_viewer');
    }

    $shop_manager_role = get_role('shop_manager');
    if ($shop_manager_role) {
        $shop_manager_role->remove_cap('product_viewer');
    }

    // Remove the product_viewer role
    remove_role('product_viewer');
}

/**
 * Remove plugin options
 */
function sellsuite_remove_options() {
    delete_option('sellsuite_settings');
    delete_transient('sellsuite_cache');
}

/**
 * Remove database tables (optional - uncomment if you want to remove tables on uninstall)
 */
function sellsuite_remove_tables() {
    global $wpdb;
    
    // Uncomment the line below to drop the table on uninstall
    // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sellsuite_points");
}

// Execute cleanup
sellsuite_remove_custom_roles();
sellsuite_remove_options();
// sellsuite_remove_tables(); // Uncomment if you want to remove tables

// Flush rewrite rules
flush_rewrite_rules();
