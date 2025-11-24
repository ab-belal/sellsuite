<?php
namespace SellSuite;

/**
 * Handle points system functionality.
 */
class Points {

    /**
     * Get total points for a user.
     */
    public static function get_user_total_points($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sellsuite_points';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        return $total ? intval($total) : 0;
    }

    /**
     * Add points to a user.
     */
    public static function add_points($user_id, $points, $action_type = 'manual', $description = '', $order_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sellsuite_points';

        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'points' => $points,
                'action_type' => $action_type,
                'description' => $description,
                'order_id' => $order_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Deduct points from a user.
     */
    public static function deduct_points($user_id, $points, $action_type = 'redemption', $description = '') {
        return self::add_points($user_id, -abs($points), $action_type, $description);
    }

    /**
     * Get points history for a user.
     */
    public static function get_user_points_history($user_id, $limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sellsuite_points';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id,
            $limit
        ));
    }

    /**
     * Award points for order completion.
     */
    public static function award_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return false;
        }

        // Check if points already awarded
        global $wpdb;
        $table_name = $wpdb->prefix . 'sellsuite_points';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE order_id = %d AND action_type = 'order_complete'",
            $order_id
        ));

        if ($existing) {
            return false;
        }

        // Calculate points
        $settings = get_option('sellsuite_settings', array());
        $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;
        $order_total = $order->get_total();
        $points = floor($order_total * $points_per_dollar);

        // Award points
        return self::add_points(
            $user_id,
            $points,
            'order_complete',
            sprintf(__('Points earned from order #%s', 'sellsuite'), $order_id),
            $order_id
        );
    }
}
