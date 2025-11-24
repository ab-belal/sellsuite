<?php
namespace SellSuite;

/**
 * Helper functions for SellSuite plugin.
 */

/**
 * Format points for display.
 */
function format_points($points) {
    return number_format_i18n($points);
}

/**
 * Check if points system is enabled.
 */
function is_points_enabled() {
    $settings = get_option('sellsuite_settings', array());
    return isset($settings['points_enabled']) ? (bool) $settings['points_enabled'] : true;
}

/**
 * Get points redemption rate.
 */
function get_redemption_rate() {
    $settings = get_option('sellsuite_settings', array());
    return isset($settings['points_redemption_rate']) ? intval($settings['points_redemption_rate']) : 100;
}

/**
 * Convert points to currency value.
 */
function points_to_currency($points) {
    $rate = get_redemption_rate();
    if ($rate <= 0) {
        return 0;
    }
    return $points / $rate;
}

/**
 * Convert currency to points.
 */
function currency_to_points($amount) {
    $settings = get_option('sellsuite_settings', array());
    $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;
    return floor($amount * $points_per_dollar);
}

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
