<?php
namespace SellSuite;

/**
 * WooCommerce integration functionality.
 *
 * Handles all WooCommerce-specific hooks and filters.
 */
class WooCommerce_Integration {

    public function __construct() {
        // Order completion hooks
        add_action('woocommerce_order_status_completed', array($this, 'award_points_on_order_complete'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'award_points_on_payment_complete'), 10, 1);
        add_action('woocommerce_thankyou', array($this, 'display_points_earned_message'), 10, 1);

        // Account page hooks
        add_action('woocommerce_before_my_account', array($this, 'display_points_summary'), 5);
        add_action('woocommerce_account_dashboard', array($this, 'display_points_history'), 20);

        // Cart and checkout hooks
        add_action('woocommerce_cart_totals_before_order_total', array($this, 'display_potential_points'));
        add_action('woocommerce_review_order_before_order_total', array($this, 'display_potential_points'));
        

        // Product page hooks
        add_action('woocommerce_single_product_summary', array($this, 'display_product_points'), 25);

        // Admin order page
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_order_points_info'));

        // Template override: let plugin provide WooCommerce templates from templates/woocommerce/
        // add_filter('woocommerce_locate_template', array($this, 'locate_plugin_template'), 10, 3);
        
    }


    public function award_points_on_order_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        $settings = get_option('sellsuite_settings', array());
        $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;
        $order_total = $order->get_total();
        $points = floor($order_total * $points_per_dollar);

        if ($points > 0 && class_exists('SellSuite\\Points')) {
            \SellSuite\Points::award_points($user_id, $points, $order_id, 'order_complete');
        }
    }

    public function award_points_on_payment_complete($order_id) {
        $this->award_points_on_order_complete($order_id);
    }

    public function display_points_earned_message($order_id) {
        if (!is_points_enabled()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $settings = get_option('sellsuite_settings', array());
        $points_per_dollar = isset($settings['points_per_dollar']) ? floatval($settings['points_per_dollar']) : 1;
        $order_total = $order->get_total();
        $points = floor($order_total * $points_per_dollar);

        if ($points > 0) {
            $message = sprintf(esc_html__('You earned %s loyalty points with this order!', 'sellsuite'), '<strong>' . esc_html(format_points($points)) . '</strong>');
            echo '<div class="woocommerce-message sellsuite-points-earned">';
            echo '<strong>' . esc_html__('Congratulations!', 'sellsuite') . '</strong> ' . wp_kses_post($message);
            echo '</div>';
        }
    }

    public function display_points_summary() {
        if (!is_points_enabled() || !is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $points = Points::get_user_total_points($user_id);
        $redemption_value = points_to_currency($points);

        $output  = '<div class="sellsuite-points-display">';
        $output .= '<h3>' . esc_html__('Your Loyalty Points', 'sellsuite') . '</h3>';
        $output .= '<div class="sellsuite-points-summary">';
        $output .= '<div class="sellsuite-points-total">';
        $output .= '<span class="points-label">' . esc_html__('Total Points:', 'sellsuite') . '</span>';
        $output .= '<strong class="points-value">' . esc_html(format_points($points)) . '</strong>';
        $output .= '</div>';
        $output .= '<div class="sellsuite-points-value">';
        $output .= '<span class="value-label">' . esc_html__('Redemption Value:', 'sellsuite') . '</span>';
        $output .= '<strong class="value-amount">' . wc_price($redemption_value) . '</strong>';
        $output .= '</div></div>';
        $output .= '<a href="#" class="button sellsuite-toggle-history">' . esc_html__('View Points History', 'sellsuite') . '</a>';
        $output .= '</div>';

        echo $output;
    }

    public function display_points_history() {
        if (!is_points_enabled() || !is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $history = Points::get_user_points_history($user_id, 20);
        if (empty($history)) {
            return;
        }

        $output  = '<div class="sellsuite-points-history" style="display: none;">';
        $output .= '<h4>' . esc_html__('Points History', 'sellsuite') . '</h4>';
        $output .= '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">';
        $output .= '<thead><tr><th>' . esc_html__('Date', 'sellsuite') . '</th><th>' . esc_html__('Description', 'sellsuite') . '</th><th>' . esc_html__('Points', 'sellsuite') . '</th></tr></thead>';
        $output .= '<tbody>';

        foreach ($history as $entry) {
            $date = esc_html(date_i18n(get_option('date_format'), strtotime($entry->created_at)));
            $desc = esc_html($entry->description);
            $pts  = esc_html(format_points($entry->points));
            $badge_class = $entry->points > 0 ? 'positive' : 'negative';
            $sign = $entry->points > 0 ? '+' : '';

            $output .= '<tr>';
            $output .= '<td>' . $date . '</td>';
            $output .= '<td>' . $desc . '</td>';
            $output .= '<td><span class="sellsuite-points-badge ' . $badge_class . '">' . $sign . $pts . '</span></td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table></div>';
        echo $output;
    }

    public function display_potential_points() {
        if (!is_points_enabled() || !is_user_logged_in()) {
            return;
        }

        $cart_total = WC()->cart->get_total('raw');
        $points = currency_to_points($cart_total);

        if ($points > 0) {
            echo '<tr class="sellsuite-potential-points">';
            echo '<th>' . esc_html__("Points You'll Earn", 'sellsuite') . '</th>';
            echo '<td data-title="' . esc_attr__("Points You'll Earn", 'sellsuite') . '">';
            echo '<strong>' . esc_html(format_points($points)) . '</strong>';
            echo '</td></tr>';
        }
    }

    public function display_product_points() {
        if (!is_points_enabled() || !is_user_logged_in()) {
            return;
        }

        global $product;
        if (!$product) {
            return;
        }

        $price = $product->get_price();
        if (!$price) {
            return;
        }

        $points = currency_to_points($price);

        if ($points > 0) {
            $message = sprintf(esc_html__('Earn %s points with this purchase', 'sellsuite'), '<strong>' . esc_html(format_points($points)) . '</strong>');
            echo '<div class="sellsuite-product-points">';
            echo '<span class="points-icon">⭐</span> ' . wp_kses_post($message);
            echo '</div>';
        }
    }

    public function display_order_points_info($order) {
        if (!is_points_enabled()) {
            return;
        }

        $order_id = $order->get_id();
        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'sellsuite_points';
        $points_entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %d AND action_type = 'order_complete'", $order_id));

        $output  = '<div class="sellsuite-order-points-info">';
        $output .= '<h3>' . esc_html__('SellSuite Points', 'sellsuite') . '</h3>';
        if ($points_entry) {
            $output .= '<p><strong>' . esc_html__('Points Awarded:', 'sellsuite') . '</strong> ' . esc_html(format_points($points_entry->points)) . '</p>';
            $output .= '<p><strong>' . esc_html__('Awarded On:', 'sellsuite') . '</strong> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($points_entry->created_at))) . '</p>';
        } else {
            $output .= '<p>' . esc_html__('No points awarded yet for this order.', 'sellsuite') . '</p>';
        }
        $output .= '<p><strong>' . esc_html__('Customer Total Points:', 'sellsuite') . '</strong> ' . esc_html(format_points(Points::get_user_total_points($user_id))) . '</p>';
        $output .= '</div>';

        echo $output;
    }

    public function locate_plugin_template($template, $template_name, $template_path) {
        $plugin_template = SELLSUITE_PLUGIN_DIR . 'templates/woocommerce/' . $template_name;
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }

        $plugin_template_with_path = SELLSUITE_PLUGIN_DIR . 'templates/woocommerce/' . ltrim($template_path, '/') . $template_name;
        if (file_exists($plugin_template_with_path)) {
            return $plugin_template_with_path;
        }

        return $template;
    }

}
