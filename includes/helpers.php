<?php
namespace SellSuite;

/**
 * Helper functions for SellSuite plugin.
 */

/**
 * Check if the current user has product viewer capability.
 *
 * This helper function checks if the current logged-in user has the
 * 'product_viewer' capability. Use this throughout your plugin
 * to control access to product information.
 *
 * @return bool True if user has the capability, false otherwise.
 */
function user_can_view_products() {
    return current_user_can('product_viewer');
}

/**
 * Check if a specific user has product viewer capability.
 *
 * @param int $user_id The user ID to check.
 * @return bool True if user has the capability, false otherwise.
 */
function user_has_product_viewer_capability($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    return user_can($user, 'product_viewer');
}

/**
 * Get all users with the product_viewer role.
 *
 * @return array Array of WP_User objects.
 */
function get_product_viewers() {
    $args = array(
        'role' => 'product_viewer',
        'orderby' => 'display_name',
    );
    return get_users($args);
}
