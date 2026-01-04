<?php
namespace SellSuite;

/**
 * WooCommerce integration functionality.
 *
 * Handles all WooCommerce-specific hooks and filters.
 */
class WooCommerce_Integration {

    public function __construct() {
        // Template override: let plugin provide WooCommerce templates from templates/woocommerce/
        add_filter('woocommerce_locate_template', array($this, 'locate_plugin_template'), 10, 3);

        // Product meta boxes
        add_action('add_meta_boxes', array(Product_Meta::class, 'add_product_meta_box'));
        add_action('save_post_product', array(Product_Meta::class, 'save_product_meta_box'));

        /**
         * SIMPLE PRODUCT
         * General → Pricing → After Sale Price
         */
        add_action( 'woocommerce_product_options_pricing', [Product_Meta::class, 'add_cost_price_simple'] );
        add_action( 'woocommerce_process_product_meta', [Product_Meta::class, 'save_cost_price_simple'] );

        // Product variations
        add_action('woocommerce_product_after_variable_attributes', array(Product_Meta::class, 'add_variation_options'), 10, 3);
        add_action('woocommerce_save_product_variation', array(Product_Meta::class, 'save_variation_meta'), 10, 2);

        /**
         * VARIABLE PRODUCT
         * Each variation → Pricing → After Sale Price
         */
        add_action('woocommerce_variation_options_pricing', [Product_Meta::class, 'add_cost_price_variation'], 10, 3);
        add_action('woocommerce_save_product_variation', [Product_Meta::class, 'save_cost_price_variation'], 10, 2);
        

        // Product deletion
        add_action('delete_post', array(Product_Meta::class, 'on_product_delete'));

        // Order points handling
        Order_Handler::init();

        // Refund handling
        Refund_Handler::init();

        // Apply point redemption discount to cart as a fee
        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_redemption_discount_fee'));

        // Filter order statuses in admin to show only Completed and Cancelled
        add_filter('wc_order_statuses', array($this, 'sellsuite_limit_order_statuses'), 10, 1);

        // PHASE 7: Point expiry scheduled processing
        add_action('sellsuite_process_point_expirations', array($this, 'process_all_expirations'));

        // PHASE 8: Currency exchange rate caching and updates
        add_action('sellsuite_update_exchange_rates', array($this, 'refresh_exchange_rates'));

        add_action('woocommerce_cart_calculate_fees', array($this, 'sellsuite_apply_point_discount'), 20);
        add_action('woocommerce_checkout_create_order', array($this, 'sellsuite_save_redemption_to_order'), 20, 2);
        add_action('woocommerce_thankyou', array($this, 'sellsuite_clear_redemption_session'));
        
        // Display redemption on thank you page
        add_action('woocommerce_thankyou', array($this, 'display_redemption_on_thankyou'), 15);
        
        // Display redemption in order details (frontend and backend)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_redemption_in_order_details'), 10);
        add_action('woocommerce_admin_order_details_after_order_details', array($this, 'display_redemption_in_order_details'), 10);
    }

    

    /**
     * Applies a point redemption discount to the WooCommerce cart.
     *
     * This function is called in the `woocommerce_calculate_fees` hook to apply
     * the discount amount set in the session. It will only apply if the
     * user is not an admin and if the `DOING_AJAX` constant is defined.
     *
     * @param WC_Cart $cart The WooCommerce cart object.
     *
     * @return void
     */
    public function sellsuite_apply_point_discount( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $redeemed_points = WC()->session->get( 'sellsuite_redeemed_points' );
        $discount_amount = WC()->session->get( 'sellsuite_redeem_discount' );

        if ( ! $redeemed_points || ! $discount_amount ) {
            return;
        }

        $cart->add_fee(
            __( 'Point Redemption Discount', 'sellsuite' ),
            -abs( $discount_amount ),
            false
        );
    }

    /**
     * Saves point redemption data to an order.
     *
     * Called in the `woocommerce_new_order` hook, this function saves the
     * point redemption data to the order meta for future reference.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param array $data The point redemption data array.
     */
    public function sellsuite_save_redemption_to_order( $order, $data ) {
        $redeemed_points = WC()->session->get( 'sellsuite_redeemed_points' );
        $discount_amount = WC()->session->get( 'sellsuite_redeem_discount' );

        if ( $redeemed_points && $discount_amount ) {
            $order->update_meta_data( '_sellsuite_redeemed_points', $redeemed_points );
            $order->update_meta_data( '_sellsuite_redeem_discount', $discount_amount );
        }
    }

    /**
     * Clears point redemption data from the session.
     *
     * This function removes the point redemption data stored in the
     * WooCommerce session after the order is placed.
     *
     * @return void
     */
    public function sellsuite_clear_redemption_session() {
        WC()->session->__unset( 'sellsuite_redeemed_points' );
        WC()->session->__unset( 'sellsuite_redeem_discount' );
    }

    /**
     * Save product cost price meta.
     * 
     * @param int $post_id Product ID
     * @return void
     */
    public function save_product_cost_price($post_id) {
        // Verify nonce for product meta box
        if (!isset($_POST['sellsuite_product_points_nonce']) || !wp_verify_nonce($_POST['sellsuite_product_points_nonce'], 'sellsuite_product_points_nonce')) {
            return;
        }

        // Verify user capability
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check if product cost price field exists in post data
        if (isset($_POST['_product_cost_price'])) {
            $cost_price = sanitize_text_field($_POST['_product_cost_price']);
            if ($cost_price !== '') {
                update_post_meta($post_id, '_product_cost_price', floatval($cost_price));
            } else {
                delete_post_meta($post_id, '_product_cost_price');
            }
        }
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

    /**
     * Filter order statuses in admin to show only Processing, Completed and Cancelled.
     * This filters the order status select box on the order edit page.
     *
     * @param array $order_statuses All available WooCommerce order statuses.
     * @return array Filtered array with only 'processing', 'completed' and 'cancelled' statuses on admin pages.
     */
    public function sellsuite_limit_order_statuses($order_statuses) {
        // Only filter on admin pages
        if (!is_admin()) {
            return $order_statuses;
        }

        // Check if we're on the order edit page or order list page
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders'), true)) {
            return $order_statuses;
        }

        $allowed_statuses = array(
            'wc-processing',
            'wc-completed',
            'wc-cancelled',
        );

        foreach ( $order_statuses as $status_key => $status_label ) {
            if ( ! in_array( $status_key, $allowed_statuses, true ) ) {
                unset( $order_statuses[ $status_key ] );
            }
        }

        return $order_statuses;
    }

    /**
     * Apply redeemed points discount to the WooCommerce cart as a negative fee.
     *
     * This hooks into woocommerce_cart_calculate_fees to apply the discount
     * that was created when the user redeemed points at checkout.
     * During checkout, redemption is stored in user meta (_pending_point_redemption),
     * not in database yet.
     *
     * @return void
     */
    
    public function apply_redemption_discount_fee() {
        // Only apply on checkout or cart pages
        if (is_admin()) {
            return;
        }

        if (!is_checkout() && !is_cart()) {
            return;
        }

        // Get current user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            return; // Guest checkout - no redemption
        }

        // Get pending redemption from user meta (stored during checkout by applyRedemption() JS)
        // This is temporary data that exists only during current checkout session
        $redemption_data = get_user_meta($user_id, '_pending_point_redemption', true);
        
        if (empty($redemption_data) || !is_array($redemption_data)) {
            return; // No pending redemption
        }

        // Extract discount value from redemption data
        $discount_value = floatval($redemption_data['discount_value'] ?? 0);
        if ($discount_value <= 0) {
            return; // Invalid discount value
        }

        // Apply the discount as a negative fee
        WC()->cart->add_fee(
            __('Points Discount', 'sellsuite'),
            -$discount_value
        );
    }

    /**
     * PHASE 7: Process all point expirations for all users.
     *
     * This is called by the WordPress scheduled cron job
     * and processes expired points for all users in the system.
     */
    public function process_all_expirations() {
        try {
            global $wpdb;

            // Get all users with points
            $table = $wpdb->prefix . 'sellsuite_user_points';

            $user_ids = $wpdb->get_col(
                "SELECT DISTINCT user_id FROM {$table} WHERE status = 'earned'"
            );

            if (empty($user_ids)) {
                return;
            }

            foreach ($user_ids as $user_id) {
                Expiry_Handler::process_user_expirations($user_id);
            }

            do_action('sellsuite_expirations_processed', count($user_ids));

        } catch (Exception $e) {
            error_log('SellSuite Expiration Processing Error: ' . $e->getMessage());
        }
    }

    /**
     * PHASE 8: Refresh exchange rates.
     *
     * This is called by the WordPress scheduled cron job
     * and can be extended to fetch rates from external APIs.
     */
    public function refresh_exchange_rates() {
        try {
            // This can be extended to fetch rates from external services
            // For now, it just triggers the action for logging/tracking
            do_action('sellsuite_exchange_rates_refreshed');

        } catch (Exception $e) {
            error_log('SellSuite Exchange Rate Refresh Error: ' . $e->getMessage());
        }
    }

    /**
     * Display points redemption discount on order thank you page.
     *
     * Shows the points redeemed and discount applied on the order thank you page.
     *
     * @param int $order_id Order ID
     * @return void
     */
    public function display_redemption_on_thankyou($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get redemption data from order meta
        $redeemed_points = $order->get_meta('_sellsuite_redeemed_points');
        $discount_amount = $order->get_meta('_sellsuite_redeem_discount');

        if (!$redeemed_points || !$discount_amount) {
            return; // No redemption applied to this order
        }

        ?>
        <div style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-left: 4px solid #28a745; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #28a745;"><?php esc_html_e('Points Redemption', 'sellsuite'); ?></h3>
            <p>
                <strong><?php esc_html_e('Points Used:', 'sellsuite'); ?></strong> 
                <?php echo intval($redeemed_points); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Discount Applied:', 'sellsuite'); ?></strong> 
                <?php echo wc_price($discount_amount); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display points redemption info in order details (backend and frontend).
     *
     * Shows redemption details in order meta box and order review.
     *
     * @param int $order_id Order ID
     * @return void
     */
    public function display_redemption_in_order_details($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Get redemption data from order meta
        $redeemed_points = $order->get_meta('_sellsuite_redeemed_points');
        $discount_amount = $order->get_meta('_sellsuite_redeem_discount');

        if (!$redeemed_points || !$discount_amount) {
            return; // No redemption applied to this order
        }

        ?>
        <div style="margin: 15px 0; padding: 12px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
            <p style="margin: 0 0 8px 0;">
                <strong style="color: #0073aa;"><?php esc_html_e('Customer Loyalty Points Used', 'sellsuite'); ?></strong>
            </p>
            <p style="margin: 0;">
                <?php 
                echo sprintf(
                    esc_html__('%s points redeemed for %s discount', 'sellsuite'),
                    '<strong>' . intval($redeemed_points) . '</strong>',
                    '<strong>' . wc_price($discount_amount) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

}
