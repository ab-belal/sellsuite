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
