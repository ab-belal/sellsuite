<?php
namespace SellSuite;

/**
 * Dashboard and analytics functionality.
 * 
 * Provides comprehensive analytics and dashboard data for points system.
 */
class Dashboard {

    /**
     * Get dashboard overview data.
     * 
     * @return array Dashboard statistics
     */
    public static function get_overview() {
        global $wpdb;

        $data = array(
            'total_users_with_points' => self::get_total_users_with_points(),
            'total_points_awarded' => self::get_total_points_awarded(),
            'total_points_redeemed' => self::get_total_points_redeemed(),
            'total_points_expired' => self::get_total_points_expired(),
            'active_pending_points' => self::get_pending_points_total(),
            'average_points_per_user' => self::get_average_points_per_user(),
            'redemption_rate' => self::get_redemption_rate(),
        );

        return apply_filters('sellsuite_dashboard_overview', $data);
    }

    /**
     * Get user points dashboard data.
     * 
     * @param int $user_id User ID
     * @return array User-specific statistics
     */
    public static function get_user_dashboard($user_id) {
        return array(
            'total_earned' => Points::get_earned_points($user_id),
            'available_balance' => Points::get_available_balance($user_id),
            'pending_points' => Points::get_pending_points($user_id),
            'total_redeemed' => Redeem_Handler::get_total_redeemed($user_id),
            'recent_transactions' => Points::get_history($user_id, 10, array()),
            'recent_redemptions' => Redeem_Handler::get_user_redemptions($user_id, 5),
        );
    }

    /**
     * Get total users with points.
     * 
     * @return int User count
     */
    private static function get_total_users_with_points() {
        global $wpdb;

        return intval($wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}sellsuite_points_ledger"
        ));
    }

    /**
     * Get total points awarded.
     * 
     * @return int Total points
     */
    private static function get_total_points_awarded() {
        global $wpdb;

        return intval($wpdb->get_var(
            "SELECT SUM(points_amount) FROM {$wpdb->prefix}sellsuite_points_ledger 
            WHERE status IN ('earned', 'pending') AND points_amount > 0"
        ));
    }

    /**
     * Get total points redeemed.
     * 
     * @return int Total points
     */
    private static function get_total_points_redeemed() {
        global $wpdb;

        return intval($wpdb->get_var(
            "SELECT ABS(SUM(points_amount)) FROM {$wpdb->prefix}sellsuite_points_ledger 
            WHERE action_type = 'redemption'"
        ));
    }

    /**
     * Get expired points count.
     * 
     * @return int Total expired points
     */
    private static function get_total_points_expired() {
        global $wpdb;

        return intval($wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(points_amount) FROM {$wpdb->prefix}sellsuite_points_ledger 
                WHERE status = 'expired' OR (expires_at IS NOT NULL AND expires_at < %s)",
                current_time('mysql')
            )
        ));
    }

    /**
     * Get total pending points awaiting order completion.
     * 
     * @return int Total pending points
     */
    private static function get_pending_points_total() {
        global $wpdb;

        return intval($wpdb->get_var(
            "SELECT SUM(points_amount) FROM {$wpdb->prefix}sellsuite_points_ledger 
            WHERE status = 'pending'"
        ));
    }

    /**
     * Get average points per user.
     * 
     * @return float Average
     */
    private static function get_average_points_per_user() {
        global $wpdb;

        $total_users = self::get_total_users_with_points();
        if ($total_users === 0) {
            return 0;
        }

        $total_points = self::get_total_points_awarded();
        return round($total_points / $total_users, 2);
    }

    /**
     * Get redemption rate percentage.
     * 
     * @return float Percentage
     */
    private static function get_redemption_rate() {
        $awarded = self::get_total_points_awarded();
        if ($awarded === 0) {
            return 0;
        }

        $redeemed = self::get_total_points_redeemed();
        return round(($redeemed / $awarded) * 100, 2);
    }

    /**
     * Get top earners.
     * 
     * @param int $limit Number of users
     * @return array User data with point totals
     */
    public static function get_top_earners($limit = 10) {
        global $wpdb;

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    user_id,
                    COUNT(*) as transaction_count,
                    SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END) as earned_points
                FROM {$wpdb->prefix}sellsuite_points_ledger 
                WHERE status IN ('earned', 'pending')
                GROUP BY user_id 
                ORDER BY earned_points DESC 
                LIMIT %d",
                $limit
            )
        );

        $users = array();
        foreach ($results as $result) {
            $user = get_userdata($result->user_id);
            if ($user) {
                $users[] = array(
                    'user_id' => $result->user_id,
                    'user_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'points_earned' => intval($result->earned_points),
                    'transactions' => intval($result->transaction_count),
                );
            }
        }

        return $users;
    }

    /**
     * Get points timeline (daily aggregation).
     * 
     * @param int $days Number of days to retrieve
     * @return array Timeline data
     */
    public static function get_points_timeline($days = 30) {
        global $wpdb;

        $date_from = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(created_at) as date,
                    SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END) as awarded,
                    SUM(CASE WHEN points_amount < 0 THEN ABS(points_amount) ELSE 0 END) as deducted,
                    COUNT(*) as transaction_count
                FROM {$wpdb->prefix}sellsuite_points_ledger 
                WHERE DATE(created_at) >= %s 
                GROUP BY DATE(created_at) 
                ORDER BY date ASC",
                $date_from
            )
        );

        return $results ?: array();
    }

    /**
     * Get points by action type.
     * 
     * @return array Distribution by action type
     */
    public static function get_points_by_action() {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT 
                action_type,
                COUNT(*) as count,
                SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END) as awarded,
                SUM(CASE WHEN points_amount < 0 THEN ABS(points_amount) ELSE 0 END) as deducted
            FROM {$wpdb->prefix}sellsuite_points_ledger 
            GROUP BY action_type"
        );

        return $results ?: array();
    }

    /**
     * Get user segments by points balance.
     * 
     * @return array User segments
     */
    public static function get_user_segments() {
        global $wpdb;

        // Get unique users with their total earned points
        $user_data = $wpdb->get_results(
            "SELECT 
                user_id,
                SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END) as total_earned
            FROM {$wpdb->prefix}sellsuite_points_ledger 
            GROUP BY user_id"
        );

        $segments = array(
            'no_points' => 0,
            'low' => 0,      // 1-50 points
            'medium' => 0,   // 51-200 points
            'high' => 0,     // 201-500 points
            'premium' => 0,  // 500+ points
        );

        foreach ($user_data as $user) {
            $points = intval($user->total_earned);
            
            if ($points === 0) {
                $segments['no_points']++;
            } elseif ($points <= 50) {
                $segments['low']++;
            } elseif ($points <= 200) {
                $segments['medium']++;
            } elseif ($points <= 500) {
                $segments['high']++;
            } else {
                $segments['premium']++;
            }
        }

        return $segments;
    }

    /**
     * Generate report data.
     * 
     * @param string $report_type Type of report (summary, detailed, export)
     * @param array  $filters Optional filters
     * @return array Report data
     */
    public static function generate_report($report_type = 'summary', $filters = array()) {
        $filters = wp_parse_args($filters, array(
            'date_from' => null,
            'date_to' => null,
            'user_id' => null,
            'action_type' => null,
        ));

        global $wpdb;
        $where = array('1=1');
        $prepare_args = array();

        // Date range filter
        if ($filters['date_from']) {
            $where[] = 'DATE(created_at) >= %s';
            $prepare_args[] = sanitize_text_field($filters['date_from']);
        }

        if ($filters['date_to']) {
            $where[] = 'DATE(created_at) <= %s';
            $prepare_args[] = sanitize_text_field($filters['date_to']);
        }

        // User filter
        if ($filters['user_id']) {
            $where[] = 'user_id = %d';
            $prepare_args[] = intval($filters['user_id']);
        }

        // Action type filter
        if ($filters['action_type']) {
            $where[] = 'action_type = %s';
            $prepare_args[] = sanitize_text_field($filters['action_type']);
        }

        $where_clause = implode(' AND ', $where);

        if ($report_type === 'detailed') {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sellsuite_points_ledger 
                    WHERE {$where_clause} 
                    ORDER BY created_at DESC",
                    ...$prepare_args
                )
            );
        } else {
            // Summary report
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT 
                        COUNT(*) as transaction_count,
                        SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END) as total_awarded,
                        SUM(CASE WHEN points_amount < 0 THEN ABS(points_amount) ELSE 0 END) as total_deducted,
                        action_type
                    FROM {$wpdb->prefix}sellsuite_points_ledger 
                    WHERE {$where_clause}
                    GROUP BY action_type",
                    ...$prepare_args
                )
            );
        }
    }

    /**
     * Get expiry forecast.
     * 
     * Get points that will expire in coming days.
     * 
     * @param int $days Days ahead to forecast
     * @return array Expiry forecast data
     */
    public static function get_expiry_forecast($days = 30) {
        global $wpdb;

        $date_to = date('Y-m-d', strtotime("+{$days} days"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    user_id,
                    DATE(expires_at) as expiry_date,
                    SUM(points_amount) as points,
                    COUNT(*) as entry_count
                FROM {$wpdb->prefix}sellsuite_points_ledger 
                WHERE expires_at IS NOT NULL 
                AND expires_at <= %s 
                AND status IN ('earned', 'pending')
                GROUP BY user_id, DATE(expires_at) 
                ORDER BY expires_at ASC",
                $date_to
            )
        );
    }
}
