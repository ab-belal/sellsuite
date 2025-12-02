<?php
namespace SellSuite;

/**
 * Handle refund-related point operations.
 * 
 * Manages point deductions and reversals for partial and full refunds.
 */
class Refund_Handler {

    /**
     * Initialize refund hooks.
     * 
     * @return void
     */
    public static function init() {
        add_action('woocommerce_order_fully_refunded', array(self::class, 'on_full_refund'), 10, 2);
        add_action('woocommerce_order_partially_refunded', array(self::class, 'on_partial_refund'), 10, 2);
    }

    /**
     * Handle full refund - deduct all points.
     * 
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     * @return bool Success
     */
    public static function on_full_refund($order_id, $refund_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id || $user_id <= 0) {
                return false;
            }

            // Check if already processed
            if (get_post_meta($refund_id, '_full_refund_points_processed', true)) {
                return false;
            }

            // Get all earned points from this order
            global $wpdb;
            $order_points = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT SUM(points_amount) as total_points FROM {$wpdb->prefix}sellsuite_points_ledger 
                    WHERE order_id = %d AND status IN ('earned', 'pending') AND action_type IN ('order_placement', 'bonus')",
                    $order_id
                )
            );

            if ($order_points && $order_points->total_points > 0) {
                // Deduct all points
                Points_Manager::add_ledger_entry(
                    $user_id,
                    $order_id,
                    0,
                    'full_refund',
                    -$order_points->total_points,
                    'earned',
                    __('All points deducted due to full refund', 'sellsuite'),
                    $refund_id
                );

                update_post_meta($refund_id, '_full_refund_points_processed', true);
                do_action('sellsuite_points_deducted_full_refund', $order_id, $user_id, $order_points->total_points);
                return true;
            }

            update_post_meta($refund_id, '_full_refund_points_processed', true);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Full Refund Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle partial refund - deduct proportional points.
     * 
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     * @return bool Success
     */
    public static function on_partial_refund($order_id, $refund_id) {
        try {
            $order = wc_get_order($order_id);
            $refund = wc_get_order($refund_id);

            if (!$order || !$refund) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id || $user_id <= 0) {
                return false;
            }

            // Check if already processed
            if (get_post_meta($refund_id, '_partial_refund_points_processed', true)) {
                return false;
            }

            $refund_amount = abs($refund->get_total());
            $order_total = $order->get_total();

            if ($order_total <= 0) {
                update_post_meta($refund_id, '_partial_refund_points_processed', true);
                return false;
            }

            // Calculate proportional amount
            $proportion = $refund_amount / $order_total;

            // Get all earned points from this order
            global $wpdb;
            $order_points = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT SUM(points_amount) as total_points FROM {$wpdb->prefix}sellsuite_points_ledger 
                    WHERE order_id = %d AND status IN ('earned', 'pending') AND action_type IN ('order_placement', 'bonus')",
                    $order_id
                )
            );

            if ($order_points && $order_points->total_points > 0) {
                // Deduct proportional points
                $points_to_deduct = floor($order_points->total_points * $proportion);

                if ($points_to_deduct > 0) {
                    Points_Manager::add_ledger_entry(
                        $user_id,
                        $order_id,
                        0,
                        'partial_refund',
                        -$points_to_deduct,
                        'earned',
                        sprintf(__('Points deducted for partial refund (%.2f%% of order)', 'sellsuite'), $proportion * 100),
                        $refund_id
                    );

                    do_action('sellsuite_points_deducted_partial_refund', $order_id, $user_id, $points_to_deduct);
                }
            }

            update_post_meta($refund_id, '_partial_refund_points_processed', true);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Partial Refund Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Reverse refund and restore points.
     * 
     * Used when a refund is canceled/undone.
     * 
     * @param int $refund_id Refund ID
     * @return bool Success
     */
    public static function reverse_refund($refund_id) {
        try {
            $refund = wc_get_order($refund_id);
            if (!$refund) {
                return false;
            }

            $order_id = $refund->get_parent_id();
            $order = wc_get_order($order_id);

            if (!$order) {
                return false;
            }

            $user_id = $order->get_user_id();
            if (!$user_id || $user_id <= 0) {
                return false;
            }

            // Check if refund was already processed
            $is_full = get_post_meta($refund_id, '_full_refund_points_processed', true);
            $is_partial = get_post_meta($refund_id, '_partial_refund_points_processed', true);

            if (!$is_full && !$is_partial) {
                return false;
            }

            // Get the deduction entry
            global $wpdb;
            $deduction = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT points_amount FROM {$wpdb->prefix}sellsuite_points_ledger 
                    WHERE order_id = %d AND notes LIKE %s ORDER BY id DESC LIMIT 1",
                    $order_id,
                    '%refund%'
                )
            );

            if ($deduction) {
                // Reverse the deduction (add back points)
                Points_Manager::add_ledger_entry(
                    $user_id,
                    $order_id,
                    0,
                    'refund_reversal',
                    abs($deduction->points_amount),
                    'earned',
                    __('Points restored - refund was canceled', 'sellsuite'),
                    $refund_id
                );

                delete_post_meta($refund_id, '_full_refund_points_processed');
                delete_post_meta($refund_id, '_partial_refund_points_processed');

                do_action('sellsuite_refund_reversed', $order_id, $user_id, abs($deduction->points_amount));
                return true;
            }

            return false;

        } catch (\Exception $e) {
            error_log('SellSuite Refund Reversal Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate refund for points processing.
     * 
     * @param int $refund_id Refund ID
     * @return array Status with message
     */
    public static function validate_refund($refund_id) {
        $refund = wc_get_order($refund_id);
        
        if (!$refund) {
            return array(
                'valid' => false,
                'message' => __('Refund not found', 'sellsuite'),
            );
        }

        $order_id = $refund->get_parent_id();
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'valid' => false,
                'message' => __('Parent order not found', 'sellsuite'),
            );
        }

        if ($refund->get_total() >= 0) {
            return array(
                'valid' => false,
                'message' => __('Refund amount must be negative', 'sellsuite'),
            );
        }

        return array(
            'valid' => true,
            'message' => __('Refund is valid for points processing', 'sellsuite'),
        );
    }
}
