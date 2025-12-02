<?php
/**
 * SellSuite Point Expiry Handler
 *
 * Manages point expiration, grace periods, and expiry notifications
 *
 * @package    SellSuite
 * @subpackage SellSuite/includes
 * @author     AB Belal <info@ab-belal.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Point Expiry Handler Class
 *
 * Handles automatic expiration of points based on configured rules,
 * manages grace periods, and sends notifications for expiring points.
 */
class SellSuite_Expiry_Handler {

    /**
     * Process expired points for a user
     *
     * @param int $user_id User ID
     * @return array Result array with status and expired_points
     */
    public static function process_user_expirations($user_id) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            if ($user_id <= 0) {
                return new WP_Error(
                    'invalid_user',
                    'Invalid user ID',
                    array('status' => 400)
                );
            }

            $rules = self::get_expiry_rules();
            if (is_wp_error($rules)) {
                return $rules;
            }

            $expired_data = array(
                'total_expired' => 0,
                'transactions' => 0,
                'notifications_sent' => 0
            );

            foreach ($rules as $rule) {
                // Get points that meet expiry criteria
                $points_to_expire = self::get_expiring_points($user_id, $rule);

                if (empty($points_to_expire)) {
                    continue;
                }

                foreach ($points_to_expire as $ledger) {
                    // Mark points as expired
                    $expired = self::mark_as_expired($ledger->id, $user_id, $rule);

                    if ($expired) {
                        $expired_data['total_expired'] += $ledger->points;
                        $expired_data['transactions']++;

                        // Send notification
                        $notification_sent = self::send_expiry_notification(
                            $user_id,
                            $ledger->points,
                            $ledger->action_type
                        );

                        if ($notification_sent) {
                            $expired_data['notifications_sent']++;
                        }

                        // Execute custom action for extensibility
                        do_action(
                            'sellsuite_points_expired',
                            $user_id,
                            $ledger->points,
                            $ledger->id,
                            $rule
                        );
                    }
                }
            }

            return $expired_data;

        } catch (Exception $e) {
            error_log('SellSuite Expiry Handler Error: ' . $e->getMessage());
            return new WP_Error(
                'expiry_process_error',
                'Error processing expirations: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get points that meet expiry criteria for a user
     *
     * @param int   $user_id User ID
     * @param array $rule    Expiry rule configuration
     * @return array Array of ledger entries that should expire
     */
    public static function get_expiring_points($user_id, $rule) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            $table = $wpdb->prefix . 'sellsuite_points_ledger';
            $expiry_table = $wpdb->prefix . 'sellsuite_point_expirations';

            // Calculate expiry date based on rule
            $expiry_days = intval($rule['expiry_days']);
            $grace_days = intval($rule['grace_days']);
            $expiry_date = date('Y-m-d H:i:s', strtotime("-{$expiry_days} days"));
            $grace_date = date('Y-m-d H:i:s', strtotime("-{$grace_days} days"));

            // Get points that have expired and haven't been marked yet
            $query = $wpdb->prepare(
                "SELECT l.id, l.points, l.action_type, l.created_at
                FROM {$table} l
                WHERE l.user_id = %d
                AND l.status = 'earned'
                AND l.created_at < %s
                AND l.id NOT IN (
                    SELECT ledger_id FROM {$expiry_table}
                    WHERE status = 'expired' OR status = 'notified'
                )
                AND l.action_type IN (%s)
                ORDER BY l.created_at ASC",
                $user_id,
                $expiry_date,
                implode(',', array_fill(0, count($rule['action_types']), '%s')),
                ...$rule['action_types']
            );

            // Use simpler query without IN clause for action_types
            $query = $wpdb->prepare(
                "SELECT l.id, l.points, l.action_type, l.created_at
                FROM {$table} l
                WHERE l.user_id = %d
                AND l.status = 'earned'
                AND l.created_at < %s
                AND l.id NOT IN (
                    SELECT ledger_id FROM {$expiry_table}
                    WHERE status IN ('expired', 'notified')
                )
                ORDER BY l.created_at ASC",
                $user_id,
                $expiry_date
            );

            $points = $wpdb->get_results($query);

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return array();
            }

            return $points ?: array();

        } catch (Exception $e) {
            error_log('SellSuite Get Expiring Points Error: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Mark points as expired
     *
     * @param int   $ledger_id Ledger ID
     * @param int   $user_id   User ID
     * @param array $rule      Expiry rule
     * @return bool True on success
     */
    public static function mark_as_expired($ledger_id, $user_id, $rule) {
        try {
            global $wpdb;

            $ledger_id = intval($ledger_id);
            $user_id = intval($user_id);

            $expiry_table = $wpdb->prefix . 'sellsuite_point_expirations';

            // Record expiration
            $result = $wpdb->insert(
                $expiry_table,
                array(
                    'user_id' => $user_id,
                    'ledger_id' => $ledger_id,
                    'status' => 'expired',
                    'expiry_reason' => $rule['name'],
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );

            if (!$result) {
                error_log('Error marking points as expired: ' . $wpdb->last_error);
                return false;
            }

            // Update ledger status to reflect expiration
            $ledger_table = $wpdb->prefix . 'sellsuite_points_ledger';
            $wpdb->update(
                $ledger_table,
                array('status' => 'expired'),
                array('id' => $ledger_id),
                array('%s'),
                array('%d')
            );

            return true;

        } catch (Exception $e) {
            error_log('SellSuite Mark as Expired Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send expiry notification to user
     *
     * @param int    $user_id     User ID
     * @param int    $points      Number of points expired
     * @param string $action_type Type of action that earned the points
     * @return bool True if notification sent
     */
    public static function send_expiry_notification($user_id, $points, $action_type) {
        try {
            $user = get_userdata($user_id);

            if (!$user || !$user->user_email) {
                return false;
            }

            $subject = sprintf(
                __('[%s] Your Reward Points Have Expired', 'sellsuite'),
                get_bloginfo('name')
            );

            $message = sprintf(
                __('Hi %s,<br /><br />Your %d reward points from %s have expired and been removed from your account.<br /><br />Best regards,<br />%s', 'sellsuite'),
                $user->display_name,
                $points,
                $action_type,
                get_bloginfo('name')
            );

            // Allow filtering of notification
            $message = apply_filters(
                'sellsuite_expiry_notification_message',
                $message,
                $user_id,
                $points,
                $action_type
            );

            // Send email
            $result = wp_mail($user->user_email, $subject, $message);

            if ($result) {
                // Log successful notification
                do_action('sellsuite_expiry_notified', $user_id, $points);
            }

            return $result;

        } catch (Exception $e) {
            error_log('SellSuite Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get expiry rules configuration
     *
     * @return array|WP_Error Array of rules or error
     */
    public static function get_expiry_rules() {
        try {
            global $wpdb;

            $rules_table = $wpdb->prefix . 'sellsuite_expiry_rules';

            // Check if table exists
            $table_exists = $wpdb->get_var(
                "SHOW TABLES LIKE '{$rules_table}'"
            );

            if (!$table_exists) {
                // Return default rules if table doesn't exist yet
                return array(
                    array(
                        'id' => 1,
                        'name' => 'Standard Expiry',
                        'expiry_days' => 365,
                        'grace_days' => 30,
                        'action_types' => array('purchase', 'review', 'referral')
                    )
                );
            }

            $rules = $wpdb->get_results(
                "SELECT * FROM {$rules_table} WHERE status = 'active' ORDER BY priority ASC"
            );

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving rules',
                    array('status' => 500)
                );
            }

            if (empty($rules)) {
                // Return default rules
                return array(
                    array(
                        'id' => 1,
                        'name' => 'Standard Expiry',
                        'expiry_days' => 365,
                        'grace_days' => 30,
                        'action_types' => array('purchase', 'review', 'referral')
                    )
                );
            }

            // Parse action_types JSON
            foreach ($rules as $rule) {
                if (is_string($rule->action_types)) {
                    $rule->action_types = json_decode($rule->action_types, true) ?: array();
                }
            }

            return $rules;

        } catch (Exception $e) {
            error_log('SellSuite Get Rules Error: ' . $e->getMessage());
            return new WP_Error(
                'expiry_rules_error',
                'Error retrieving expiry rules',
                array('status' => 500)
            );
        }
    }

    /**
     * Update expiry rule
     *
     * @param int   $rule_id Rule ID
     * @param array $data    Updated rule data
     * @return bool|WP_Error True on success
     */
    public static function update_expiry_rule($rule_id, $data) {
        try {
            global $wpdb;

            $rule_id = intval($rule_id);
            $rules_table = $wpdb->prefix . 'sellsuite_expiry_rules';

            // Validate data
            if (isset($data['expiry_days'])) {
                $data['expiry_days'] = intval($data['expiry_days']);
                if ($data['expiry_days'] < 1) {
                    return new WP_Error(
                        'invalid_expiry_days',
                        'Expiry days must be at least 1',
                        array('status' => 400)
                    );
                }
            }

            if (isset($data['grace_days'])) {
                $data['grace_days'] = intval($data['grace_days']);
                if ($data['grace_days'] < 0) {
                    return new WP_Error(
                        'invalid_grace_days',
                        'Grace days cannot be negative',
                        array('status' => 400)
                    );
                }
            }

            if (isset($data['action_types'])) {
                if (is_array($data['action_types'])) {
                    $data['action_types'] = json_encode($data['action_types']);
                }
            }

            $result = $wpdb->update(
                $rules_table,
                $data,
                array('id' => $rule_id),
                array('%s', '%d', '%d', '%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                error_log('Error updating rule: ' . $wpdb->last_error);
                return new WP_Error(
                    'rule_update_error',
                    'Error updating expiry rule',
                    array('status' => 500)
                );
            }

            do_action('sellsuite_expiry_rule_updated', $rule_id, $data);

            return true;

        } catch (Exception $e) {
            error_log('SellSuite Update Rule Error: ' . $e->getMessage());
            return new WP_Error(
                'update_error',
                'Error updating rule: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get expiry forecast for user
     *
     * Predicts points that will expire in the coming days
     *
     * @param int $user_id User ID
     * @param int $days    Number of days to forecast (default 30)
     * @return array|WP_Error Array with forecast data
     */
    public static function get_expiry_forecast($user_id, $days = 30) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            $days = intval($days);

            $table = $wpdb->prefix . 'sellsuite_points_ledger';
            $rules = self::get_expiry_rules();

            if (is_wp_error($rules)) {
                return $rules;
            }

            $forecast = array(
                'upcoming_expirations' => array(),
                'total_at_risk' => 0,
                'days_range' => $days
            );

            foreach ($rules as $rule) {
                $expiry_days = intval($rule['expiry_days']);
                $current_date = current_time('mysql');
                $expiry_cutoff = date('Y-m-d H:i:s', strtotime("-{$expiry_days} days", strtotime($current_date)));
                $future_cutoff = date('Y-m-d H:i:s', strtotime("-" . ($expiry_days - $days) . " days", strtotime($current_date)));

                $query = $wpdb->prepare(
                    "SELECT 
                        DATE(created_at) as expiry_date,
                        SUM(points) as total_points,
                        COUNT(*) as transaction_count
                    FROM {$table}
                    WHERE user_id = %d
                    AND status = 'earned'
                    AND created_at >= %s
                    AND created_at < %s
                    GROUP BY DATE(created_at)
                    ORDER BY created_at ASC",
                    $user_id,
                    $expiry_cutoff,
                    $future_cutoff
                );

                $upcoming = $wpdb->get_results($query);

                if ($wpdb->last_error) {
                    error_log('Database Error: ' . $wpdb->last_error);
                    continue;
                }

                if (!empty($upcoming)) {
                    foreach ($upcoming as $item) {
                        $forecast['upcoming_expirations'][] = array(
                            'expiry_date' => $item->expiry_date,
                            'points' => intval($item->total_points),
                            'transactions' => intval($item->transaction_count),
                            'rule' => $rule['name']
                        );

                        $forecast['total_at_risk'] += intval($item->total_points);
                    }
                }
            }

            return $forecast;

        } catch (Exception $e) {
            error_log('SellSuite Expiry Forecast Error: ' . $e->getMessage());
            return new WP_Error(
                'forecast_error',
                'Error generating forecast: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Get expired points summary for user
     *
     * @param int $user_id User ID
     * @return array|WP_Error Array with summary data
     */
    public static function get_expired_summary($user_id) {
        try {
            global $wpdb;

            $user_id = intval($user_id);
            $expiry_table = $wpdb->prefix . 'sellsuite_point_expirations';
            $ledger_table = $wpdb->prefix . 'sellsuite_points_ledger';

            $query = $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_expirations,
                    SUM(l.points) as total_expired_points,
                    MIN(e.created_at) as first_expiry_date,
                    MAX(e.created_at) as last_expiry_date
                FROM {$expiry_table} e
                JOIN {$ledger_table} l ON e.ledger_id = l.id
                WHERE e.user_id = %d
                AND e.status = 'expired'",
                $user_id
            );

            $summary = $wpdb->get_row($query);

            if ($wpdb->last_error) {
                error_log('Database Error: ' . $wpdb->last_error);
                return new WP_Error(
                    'db_error',
                    'Database error retrieving summary',
                    array('status' => 500)
                );
            }

            return array(
                'total_expirations' => intval($summary->total_expirations),
                'total_expired_points' => intval($summary->total_expired_points ?? 0),
                'first_expiry_date' => $summary->first_expiry_date,
                'last_expiry_date' => $summary->last_expiry_date
            );

        } catch (Exception $e) {
            error_log('SellSuite Expired Summary Error: ' . $e->getMessage());
            return new WP_Error(
                'summary_error',
                'Error retrieving summary: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Manually expire points for testing/admin purposes
     *
     * @param int $ledger_id Ledger ID
     * @param int $user_id   User ID
     * @return bool|WP_Error True on success
     */
    public static function manually_expire_points($ledger_id, $user_id) {
        try {
            if (!current_user_can('manage_woocommerce')) {
                return new WP_Error(
                    'insufficient_permissions',
                    'You do not have permission to perform this action',
                    array('status' => 403)
                );
            }

            $ledger_id = intval($ledger_id);
            $user_id = intval($user_id);

            // Verify ledger exists and belongs to user
            global $wpdb;
            $table = $wpdb->prefix . 'sellsuite_points_ledger';

            $ledger = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE id = %d AND user_id = %d",
                    $ledger_id,
                    $user_id
                )
            );

            if (!$ledger) {
                return new WP_Error(
                    'ledger_not_found',
                    'Points record not found',
                    array('status' => 404)
                );
            }

            $expiry_table = $wpdb->prefix . 'sellsuite_point_expirations';

            $result = $wpdb->insert(
                $expiry_table,
                array(
                    'user_id' => $user_id,
                    'ledger_id' => $ledger_id,
                    'status' => 'expired',
                    'expiry_reason' => 'Manual expiration by admin',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s')
            );

            if (!$result) {
                return new WP_Error(
                    'expiry_error',
                    'Error expiring points: ' . $wpdb->last_error,
                    array('status' => 500)
                );
            }

            // Update ledger status
            $wpdb->update(
                $table,
                array('status' => 'expired'),
                array('id' => $ledger_id),
                array('%s'),
                array('%d')
            );

            do_action('sellsuite_points_manually_expired', $user_id, $ledger_id, $ledger->points);

            return true;

        } catch (Exception $e) {
            error_log('SellSuite Manual Expiry Error: ' . $e->getMessage());
            return new WP_Error(
                'expiry_error',
                'Error expiring points: ' . $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}
