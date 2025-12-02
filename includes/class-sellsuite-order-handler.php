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
        
        // Handle refunds
        add_action('woocommerce_order_refunded', array(self::class, 'handle_order_refund'), 10, 2);
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
            if (!Points_Manager::is_enabled()) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id || $user_id <= 0) {
                return false;
            }

            $total_points = 0;

            // Calculate points for each line item
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();
                $line_total = $item->get_total();

                // Get product points (fixed or percentage-based)
                $product_points = Product_Meta::get_product_points($product_id, $line_total / $quantity);
                $item_points = $product_points * $quantity;

                $total_points += $item_points;

                // Log individual product point awarding
                do_action('sellsuite_product_points_awarded', $product_id, $quantity, $item_points, $order_id);
            }

            // Apply global order point settings if no product-specific points
            if ($total_points === 0) {
                $total_points = self::calculate_order_points($order);
            }

            if ($total_points > 0) {
                // Add pending points entry
                $ledger_id = Points_Manager::add_ledger_entry(
                    $user_id,
                    $order_id,
                    0,
                    'order_placement',
                    $total_points,
                    'pending',
                    __('Points pending for order confirmation', 'sellsuite'),
                    null
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
        if ($new_status !== 'completed') {
            return;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }

            $ledger_id = get_post_meta($order_id, '_points_ledger_id', true);
            if (!$ledger_id) {
                return;
            }

            // Get the pending points entry
            global $wpdb;
            $ledger = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_points_ledger WHERE id = %d AND status = %s",
                    $ledger_id,
                    'pending'
                )
            );

            if (!$ledger) {
                return;
            }

            // Update status from pending to earned
            Points_Manager::update_ledger_status(
                $ledger_id,
                'earned',
                __('Order completed - points earned', 'sellsuite')
            );

            do_action('sellsuite_points_earned', $order_id, $ledger->user_id, $ledger->points_amount);

        } catch (\Exception $e) {
            error_log('SellSuite Status Change Error: ' . $e->getMessage());
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
                Points_Manager::add_ledger_entry(
                    $user_id,
                    $order_id,
                    0,
                    'refund',
                    -$points_to_deduct,
                    'earned',
                    __('Points deducted due to order refund', 'sellsuite'),
                    $refund_id
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
        $settings = Points_Manager::get_settings();
        $order_total = $order->get_total();

        if ($settings['point_calculation_method'] === 'percentage') {
            return floor(($order_total * $settings['points_percentage']) / 100);
        }

        // Fixed method: points per dollar
        return floor($order_total * $settings['points_per_dollar']);
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

        if (!Points_Manager::is_enabled()) {
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
}
