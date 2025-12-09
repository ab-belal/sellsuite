<?php
namespace SellSuite;

/**
 * Handle point redemption operations.
 * 
 * Manages redeeming earned points for discounts with validation and conditions.
 */
class Redeem_Handler {

    /**
     * Redeem points for discount.
     * 
     * @param int   $user_id User ID
     * @param int   $points Points to redeem
     * @param int   $order_id Optional order ID for the redemption
     * @param array $options Additional options (currency, conversion_rate)
     * @return array Status with redemption details
     */
    public static function redeem_points($user_id, $points, $order_id = 0, $options = array()) {
        try {
            // Validate user
            $user = get_userdata($user_id);
            if (!$user) {
                return array(
                    'success' => false,
                    'message' => __('User not found', 'sellsuite'),
                    'code' => 'invalid_user',
                );
            }

            // Validate points system
            if (!Points::is_points_enabled()) {
                return array(
                    'success' => false,
                    'message' => __('Points system is disabled', 'sellsuite'),
                    'code' => 'system_disabled',
                );
            }

            // Validate points amount
            $points = intval($points);
            if ($points <= 0) {
                return array(
                    'success' => false,
                    'message' => __('Points must be greater than zero', 'sellsuite'),
                    'code' => 'invalid_points_amount',
                );
            }

            // Get user's available balance
            $available_balance = Points::get_available_balance($user_id);
            if ($available_balance < $points) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        __('Insufficient points. Available: %d, Requested: %d', 'sellsuite'),
                        $available_balance,
                        $points
                    ),
                    'code' => 'insufficient_balance',
                    'available_balance' => $available_balance,
                );
            }

            // Get settings for conversion
            $settings = Points::get_settings();
            $conversion_rate = isset($options['conversion_rate']) ? floatval($options['conversion_rate']) : $settings['conversion_rate'];
            $currency = isset($options['currency']) ? sanitize_text_field($options['currency']) : 'USD';

            // Calculate discount value
            $discount_value = $points * $conversion_rate;

            // Validate maximum redeemable percentage for order
            if ($order_id > 0) {
                $validation = self::validate_order_redemption($order_id, $discount_value, $settings);
                if (!$validation['valid']) {
                    return array(
                        'success' => false,
                        'message' => $validation['message'],
                        'code' => 'redemption_limit_exceeded',
                        'max_redeemable' => $validation['max_redeemable'],
                    );
                }
            }

            // Create redemption record
            global $wpdb;
            $redemption_inserted = $wpdb->insert(
                $wpdb->prefix . 'sellsuite_point_redemptions',
                array(
                    'user_id' => $user_id,
                    'order_id' => intval($order_id),
                    'ledger_id' => 0,  // Will be updated by deduction
                    'redeemed_points' => $points,
                    'discount_value' => $discount_value,
                    'conversion_rate' => $conversion_rate,
                    'currency' => $currency,
                    'created_at' => current_time('mysql'),
                ),
                array(
                    '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s'
                )
            );

            if (!$redemption_inserted) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create redemption record', 'sellsuite'),
                    'code' => 'database_error',
                );
            }

            $redemption_id = $wpdb->insert_id;

            // Create ledger deduction entry
            $ledger_id = Points::add_ledger_entry(
                $user_id,
                $points,
                'redemption',
                sprintf(__('Points redeemed for discount: %s %s', 'sellsuite'), $discount_value, $currency),
                'redeemed',
                intval($order_id) > 0 ? intval($order_id) : null
            );

            if (!$ledger_id) {
                // Rollback redemption record
                $wpdb->delete(
                    $wpdb->prefix . 'sellsuite_point_redemptions',
                    array('id' => $redemption_id),
                    array('%d')
                );

                return array(
                    'success' => false,
                    'message' => __('Failed to create ledger entry', 'sellsuite'),
                    'code' => 'ledger_error',
                );
            }

            // Update redemption with ledger ID
            $wpdb->update(
                $wpdb->prefix . 'sellsuite_point_redemptions',
                array('ledger_id' => $ledger_id),
                array('id' => $redemption_id),
                array('%d'),
                array('%d')
            );

            // If order ID provided, add order meta
            if ($order_id > 0) {
                add_post_meta($order_id, '_points_redeemed_redemption_id', $redemption_id);
                add_post_meta($order_id, '_points_discount_applied', $discount_value);
            }

            do_action('sellsuite_points_redeemed', $user_id, $points, $discount_value, $order_id, $redemption_id);

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Successfully redeemed %d points for %s %s discount', 'sellsuite'),
                    $points,
                    $discount_value,
                    $currency
                ),
                'code' => 'redemption_successful',
                'redemption_id' => $redemption_id,
                'points_redeemed' => $points,
                'discount_value' => $discount_value,
                'currency' => $currency,
                'remaining_balance' => $available_balance - $points,
            );

        } catch (\Exception $e) {
            error_log('SellSuite Redeem Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred during redemption', 'sellsuite'),
                'code' => 'system_error',
            );
        }
    }

    /**
     * Cancel redemption and restore points.
     * 
     * @param int $redemption_id Redemption ID
     * @return array Status
     */
    public static function cancel_redemption($redemption_id) {
        try {
            global $wpdb;

            $redemption = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_point_redemptions WHERE id = %d",
                    $redemption_id
                )
            );

            if (!$redemption) {
                return array(
                    'success' => false,
                    'message' => __('Redemption not found', 'sellsuite'),
                );
            }

            // Check if already canceled
            if (get_post_meta($redemption->order_id, '_redemption_canceled_' . $redemption_id, true)) {
                return array(
                    'success' => false,
                    'message' => __('Redemption already canceled', 'sellsuite'),
                );
            }

            // Add back the points
            $restore_ledger_id = Points::add_ledger_entry(
                $redemption->user_id,
                $redemption->redeemed_points,
                'redemption_reversal',
                sprintf(__('Redemption #%d canceled - points restored', 'sellsuite'), $redemption_id),
                'earned',
                $redemption->order_id > 0 ? intval($redemption->order_id) : null
            );

            if (!$restore_ledger_id) {
                return array(
                    'success' => false,
                    'message' => __('Failed to restore points', 'sellsuite'),
                );
            }

            // Mark as canceled
            if ($redemption->order_id > 0) {
                add_post_meta($redemption->order_id, '_redemption_canceled_' . $redemption_id, true);
            }

            do_action('sellsuite_redemption_canceled', $redemption_id, $redemption->user_id, $redemption->redeemed_points);

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Redemption canceled and %d points restored', 'sellsuite'),
                    $redemption->redeemed_points
                ),
                'points_restored' => $redemption->redeemed_points,
            );

        } catch (\Exception $e) {
            error_log('SellSuite Redemption Cancel Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred while canceling redemption', 'sellsuite'),
            );
        }
    }

    /**
     * Validate if order can use points for redemption.
     * 
     * @param int   $order_id Order ID
     * @param float $discount_value Discount value to apply
     * @param array $settings Points settings
     * @return array Validation result
     */
    private static function validate_order_redemption($order_id, $discount_value, $settings) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'valid' => false,
                'message' => __('Order not found', 'sellsuite'),
            );
        }

        $order_total = $order->get_total();
        $max_redeemable = ($order_total * $settings['max_redeemable_percentage']) / 100;

        // Get already redeemed in this order
        $already_redeemed = floatval(get_post_meta($order_id, '_points_discount_applied', true) ?: 0);

        if (($already_redeemed + $discount_value) > $max_redeemable) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    __('Redemption exceeds maximum of %s for this order', 'sellsuite'),
                    wc_price($max_redeemable)
                ),
                'max_redeemable' => $max_redeemable,
            );
        }

        return array(
            'valid' => true,
            'message' => __('Order is valid for redemption', 'sellsuite'),
            'max_redeemable' => $max_redeemable,
        );
    }

    /**
     * Get redemption history for user.
     * 
     * @param int $user_id User ID
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array Redemption records
     */
    public static function get_user_redemptions($user_id, $limit = 20, $offset = 0) {
        global $wpdb;

        $redemptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sellsuite_point_redemptions 
                WHERE user_id = %d 
                ORDER BY created_at DESC 
                LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            )
        );

        return $redemptions ?: array();
    }

    /**
     * Get total redeemed value for user.
     * 
     * @param int $user_id User ID
     * @return float Total redeemed value
     */
    public static function get_total_redeemed($user_id) {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(discount_value) FROM {$wpdb->prefix}sellsuite_point_redemptions WHERE user_id = %d",
                $user_id
            )
        );

        return floatval($result ?: 0);
    }
}
