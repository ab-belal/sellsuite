<?php
namespace SellSuite;

/**
 * Handle admin point adjustments and operations.
 * 
 * Manages manual point assignments, deductions, and audit logging
 * with comprehensive validation and security.
 */
class Admin_Handler {

    /**
     * Manually assign points to user.
     * 
     * @param int    $user_id User ID
     * @param int    $points Points to assign
     * @param string $reason Reason for assignment
     * @param int    $admin_id Admin user ID (current user if not provided)
     * @return array Status result
     */
    public static function assign_points($user_id, $points, $reason = '', $admin_id = 0) {
        try {
            // Validate inputs
            $validation = self::validate_admin_action($user_id, $points);
            if (!$validation['valid']) {
                return $validation;
            }

            if (!$admin_id) {
                $admin_id = get_current_user_id();
            }

            // Verify admin capability
            if (!current_user_can('manage_woocommerce')) {
                return array(
                    'success' => false,
                    'message' => __('Insufficient permissions', 'sellsuite'),
                );
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return array(
                    'success' => false,
                    'message' => __('User not found', 'sellsuite'),
                );
            }

            // Create ledger entry
            $ledger_id = Points_Manager::add_ledger_entry(
                $user_id,
                0,
                0,
                'admin_assignment',
                intval($points),
                'earned',
                $reason ?: __('Manual admin assignment', 'sellsuite'),
                $admin_id
            );

            if (!$ledger_id) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create ledger entry', 'sellsuite'),
                );
            }

            // Log action
            self::log_action($admin_id, $user_id, 'assign_points', intval($points), $reason);

            // Send notification
            do_action('sellsuite_admin_points_assigned', $user_id, intval($points), $admin_id);

            return array(
                'success' => true,
                'message' => sprintf(
                    __('%d points assigned to %s', 'sellsuite'),
                    intval($points),
                    $user->display_name
                ),
                'ledger_id' => $ledger_id,
                'user_id' => $user_id,
                'points' => intval($points),
            );

        } catch (\Exception $e) {
            error_log('SellSuite Admin Points Assignment Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred', 'sellsuite'),
            );
        }
    }

    /**
     * Manually deduct points from user.
     * 
     * @param int    $user_id User ID
     * @param int    $points Points to deduct
     * @param string $reason Reason for deduction
     * @param int    $admin_id Admin user ID
     * @return array Status result
     */
    public static function deduct_points($user_id, $points, $reason = '', $admin_id = 0) {
        try {
            // Validate inputs
            $validation = self::validate_admin_action($user_id, $points);
            if (!$validation['valid']) {
                return $validation;
            }

            if (!$admin_id) {
                $admin_id = get_current_user_id();
            }

            // Verify admin capability
            if (!current_user_can('manage_woocommerce')) {
                return array(
                    'success' => false,
                    'message' => __('Insufficient permissions', 'sellsuite'),
                );
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return array(
                    'success' => false,
                    'message' => __('User not found', 'sellsuite'),
                );
            }

            // Check available balance
            $available = Points_Manager::get_available_balance($user_id);
            if ($available < $points) {
                return array(
                    'success' => false,
                    'message' => sprintf(
                        __('Insufficient points. Available: %d, Requested: %d', 'sellsuite'),
                        $available,
                        intval($points)
                    ),
                );
            }

            // Create ledger entry
            $ledger_id = Points_Manager::add_ledger_entry(
                $user_id,
                0,
                0,
                'admin_deduction',
                -intval($points),
                'earned',
                $reason ?: __('Manual admin deduction', 'sellsuite'),
                $admin_id
            );

            if (!$ledger_id) {
                return array(
                    'success' => false,
                    'message' => __('Failed to create ledger entry', 'sellsuite'),
                );
            }

            // Log action
            self::log_action($admin_id, $user_id, 'deduct_points', intval($points), $reason);

            // Send notification
            do_action('sellsuite_admin_points_deducted', $user_id, intval($points), $admin_id);

            return array(
                'success' => true,
                'message' => sprintf(
                    __('%d points deducted from %s', 'sellsuite'),
                    intval($points),
                    $user->display_name
                ),
                'ledger_id' => $ledger_id,
                'user_id' => $user_id,
                'points_deducted' => intval($points),
            );

        } catch (\Exception $e) {
            error_log('SellSuite Admin Points Deduction Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred', 'sellsuite'),
            );
        }
    }

    /**
     * Reset user points to zero.
     * 
     * @param int    $user_id User ID
     * @param string $reason Reason for reset
     * @param int    $admin_id Admin user ID
     * @return array Status result
     */
    public static function reset_user_points($user_id, $reason = '', $admin_id = 0) {
        try {
            $user = get_userdata($user_id);
            if (!$user) {
                return array(
                    'success' => false,
                    'message' => __('User not found', 'sellsuite'),
                );
            }

            if (!$admin_id) {
                $admin_id = get_current_user_id();
            }

            // Verify capability
            if (!current_user_can('manage_woocommerce')) {
                return array(
                    'success' => false,
                    'message' => __('Insufficient permissions', 'sellsuite'),
                );
            }

            // Get current balance
            $current_balance = Points_Manager::get_available_balance($user_id);

            if ($current_balance > 0) {
                // Create deduction ledger entry for total balance
                $ledger_id = Points_Manager::add_ledger_entry(
                    $user_id,
                    0,
                    0,
                    'admin_reset',
                    -$current_balance,
                    'earned',
                    $reason ?: __('Admin reset all points', 'sellsuite'),
                    $admin_id
                );

                if (!$ledger_id) {
                    return array(
                        'success' => false,
                        'message' => __('Failed to reset points', 'sellsuite'),
                    );
                }
            }

            // Log action
            self::log_action($admin_id, $user_id, 'reset_points', $current_balance, $reason);

            return array(
                'success' => true,
                'message' => sprintf(
                    __('Points reset for %s (removed %d points)', 'sellsuite'),
                    $user->display_name,
                    $current_balance
                ),
                'user_id' => $user_id,
                'points_removed' => $current_balance,
            );

        } catch (\Exception $e) {
            error_log('SellSuite Admin Points Reset Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred', 'sellsuite'),
            );
        }
    }

    /**
     * Get audit log.
     * 
     * @param int   $limit Number of records
     * @param array $filters Optional filters
     * @return array Audit logs
     */
    public static function get_audit_log($limit = 50, $filters = array()) {
        global $wpdb;

        $filters = wp_parse_args($filters, array(
            'admin_id' => 0,
            'user_id' => 0,
            'action_type' => '',
            'date_from' => null,
            'date_to' => null,
        ));

        $where = array('1=1');
        $prepare_args = array();

        if ($filters['admin_id'] > 0) {
            $where[] = 'admin_id = %d';
            $prepare_args[] = intval($filters['admin_id']);
        }

        if ($filters['user_id'] > 0) {
            $where[] = 'user_id = %d';
            $prepare_args[] = intval($filters['user_id']);
        }

        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = %s';
            $prepare_args[] = sanitize_text_field($filters['action_type']);
        }

        if ($filters['date_from']) {
            $where[] = 'created_at >= %s';
            $prepare_args[] = sanitize_text_field($filters['date_from']);
        }

        if ($filters['date_to']) {
            $where[] = 'created_at <= %s';
            $prepare_args[] = sanitize_text_field($filters['date_to']);
        }

        $where_clause = implode(' AND ', $where);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sellsuite_audit_log 
                WHERE {$where_clause} 
                ORDER BY created_at DESC 
                LIMIT %d",
                array_merge($prepare_args, array($limit))
            )
        );
    }

    /**
     * Log admin action.
     * 
     * @param int    $admin_id Admin user ID
     * @param int    $user_id Target user ID
     * @param string $action_type Action type
     * @param int    $points_involved Points involved
     * @param string $notes Additional notes
     * @return bool Success
     */
    private static function log_action($admin_id, $user_id, $action_type, $points_involved = 0, $notes = '') {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'sellsuite_audit_log',
            array(
                'admin_id' => intval($admin_id),
                'user_id' => intval($user_id),
                'action_type' => sanitize_text_field($action_type),
                'points_involved' => intval($points_involved),
                'notes' => sanitize_textarea_field($notes),
                'ip_address' => self::get_client_ip(),
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Validate admin action inputs.
     * 
     * @param int $user_id User ID
     * @param int $points Points amount
     * @return array Validation result
     */
    private static function validate_admin_action($user_id, $points) {
        if (!current_user_can('manage_woocommerce')) {
            return array(
                'valid' => false,
                'message' => __('Insufficient permissions', 'sellsuite'),
            );
        }

        if ($user_id <= 0) {
            return array(
                'valid' => false,
                'message' => __('Invalid user ID', 'sellsuite'),
            );
        }

        if ($points <= 0) {
            return array(
                'valid' => false,
                'message' => __('Points must be greater than zero', 'sellsuite'),
            );
        }

        if (!Points_Manager::is_enabled()) {
            return array(
                'valid' => false,
                'message' => __('Points system is disabled', 'sellsuite'),
            );
        }

        return array('valid' => true);
    }

    /**
     * Get client IP address.
     * 
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return $ip ?: '0.0.0.0';
    }

    /**
     * Get admin action summary.
     * 
     * @param int $days Days to analyze
     * @return array Summary data
     */
    public static function get_action_summary($days = 30) {
        global $wpdb;

        $date_from = date('Y-m-d', strtotime("-{$days} days"));

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    action_type,
                    COUNT(*) as count,
                    SUM(points_involved) as total_points,
                    COUNT(DISTINCT admin_id) as unique_admins,
                    COUNT(DISTINCT user_id) as affected_users
                FROM {$wpdb->prefix}sellsuite_audit_log 
                WHERE DATE(created_at) >= %s
                GROUP BY action_type",
                $date_from
            )
        );

        return $result ?: array();
    }

    /**
     * Bulk assign points via CSV.
     * 
     * @param array $data CSV data (user_email, points, reason)
     * @param int   $admin_id Admin user ID
     * @return array Result summary
     */
    public static function bulk_assign_points($data, $admin_id = 0) {
        if (!$admin_id) {
            $admin_id = get_current_user_id();
        }

        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array(),
        );

        foreach ($data as $index => $row) {
            $email = isset($row['user_email']) ? sanitize_email($row['user_email']) : '';
            $points = isset($row['points']) ? intval($row['points']) : 0;
            $reason = isset($row['reason']) ? sanitize_text_field($row['reason']) : '';

            if (!is_email($email)) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Row %d: Invalid email address', 'sellsuite'), $index + 1);
                continue;
            }

            $user = get_user_by('email', $email);
            if (!$user) {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Row %d: User not found (%s)', 'sellsuite'), $index + 1, $email);
                continue;
            }

            $result = self::assign_points($user->ID, $points, $reason, $admin_id);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(__('Row %d: %s', 'sellsuite'), $index + 1, $result['message']);
            }
        }

        return $results;
    }
}
