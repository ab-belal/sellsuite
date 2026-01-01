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
            
            // Get pending redemption points and calculate adjusted available
            $pending_redemption_points = self::get_pending_redemption_points($user_id);
            $adjusted_available = max(0, $available_balance - $pending_redemption_points);
            
            // Check against adjusted available (not total available)
            if ($adjusted_available < $points) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        __('Insufficient available points. You have %d points available to redeem (Total: %d, Waiting to Redeem: %d)', 'sellsuite'),
                        $adjusted_available,
                        $available_balance,
                        $pending_redemption_points
                    ),
                    'code' => 'insufficient_balance',
                    'available_balance' => $adjusted_available,
                    'total_available' => $available_balance,
                    'pending_redemption' => $pending_redemption_points,
                );
            }

            // Get settings for conversion
            $settings = Points::get_settings();
            $points_per_currency_unit = isset($options['conversion_rate']) ? floatval($options['conversion_rate']) : $settings['conversion_rate'];
            $currency = isset($options['currency']) ? sanitize_text_field($options['currency']) : 'USD';

            // Validate conversion rate
            if ($points_per_currency_unit <= 0) {
                return array(
                    'success' => false,
                    'message' => __('Invalid conversion rate configuration', 'sellsuite'),
                    'code' => 'invalid_conversion_rate',
                );
            }

            // Calculate discount value: discount = redeemed_points / points_per_currency_unit
            $discount_value = $points / $points_per_currency_unit;
            $discount_value = round($discount_value, wc_get_price_decimals());

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

            // Store redemption data in user meta for temporary use during checkout
            // Database record will be created after order is placed using WooCommerce hook
            $redemption_data = array(
                'user_id' => $user_id,
                'order_id' => intval($order_id),
                'redeemed_points' => $points,
                'discount_value' => $discount_value,
                'conversion_rate' => $points_per_currency_unit,
                'currency' => $currency,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
            );
            
            // Store in user meta temporarily - will be processed after order placement
            update_user_meta($user_id, '_pending_point_redemption', $redemption_data);

            // Generate a temporary redemption ID for front-end reference
            $temp_redemption_id = wp_generate_uuid4();
            
            // Generate ledger ID without creating database entry
            global $wpdb;
            $ledger_id = Points::generate_ledger_id();
            $redemption_id = $wpdb->insert_id;

            // If order ID provided, add order meta for display on thank you page and order details
            if ($order_id > 0) {
                add_post_meta($order_id, '_points_redeemed_redemption_id', $temp_redemption_id);
                add_post_meta($order_id, '_points_discount_applied', $discount_value);
                add_post_meta($order_id, '_points_ledger_id', $ledger_id);
            }

            if (!$ledger_id) {
                return array(
                    'success' => false,
                    'message' => __('Failed to generate ledger entry', 'sellsuite'),
                    'code' => 'ledger_error',
                );
            }

            // DO NOT store in user meta - redemption is only applied when explicitly submitted
            // via form POST data during current checkout session
            // This prevents auto-apply on page reload from previous sessions

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Successfully redeemed %d points for %s %s discount', 'sellsuite'),
                    $points,
                    $discount_value,
                    $currency
                ),
                'code' => 'redemption_successful',
                'redemption_id' => $temp_redemption_id,
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
            $restore_ledger_id = Points::add_points_entry(
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
            } else {
                // Clear pending redemption from user meta if not applied to an order yet
                delete_user_meta($redemption->user_id, '_pending_point_redemption_id');
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

    
    /**
     * Create redemption record in database after order is placed.
     * 
     * This is called by WooCommerce hook when order is completed.
     * 
     * @param int $order_id WooCommerce Order ID
     * @return array Status
     */
    public static function create_redemption_record_for_order($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                error_log('SellSuite: Order not found for redemption record creation. Order ID: ' . $order_id);
                return array(
                    'success' => false,
                    'message' => __('Order not found', 'sellsuite'),
                );
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                // Guest checkout - skip redemption record
                return array(
                    'success' => false,
                    'message' => __('Guest checkout - redemption not available', 'sellsuite'),
                );
            }

            // Get pending redemption data from user meta
            $redemption_data = get_user_meta($user_id, '_pending_point_redemption', true);
            
            if (!$redemption_data || empty($redemption_data)) {
                // No pending redemption - nothing to do
                return array(
                    'success' => false,
                    'message' => __('No pending redemption found', 'sellsuite'),
                );
            }

            global $wpdb;

            // Insert redemption record with actual order ID
            $redemption_inserted = $wpdb->insert(
                $wpdb->prefix . 'sellsuite_point_redemptions',
                array(
                    'user_id' => $user_id,
                    'order_id' => $order_id,
                    'ledger_id' => 0,  // Will be updated after ledger entry
                    'redeemed_points' => intval($redemption_data['redeemed_points']),
                    'discount_value' => floatval($redemption_data['discount_value']),
                    'conversion_rate' => floatval($redemption_data['conversion_rate']),
                    'currency' => sanitize_text_field($redemption_data['currency']),
                    'status' => 'completed',  // Mark as completed since order is placed
                    'created_at' => current_time('mysql'),
                ),
                array(
                    '%d', '%d', '%d', '%d', '%f', '%f', '%s', '%s'
                )
            );

            if (!$redemption_inserted) {
                error_log('SellSuite: Failed to insert redemption record for order ' . $order_id);
                return array(
                    'success' => false,
                    'message' => __('Failed to create redemption record', 'sellsuite'),
                    'code' => 'database_error',
                );
            }

            $redemption_id = $wpdb->insert_id;

            $ledger_id = Points::generate_ledger_id();

            // Create ledger deduction entry with product ID
            // $ledger_id = Points::add_points_entry(
            //     $user_id,
            //     intval($redemption_data['redeemed_points']),
            //     'redemption',
            //     sprintf(
            //         __('Points redeemed for order #%d: %s %s', 'sellsuite'),
            //         $order_id,
            //         $redemption_data['discount_value'],
            //         $redemption_data['currency']
            //     ),
            //     'redeemed',
            //     $order_id,
            //     $first_product_id
            // );

            if (!$ledger_id) {
                // Rollback redemption record
                $wpdb->delete(
                    $wpdb->prefix . 'sellsuite_point_redemptions',
                    array('id' => $redemption_id),
                    array('%d')
                );

                error_log('SellSuite: Failed to create ledger entry for order ' . $order_id);
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

            // Store redemption ID in order meta
            add_post_meta($order_id, '_points_redeemed_redemption_id', $redemption_id);
            add_post_meta($order_id, '_points_discount_applied', $redemption_data['discount_value']);

            // Clear pending redemption from user meta
            delete_user_meta($user_id, '_pending_point_redemption');

            // Fire action hook
            do_action(
                'sellsuite_points_redeemed_on_order',
                $user_id,
                intval($redemption_data['redeemed_points']),
                floatval($redemption_data['discount_value']),
                $order_id,
                $redemption_id
            );

            error_log('SellSuite: Redemption record created successfully for order ' . $order_id . '. Redemption ID: ' . $redemption_id);

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Redemption record created for order #%d', 'sellsuite'),
                    $order_id
                ),
                'redemption_id' => $redemption_id,
                'order_id' => $order_id,
            );

        } catch (\Exception $e) {
            error_log('SellSuite: Error creating redemption record: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred while creating redemption record', 'sellsuite'),
                'code' => 'system_error',
            );
        }
    }

    /**
     * Get pending redemption points (points from incomplete/pending orders).
     * These are points that have been redeemed but order is not yet completed.
     * Only counts redemptions linked to actual orders (order_id > 0).
     * 
     * @param int $user_id User ID
     * @return int Total pending redemption points
     */
    public static function get_pending_redemption_points($user_id) {
        global $wpdb;

        // Query the point_redemptions table for pending status entries
        // Only count redemptions with actual order_id (order_id > 0)
        // This ensures waiting points only show after order is placed, not when user applies redemption
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(redeemed_points) FROM {$wpdb->prefix}sellsuite_point_redemptions 
                WHERE user_id = %d AND order_id > 0 AND status = 'pending'",
                $user_id
            )
        );

        return intval($result ?: 0);
    }
}