<?php
namespace SellSuite;

/**
 * Handle order-related point operations.
 * 
 * Manages point awarding, refunds, and pending status transitions
 * based on order lifecycle events.
 */
class Order_Handler {

    /**
     * Initialize order hooks.
     * 
     * @return void
     */
    public static function init() {
        // Award pending points when order is placed
        add_action('woocommerce_thankyou', array(self::class, 'award_points_for_order'), 10, 1);
        add_action('woocommerce_order_status_changed', array(self::class, 'on_order_status_changed'), 10, 3);
        
        // Handle redemptions
        add_action('woocommerce_thankyou', array(self::class, 'handle_redemption_on_order'), 11, 1);
        add_action('woocommerce_order_status_changed', array(self::class, 'handle_redemption_status_change'), 11, 3);
        
        // Handle refunds
        add_action('woocommerce_order_refunded', array(self::class, 'handle_order_refund'), 10, 2);
        add_action('woocommerce_order_refunded', array(self::class, 'handle_redemption_on_refund'), 11, 2);
    }

    /**
     * Award pending points when order is placed.
     * 
     * @param int $order_id Order ID
     * @return bool Success
     */
    public static function award_points_for_order($order_id) {
        if (!$order_id) {
            return false;
        }

        // Verify order hasn't been processed
        if (get_post_meta($order_id, '_points_awarded', true)) {
            return false;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            // Check if points system is enabled
            if (!Points::is_points_enabled()) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id || $user_id <= 0) {
                return false;
            }

            $total_points = 0;
            $product_ids = array();

            // Calculate points for each line item
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $line_total = $item->get_total();

                // Get product points with priority: custom value > calculated from global setting
                $product_points = Points::get_product_display_points($product_id, $line_total / $quantity);
                $item_points = $product_points * $quantity;

                $total_points += $item_points;
                $product_ids[] = $product_id;

                // Log individual product point awarding
                do_action('sellsuite_product_points_awarded', $product_id, $quantity, $item_points, $order_id);
            }

            // Apply global order point settings if no product-specific points
            if ($total_points === 0) {
                $total_points = self::calculate_order_points($order);
            }

            if ($total_points > 0) {
                // Add pending points entry (save first product_id if available)
                $first_product_id = !empty($product_ids) ? $product_ids[0] : null;
                $ledger_id = Points::add_ledger_entry(
                    $user_id,
                    $total_points,
                    'order_placement',
                    __('Points pending for order confirmation', 'sellsuite'),
                    'pending',
                    $order_id,
                    $first_product_id
                );

                // Mark order as processed
                update_post_meta($order_id, '_points_awarded', true);
                update_post_meta($order_id, '_points_ledger_id', $ledger_id);

                do_action('sellsuite_points_awarded_pending', $order_id, $user_id, $total_points);
                return true;
            }

            // Mark as processed even if no points
            update_post_meta($order_id, '_points_awarded', true);
            return true;

        } catch (\Exception $e) {
            // Log error safely
            error_log('SellSuite Order Handler Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle order status changes.
     * 
     * Transition points from pending to earned when order is completed.
     * 
     * @param int    $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @return void
     */
    public static function on_order_status_changed($order_id, $old_status, $new_status) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

            $ledger_id = get_post_meta($order_id, '_points_ledger_id', true);
            if (!$ledger_id) {
                return;
            }

            global $wpdb;
            $ledger = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_points_ledger WHERE id = %d",
                    $ledger_id
                )
            );

            if (!$ledger) {
                return;
            }

            $table = $wpdb->prefix . 'sellsuite_points_ledger';

            // Handle order completion - transition pending to earned
            if ($new_status === 'completed') {
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'earned',
                        'description' => sprintf(__('Points earned from order #%d - Order completed', 'sellsuite'), $order_id),
                        'notes' => __('Order completed - points earned', 'sellsuite')
                    ),
                    array('id' => $ledger_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }
            // Handle order cancellation - mark points as cancelled
            elseif ($new_status === 'cancelled') {
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'cancelled',
                        'description' => sprintf(__('Points cancelled - Order #%d was cancelled', 'sellsuite'), $order_id),
                        'notes' => __('Order cancelled - points cancelled', 'sellsuite')
                    ),
                    array('id' => $ledger_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }
            // Handle order refund - mark points as refunded
            elseif ($new_status === 'refunded') {
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'refunded',
                        'description' => sprintf(__('Points refunded - Order #%d was refunded', 'sellsuite'), $order_id),
                        'notes' => __('Order refunded - points deducted', 'sellsuite')
                    ),
                    array('id' => $ledger_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }
            // Handle order pending (processing) - keep as pending
            elseif ($new_status === 'pending') {
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'pending',
                        'description' => sprintf(__('Points pending - Order #%d is being processed', 'sellsuite'), $order_id),
                        'notes' => __('Order pending - points pending', 'sellsuite')
                    ),
                    array('id' => $ledger_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }

            do_action('sellsuite_points_status_updated', $order_id, $ledger->user_id, $new_status);

        } catch (\Exception $e) {
            error_log('SellSuite Status Change Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle redemption status changes based on order status.
     * 
     * Wrapper method to handle redemption status transitions when order status changes.
     * 
     * @param int    $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @return void
     */
    public static function handle_redemption_status_change($order_id, $old_status, $new_status) {
        if ($new_status === 'completed') {
            self::handle_redemption_on_complete($order_id);
        } elseif ($new_status === 'refunded') {
            // Note: Refund is handled by handle_redemption_on_refund via woocommerce_order_refunded hook
            // This is just for status tracking if needed
        } elseif ($new_status === 'cancelled') {
            // Mark redemption as cancelled if order is cancelled
            $redemption_id = get_post_meta($order_id, '_points_redeemed_redemption_id', true);
            if ($redemption_id) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'sellsuite_point_redemptions',
                    array('status' => 'cancelled'),
                    array('id' => $redemption_id),
                    array('%s'),
                    array('%d')
                );
            }
        }
    }

    /**
     * Handle order refunds.
     * 
     * Deduct points when order is refunded.
     * 
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     * @return void
     */
    public static function handle_order_refund($order_id, $refund_id) {
        try {
            $order = wc_get_order($order_id);
            $refund = wc_get_order($refund_id);

            if (!$order || !$refund) {
                return;
            }

            $user_id = $order->get_user_id();
            if (!$user_id || $user_id <= 0) {
                return;
            }

            // Check if points were already deducted
            if (get_post_meta($refund_id, '_points_deducted', true)) {
                return;
            }

            // Calculate points to deduct
            $refund_amount = abs($refund->get_total());
            $order_total = $order->get_total();

            // Get original points awarded
            $ledger_id = get_post_meta($order_id, '_points_ledger_id', true);
            if (!$ledger_id) {
                return;
            }

            global $wpdb;
            $original_ledger = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT points_amount FROM {$wpdb->prefix}sellsuite_points_ledger WHERE id = %d",
                    $ledger_id
                )
            );

            if (!$original_ledger) {
                return;
            }

            // Calculate proportional points to deduct
            $points_to_deduct = round(($refund_amount / $order_total) * $original_ledger->points_amount);

            if ($points_to_deduct > 0) {
                // Add deduction entry
                Points::add_ledger_entry(
                    $user_id,
                    $points_to_deduct,
                    'refund',
                    __('Points deducted due to order refund', 'sellsuite'),
                    'refunded',
                    $order_id
                );

                // Mark refund as processed
                update_post_meta($refund_id, '_points_deducted', true);

                do_action('sellsuite_points_deducted_refund', $order_id, $user_id, $points_to_deduct, $refund_id);
            }

        } catch (\Exception $e) {
            error_log('SellSuite Refund Handler Error: ' . $e->getMessage());
        }
    }

    /**
     * Calculate order points using global settings.
     * 
     * @param WC_Order $order WooCommerce order object
     * @return int Total points
     */
    private static function calculate_order_points($order) {
        $settings = Points::get_settings();
        $order_total = $order->get_total();

        if ($settings['point_calculation_method'] === 'percentage') {
            return floor(($order_total * $settings['points_percentage']) / 100);
        }

        // Fixed method: points per currency
        return floor($order_total * $settings['points_per_currency']);
    }

    /**
     * Get order points summary.
     * 
     * @param int $order_id Order ID
     * @return array Points information
     */
    public static function get_order_points_summary($order_id) {
        global $wpdb;

        $ledger_id = get_post_meta($order_id, '_points_ledger_id', true);
        if (!$ledger_id) {
            return array(
                'points_awarded' => 0,
                'points_status' => 'none',
                'created_at' => null,
            );
        }

        $ledger = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT points_amount, status, created_at FROM {$wpdb->prefix}sellsuite_points_ledger WHERE id = %d",
                $ledger_id
            )
        );

        if (!$ledger) {
            return array(
                'points_awarded' => 0,
                'points_status' => 'none',
                'created_at' => null,
            );
        }

        return array(
            'points_awarded' => intval($ledger->points_amount),
            'points_status' => sanitize_text_field($ledger->status),
            'created_at' => $ledger->created_at,
        );
    }

    /**
     * Validate order before processing points.
     * 
     * @param int $order_id Order ID
     * @return array Status with message
     */
    public static function validate_order($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return array(
                'valid' => false,
                'message' => __('Order not found', 'sellsuite'),
            );
        }

        $user_id = $order->get_user_id();
        if (!$user_id || $user_id <= 0) {
            return array(
                'valid' => false,
                'message' => __('Order has no associated user', 'sellsuite'),
            );
        }

        if ($order->get_item_count() === 0) {
            return array(
                'valid' => false,
                'message' => __('Order has no items', 'sellsuite'),
            );
        }

        if (!Points::is_points_enabled()) {
            return array(
                'valid' => false,
                'message' => __('Points system is disabled', 'sellsuite'),
            );
        }

        return array(
            'valid' => true,
            'message' => __('Order is valid for points processing', 'sellsuite'),
        );
    }

    /**
     * Handle redemption points deduction on order placement.
     * 
     * Deducts redeemed points from user's balance when order is placed.
     * 
     * @param int $order_id Order ID
     * @return bool Success
     */
    public static function handle_redemption_on_order($order_id) {
        if (!$order_id) {
            return false;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                return true; // Guest checkout - skip points handling
            }

            // Check if order has a redemption
            $redemption_id = get_post_meta($order_id, '_points_redeemed_redemption_id', true);
            if (!$redemption_id) {
                return true; // No redemption on this order
            }

            global $wpdb;
            $redemption = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_point_redemptions WHERE id = %d",
                    $redemption_id
                )
            );

            if (!$redemption || intval($redemption->user_id) !== $user_id) {
                return false;
            }

            // Mark redemption as applied (pending)
            $wpdb->update(
                $wpdb->prefix . 'sellsuite_point_redemptions',
                array('status' => 'pending'),
                array('id' => $redemption_id),
                array('%s'),
                array('%d')
            );

            do_action('sellsuite_redemption_applied_on_order', $order_id, $user_id, $redemption_id);

            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Redemption On Order Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle redemption completion when order is completed.
     * 
     * Marks redeemed points as earned/completed when order is completed.
     * 
     * @param int $order_id Order ID
     * @return bool Success
     */
    public static function handle_redemption_on_complete($order_id) {
        if (!$order_id) {
            return false;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                return true; // Guest checkout
            }

            // Get redemption ID from order
            $redemption_id = get_post_meta($order_id, '_points_redeemed_redemption_id', true);
            if (!$redemption_id) {
                return true; // No redemption
            }

            global $wpdb;
            $redemption = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_point_redemptions WHERE id = %d",
                    $redemption_id
                )
            );

            if (!$redemption) {
                return false;
            }

            // Update redemption status to completed
            $wpdb->update(
                $wpdb->prefix . 'sellsuite_point_redemptions',
                array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql')
                ),
                array('id' => $redemption_id),
                array('%s', '%s'),
                array('%d')
            );

            // Update ledger entry status if exists
            if ($redemption->ledger_id) {
                $wpdb->update(
                    $wpdb->prefix . 'sellsuite_points_ledger',
                    array(
                        'status' => 'earned',
                        'description' => sprintf(__('Redeemed points confirmed - Order #%d completed', 'sellsuite'), $order_id),
                        'notes' => __('Redemption completed - order confirmed', 'sellsuite')
                    ),
                    array('id' => $redemption->ledger_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }

            do_action('sellsuite_redemption_completed', $order_id, $user_id, $redemption_id);

            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Redemption Complete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle redemption refund when order is refunded.
     * 
     * Restores redeemed points to user when order is refunded.
     * 
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     * @return bool Success
     */
    public static function handle_redemption_on_refund($order_id, $refund_id) {
        if (!$order_id) {
            return false;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id) {
                return true; // Guest checkout
            }

            // Get redemption ID from order
            $redemption_id = get_post_meta($order_id, '_points_redeemed_redemption_id', true);
            if (!$redemption_id) {
                return true; // No redemption
            }

            global $wpdb;
            $redemption = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_point_redemptions WHERE id = %d",
                    $redemption_id
                )
            );

            if (!$redemption) {
                return false;
            }

            // Restore the redeemed points
            $restore_ledger_id = Points::add_ledger_entry(
                $user_id,
                $redemption->redeemed_points,
                'redemption_refund',
                sprintf(__('Redeemed points restored - Order #%d refunded', 'sellsuite'), $order_id),
                'earned',
                $order_id
            );

            if (!$restore_ledger_id) {
                return false;
            }

            // Update redemption status to refunded
            $wpdb->update(
                $wpdb->prefix . 'sellsuite_point_redemptions',
                array(
                    'status' => 'refunded',
                    'refunded_at' => current_time('mysql'),
                    'refund_id' => intval($refund_id)
                ),
                array('id' => $redemption_id),
                array('%s', '%s', '%d'),
                array('%d')
            );

            // Update original ledger entry
            if ($redemption->ledger_id) {
                $wpdb->update(
                    $wpdb->prefix . 'sellsuite_points_ledger',
                    array(
                        'status' => 'refunded',
                        'description' => sprintf(__('Redemption refunded - Order #%d refunded', 'sellsuite'), $order_id),
                        'notes' => __('Order refunded - points restored', 'sellsuite')
                    ),
                    array('id' => $redemption->ledger_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
            }

            do_action('sellsuite_redemption_refunded', $order_id, $user_id, $redemption_id, $refund_id);

            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Redemption Refund Error: ' . $e->getMessage());
            return false;
        }
    }
}

