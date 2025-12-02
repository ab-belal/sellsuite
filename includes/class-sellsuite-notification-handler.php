<?php
namespace SellSuite;

/**
 * Handle all notification operations.
 * 
 * Manages email, SMS, and in-app notifications for point events
 * with user preference handling and template support.
 */
class Notification_Handler {

    /**
     * Initialize notification hooks.
     * 
     * @return void
     */
    public static function init() {
        add_action('sellsuite_points_awarded_pending', array(self::class, 'send_points_awarded_notification'), 10, 3);
        add_action('sellsuite_points_earned', array(self::class, 'send_points_earned_notification'), 10, 3);
        add_action('sellsuite_points_redeemed', array(self::class, 'send_redemption_notification'), 10, 5);
        add_action('sellsuite_points_deducted_refund', array(self::class, 'send_refund_notification'), 10, 4);
        add_action('sellsuite_redemption_canceled', array(self::class, 'send_redemption_canceled_notification'), 10, 3);
    }

    /**
     * Send notification when points are awarded.
     * 
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param int $points Points awarded
     * @return bool Success
     */
    public static function send_points_awarded_notification($order_id, $user_id, $points) {
        try {
            // Check user preference
            if (!self::get_user_notification_preference($user_id, 'points_awarded')) {
                return false;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            $subject = sprintf(__('You earned %d reward points!', 'sellsuite'), $points);
            
            $data = array(
                'user_name' => $user->display_name,
                'order_id' => $order_id,
                'points' => $points,
                'available_balance' => Points_Manager::get_available_balance($user_id),
                'email' => $user->user_email,
            );

            // Send email notification
            self::send_email($user->user_email, $subject, 'points_awarded', $data);

            // Create in-app notification
            self::create_in_app_notification($user_id, 'points_awarded', $subject, $data);

            do_action('sellsuite_notification_sent', 'points_awarded', $user_id, $data);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when points transition to earned.
     * 
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param int $points Points earned
     * @return bool Success
     */
    public static function send_points_earned_notification($order_id, $user_id, $points) {
        try {
            if (!self::get_user_notification_preference($user_id, 'points_earned')) {
                return false;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            $subject = sprintf(__('Your %d reward points are now available!', 'sellsuite'), $points);

            $data = array(
                'user_name' => $user->display_name,
                'order_id' => $order_id,
                'points' => $points,
                'available_balance' => Points_Manager::get_available_balance($user_id),
                'email' => $user->user_email,
            );

            self::send_email($user->user_email, $subject, 'points_earned', $data);
            self::create_in_app_notification($user_id, 'points_earned', $subject, $data);

            do_action('sellsuite_notification_sent', 'points_earned', $user_id, $data);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Points Earned Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when points are redeemed.
     * 
     * @param int    $user_id User ID
     * @param int    $points Points redeemed
     * @param float  $discount_value Discount value
     * @param int    $order_id Order ID
     * @param int    $redemption_id Redemption ID
     * @return bool Success
     */
    public static function send_redemption_notification($user_id, $points, $discount_value, $order_id, $redemption_id) {
        try {
            if (!self::get_user_notification_preference($user_id, 'redemption')) {
                return false;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            $subject = sprintf(
                __('Redemption confirmed: %d points = %s discount', 'sellsuite'),
                $points,
                wc_price($discount_value)
            );

            $data = array(
                'user_name' => $user->display_name,
                'points' => $points,
                'discount_value' => $discount_value,
                'order_id' => $order_id,
                'redemption_id' => $redemption_id,
                'remaining_balance' => Points_Manager::get_available_balance($user_id),
                'email' => $user->user_email,
            );

            self::send_email($user->user_email, $subject, 'redemption', $data);
            self::create_in_app_notification($user_id, 'redemption', $subject, $data);

            do_action('sellsuite_notification_sent', 'redemption', $user_id, $data);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Redemption Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when points are deducted due to refund.
     * 
     * @param int $order_id Order ID
     * @param int $user_id User ID
     * @param int $points_deducted Points deducted
     * @param int $refund_id Refund ID
     * @return bool Success
     */
    public static function send_refund_notification($order_id, $user_id, $points_deducted, $refund_id) {
        try {
            if (!self::get_user_notification_preference($user_id, 'refund')) {
                return false;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            $subject = sprintf(__('Refund processed: %d reward points deducted', 'sellsuite'), $points_deducted);

            $data = array(
                'user_name' => $user->display_name,
                'order_id' => $order_id,
                'refund_id' => $refund_id,
                'points_deducted' => $points_deducted,
                'remaining_balance' => Points_Manager::get_available_balance($user_id),
                'email' => $user->user_email,
            );

            self::send_email($user->user_email, $subject, 'refund', $data);
            self::create_in_app_notification($user_id, 'refund', $subject, $data);

            do_action('sellsuite_notification_sent', 'refund', $user_id, $data);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Refund Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when redemption is canceled.
     * 
     * @param int $redemption_id Redemption ID
     * @param int $user_id User ID
     * @param int $points_restored Points restored
     * @return bool Success
     */
    public static function send_redemption_canceled_notification($redemption_id, $user_id, $points_restored) {
        try {
            if (!self::get_user_notification_preference($user_id, 'redemption_canceled')) {
                return false;
            }

            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }

            $subject = sprintf(__('Redemption canceled: %d points restored', 'sellsuite'), $points_restored);

            $data = array(
                'user_name' => $user->display_name,
                'redemption_id' => $redemption_id,
                'points_restored' => $points_restored,
                'available_balance' => Points_Manager::get_available_balance($user_id),
                'email' => $user->user_email,
            );

            self::send_email($user->user_email, $subject, 'redemption_canceled', $data);
            self::create_in_app_notification($user_id, 'redemption_canceled', $subject, $data);

            do_action('sellsuite_notification_sent', 'redemption_canceled', $user_id, $data);
            return true;

        } catch (\Exception $e) {
            error_log('SellSuite Redemption Canceled Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification.
     * 
     * @param string $to Email address
     * @param string $subject Email subject
     * @param string $template Template name
     * @param array  $data Template data
     * @return bool Success
     */
    public static function send_email($to, $subject, $template, $data = array()) {
        try {
            // Validate email
            if (!is_email($to)) {
                return false;
            }

            // Get email template
            $body = Email_Templates::get_template($template, $data);
            if (!$body) {
                return false;
            }

            // Prepare headers
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
            );

            // Get from email
            $from_email = get_option('sellsuite_from_email', get_option('admin_email'));
            $from_name = get_option('sellsuite_from_name', get_bloginfo('name'));

            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

            // Apply filters
            $to = apply_filters('sellsuite_email_recipient', $to, $template);
            $subject = apply_filters('sellsuite_email_subject', $subject, $template);
            $body = apply_filters('sellsuite_email_body', $body, $template);

            // Send email
            $sent = wp_mail($to, $subject, $body, $headers);

            if ($sent) {
                // Log email sent
                self::log_notification($to, $template, 'email', true);
            }

            return $sent;

        } catch (\Exception $e) {
            error_log('SellSuite Email Send Error: ' . $e->getMessage());
            self::log_notification($to, $template, 'email', false);
            return false;
        }
    }

    /**
     * Create in-app notification.
     * 
     * @param int    $user_id User ID
     * @param string $type Notification type
     * @param string $title Notification title
     * @param array  $data Notification data
     * @return int Notification ID
     */
    public static function create_in_app_notification($user_id, $type, $title, $data = array()) {
        global $wpdb;

        try {
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'sellsuite_notifications',
                array(
                    'user_id' => intval($user_id),
                    'type' => sanitize_text_field($type),
                    'title' => sanitize_text_field($title),
                    'data' => maybe_serialize($data),
                    'is_read' => 0,
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s')
            );

            if ($inserted) {
                self::log_notification($user_id, $type, 'in-app', true);
                return $wpdb->insert_id;
            }

            return 0;

        } catch (\Exception $e) {
            error_log('SellSuite In-App Notification Error: ' . $e->getMessage());
            self::log_notification($user_id, $type, 'in-app', false);
            return 0;
        }
    }

    /**
     * Get user notification preferences.
     * 
     * @param int    $user_id User ID
     * @param string $type Notification type
     * @return bool User preference (default: true)
     */
    public static function get_user_notification_preference($user_id, $type) {
        $preferences = get_user_meta($user_id, 'sellsuite_notification_prefs', true);
        
        if (!is_array($preferences)) {
            return true;  // Default: enabled
        }

        return !isset($preferences[$type]) || $preferences[$type] !== false;
    }

    /**
     * Update user notification preferences.
     * 
     * @param int   $user_id User ID
     * @param array $preferences Preferences array
     * @return bool Success
     */
    public static function update_user_preferences($user_id, $preferences) {
        // Sanitize preferences
        $sanitized = array();
        $allowed_types = array(
            'points_awarded',
            'points_earned',
            'redemption',
            'refund',
            'redemption_canceled',
            'expiry_warning',
            'admin_notification',
        );

        foreach ($allowed_types as $type) {
            if (isset($preferences[$type])) {
                $sanitized[$type] = (bool)$preferences[$type];
            }
        }

        return update_user_meta($user_id, 'sellsuite_notification_prefs', $sanitized);
    }

    /**
     * Get user's unread notifications.
     * 
     * @param int $user_id User ID
     * @param int $limit Number of notifications
     * @return array Notifications
     */
    public static function get_unread_notifications($user_id, $limit = 10) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sellsuite_notifications 
                WHERE user_id = %d AND is_read = 0 
                ORDER BY created_at DESC 
                LIMIT %d",
                intval($user_id),
                intval($limit)
            )
        );
    }

    /**
     * Mark notification as read.
     * 
     * @param int $notification_id Notification ID
     * @return bool Success
     */
    public static function mark_as_read($notification_id) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'sellsuite_notifications',
            array('is_read' => 1),
            array('id' => intval($notification_id)),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Get notification by ID.
     * 
     * @param int $notification_id Notification ID
     * @return object|null Notification
     */
    public static function get_notification($notification_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}sellsuite_notifications WHERE id = %d",
                intval($notification_id)
            )
        );
    }

    /**
     * Log notification action.
     * 
     * @param mixed  $recipient Recipient (email or user_id)
     * @param string $type Notification type
     * @param string $channel Channel (email, sms, in-app)
     * @param bool   $success Success status
     * @return void
     */
    private static function log_notification($recipient, $type, $channel, $success) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'sellsuite_notification_logs',
            array(
                'recipient' => is_email($recipient) ? sanitize_email($recipient) : intval($recipient),
                'type' => sanitize_text_field($type),
                'channel' => sanitize_text_field($channel),
                'success' => intval($success),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }

    /**
     * Delete old notifications.
     * 
     * @param int $days_old Number of days to keep
     * @return int Deleted count
     */
    public static function cleanup_old_notifications($days_old = 30) {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}sellsuite_notifications WHERE created_at < %s",
                $date
            )
        );
    }
}
