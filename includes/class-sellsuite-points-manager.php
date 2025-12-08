<?php
namespace SellSuite;

/**
 * Handle points system functionality.
 * 
 * Manages all point-related operations: earning, redemption, tracking, and balance calculation.
 */
class Points {

    /**
     * Get available balance (earned - redeemed - refunded - expired).
     * 
     * @param int $user_id User ID
     * @return int Available balance
     */
    public static function get_available_balance($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        // Get sum of earned and pending points (excluding refunded/cancelled)
        $available = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_amount), 0) FROM $table 
             WHERE user_id = %d AND status IN ('earned') AND (expires_at IS NULL OR expires_at > NOW())",
            $user_id
        ));

        return intval($available);
    }

    /**
     * Get total earned points (including expired and redeemed).
     * 
     * @param int $user_id User ID
     * @return int Total earned points
     */
    public static function get_earned_points($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_amount), 0) FROM $table 
             WHERE user_id = %d AND status IN ('earned', 'redeemed')",
            $user_id
        ));

        return intval($total);
    }

    /**
     * Get pending points (waiting for order to be completed).
     * 
     * @param int $user_id User ID
     * @return int Pending points
     */
    public static function get_pending_points($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        $pending = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_amount), 0) FROM $table 
             WHERE user_id = %d AND status = 'pending'",
            $user_id
        ));

        return intval($pending);
    }

    /**
     * Get all point settings.
     * 
     * @return array Settings array
     */
    public static function get_settings() {
        $defaults = array(
            'points_enabled' => true,
            'conversion_rate' => 1,
            'max_redeemable_percentage' => 20,
            'enable_expiry' => false,
            'expiry_days' => 365,
            'point_calculation_method' => 'fixed',
            'points_per_dollar' => 1,
            'points_percentage' => 0,
        );

        $settings = get_option('sellsuite_settings', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Check if points system is enabled.
     * 
     * @return bool
     */
    public static function is_enabled() {
        $settings = self::get_settings();
        return isset($settings['points_enabled']) ? (bool) $settings['points_enabled'] : true;
    }

    /**
     * Check if expiry system is enabled.
     * 
     * @return bool
     */
    public static function is_expiry_enabled() {
        $settings = self::get_settings();
        return isset($settings['enable_expiry']) ? (bool) $settings['enable_expiry'] : false;
    }

    /**
     * Add points to ledger (new method using ledger table).
     * 
     * @param int    $user_id User ID
     * @param int    $points Points amount
     * @param string $action_type Type of action (order_complete, redemption, refund, manual, etc.)
     * @param string $description Description of action
     * @param string $status Point status (pending, earned, refunded, cancelled)
     * @param int    $order_id Optional order ID
     * @param int    $product_id Optional product ID
     * @param string $notes Optional notes
     * @return int|false Ledger entry ID or false
     */
    public static function add_ledger_entry($user_id, $points, $action_type = 'manual', $description = '', $status = 'earned', $order_id = null, $product_id = null, $notes = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        // Calculate expiry date if expiry is enabled
        $expires_at = null;
        if (self::is_expiry_enabled()) {
            $settings = self::get_settings();
            $expiry_days = isset($settings['expiry_days']) ? intval($settings['expiry_days']) : 365;
            $expires_at = gmdate('Y-m-d H:i:s', time() + ($expiry_days * 24 * 60 * 60));
        }

        $result = $wpdb->insert(
            $table,
            array(
                'user_id' => intval($user_id),
                'order_id' => $order_id ? intval($order_id) : null,
                'product_id' => $product_id ? intval($product_id) : null,
                'action_type' => sanitize_text_field($action_type),
                'points_amount' => intval($points),
                'status' => sanitize_text_field($status),
                'description' => sanitize_textarea_field($description),
                'notes' => sanitize_textarea_field($notes),
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at
            ),
            array('%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get point history for a user with optional filters.
     * 
     * @param int   $user_id User ID
     * @param int   $limit Limit results
     * @param array $filters Optional filters (action_type, status, date_from, date_to)
     * @return array History entries
     */
    public static function get_history($user_id, $limit = 50, $filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        );

        // Add action type filter
        if (!empty($filters['action_type'])) {
            $query .= $wpdb->prepare(
                " AND action_type = %s",
                $filters['action_type']
            );
        }

        // Add status filter
        if (!empty($filters['status'])) {
            $query .= $wpdb->prepare(
                " AND status = %s",
                $filters['status']
            );
        }

        // Add date range filter
        if (!empty($filters['date_from'])) {
            $query .= $wpdb->prepare(
                " AND created_at >= %s",
                $filters['date_from']
            );
        }

        if (!empty($filters['date_to'])) {
            $query .= $wpdb->prepare(
                " AND created_at <= %s",
                $filters['date_to']
            );
        }

        $query .= " ORDER BY created_at DESC LIMIT %d";
        $query = $wpdb->prepare($query, $limit);

        return $wpdb->get_results($query);
    }

    /**
     * Get a specific ledger entry.
     * 
     * @param int $ledger_id Ledger entry ID
     * @return object|null Entry or null
     */
    public static function get_ledger_entry($ledger_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $ledger_id
        ));
    }

    /**
     * Update ledger entry status.
     * 
     * @param int    $ledger_id Ledger entry ID
     * @param string $new_status New status
     * @param string $notes Optional notes
     * @return bool Success
     */
    public static function update_ledger_status($ledger_id, $new_status, $notes = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        return $wpdb->update(
            $table,
            array(
                'status' => sanitize_text_field($new_status),
                'notes' => sanitize_textarea_field($notes)
            ),
            array('id' => intval($ledger_id)),
            array('%s', '%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Get total points for a user (legacy compatibility).
     */
    public static function get_user_total_points($user_id) {
        return self::get_available_balance($user_id);
    }

    /**
     * Add points to a user (legacy compatibility).
     */
    public static function add_points($user_id, $points, $action_type = 'manual', $description = '', $order_id = null) {
        return self::add_ledger_entry($user_id, $points, $action_type, $description, 'earned', $order_id);
    }

    /**
     * Deduct points from a user.
     */
    public static function deduct_points($user_id, $points, $action_type = 'redemption', $description = '') {
        return self::add_ledger_entry($user_id, -abs($points), $action_type, $description, 'earned');
    }

    /**
     * Get points history for a user (legacy compatibility).
     */
    public static function get_user_points_history($user_id, $limit = 50) {
        return self::get_history($user_id, $limit);
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
        $table = $wpdb->prefix . 'sellsuite_points_ledger';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE order_id = %d AND action_type = 'order_complete'",
            $order_id
        ));

        if ($existing) {
            return false;
        }

        // Calculate points
        $settings = self::get_settings();
        $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;
        $order_total = $order->get_total();
        $points = floor($order_total * $points_per_dollar);

        // Award points
            return self::add_ledger_entry(
            $user_id,
            $points,
            'order_complete',
            sprintf(__('Points earned from order #%s', 'sellsuite'), $order_id),
            'earned',
            $order_id
        );
    }

    /**
     * Calculate display points for a product with priority logic.
     * 
     * Priority:
     * 1. Custom product reward points (if set)
     * 2. Fallback: Automatic calculation using price × Points Per Dollar setting
     * 
     * @param int   $product_id Product ID
     * @param float $price Optional price (uses product price if not provided)
     * @return int Display points
     */
    public static function get_product_display_points($product_id, $price = null) {
        // First priority: Check for custom product points
        $custom_points = \SellSuite\Product_Meta::get_product_points($product_id, $price);
        
        if ($custom_points > 0) {
            return $custom_points;
        }

        // Fallback: Calculate based on global "Points Per Dollar" setting
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }

        // Get price
        if ($price === null) {
            $price = floatval($product->get_price());
        } else {
            $price = floatval($price);
        }

        if ($price <= 0) {
            return 0;
        }

        // Get Points Per Dollar from settings
        $settings = self::get_settings();
        $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;

        // Calculate: price × points_per_dollar
        return intval(floor($price * $points_per_dollar));
    }

    /**
     * Calculate display points for a product variation with priority logic.
     * 
     * Priority:
     * 1. Custom variation reward points (if set)
     * 2. Custom parent product reward points (if set)
     * 3. Fallback: Automatic calculation using price × Points Per Dollar setting
     * 
     * @param int   $variation_id Variation ID
     * @param float $price Optional price (uses variation price if not provided)
     * @return int Display points
     */
    public static function get_variation_display_points($variation_id, $price = null) {
        // First priority: Check for custom variation points
        $custom_points = \SellSuite\Product_Meta::get_variation_points($variation_id, $price);
        
        if ($custom_points > 0) {
            return $custom_points;
        }

        // Fallback: Calculate based on global "Points Per Dollar" setting
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return 0;
        }

        // Get price
        if ($price === null) {
            $price = floatval($variation->get_price());
        } else {
            $price = floatval($price);
        }

        if ($price <= 0) {
            return 0;
        }

        // Get Points Per Dollar from settings
        $settings = self::get_settings();
        $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;

        // Calculate: price × points_per_dollar
        return intval(floor($price * $points_per_dollar));
    }
}