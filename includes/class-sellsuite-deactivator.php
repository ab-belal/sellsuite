<?php
namespace SellSuite;

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 */
class Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clean up temporary data and flush rewrite rules.
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clean up transients
        delete_transient('sellsuite_cache');

        // Clean up all pending redemption metadata from users
        self::cleanup_pending_redemptions();

        // Note: We do NOT remove the 'product_viewer' role on deactivation
        // because users might have this role assigned. Removing it would
        // cause data loss. The role will only be removed on plugin uninstall.
        // If you need to remove the role, uncomment the line below:
        // self::remove_custom_roles();
    }

    /**
     * Clean up all pending point redemption metadata from users.
     *
     * Removes the _pending_point_redemption_id meta from all users.
     */
    private static function cleanup_pending_redemptions() {
        global $wpdb;
        
        // Delete all pending redemption metadata
        $wpdb->query(
            "DELETE FROM {$wpdb->usermeta} 
             WHERE meta_key = '_pending_point_redemption_id'"
        );
    }

    /**
     * Remove custom user roles and capabilities.
     *
     * ⚠️ WARNING: This will remove the role from all users who have it assigned!
     * Only call this method if you're sure you want to delete the role.
     * Typically this should only be done on plugin uninstall, not deactivation.
     */
    private static function remove_custom_roles() {
        // Remove the custom capability from existing roles
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('product_viewer');
        }

        $shop_manager_role = get_role('shop_manager');
        if ($shop_manager_role) {
            $shop_manager_role->remove_cap('product_viewer');
        }

        // Remove the product_viewer role completely
        // This will reassign all users with this role to have no role
        remove_role('product_viewer');
    }
}
