<?php
namespace SellSuite;

/**
 * Handle customer management functionality.
 */
class Customers {

    /**
     * Get customer data with points.
     */
    public static function get_customer_with_points($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        $points = Points::get_user_total_points($user_id);
        $customer = wc_get_customer($user_id);

        return array(
            'id' => $user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'points' => $points,
            'orders_count' => $customer ? $customer->get_order_count() : 0,
            'total_spent' => $customer ? $customer->get_total_spent() : 0,
        );
    }

    /**
     * Get all customers with points.
     */
    public static function get_all_customers_with_points($limit = 50, $offset = 0) {
        $args = array(
            'role' => 'customer',
            'number' => $limit,
            'offset' => $offset,
        );

        $users = get_users($args);
        $customers = array();

        foreach ($users as $user) {
            $customers[] = self::get_customer_with_points($user->ID);
        }

        return $customers;
    }

    /**
     * Search customers by name or email.
     */
    public static function search_customers($search_term, $limit = 20) {
        $args = array(
            'role' => 'customer',
            'search' => '*' . esc_attr($search_term) . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => $limit,
        );

        $users = get_users($args);
        $customers = array();

        foreach ($users as $user) {
            $customers[] = self::get_customer_with_points($user->ID);
        }

        return $customers;
    }
}
