<?php
/**
 * SellSuite Frontend Display
 *
 * Handles front-end display of points information on various pages
 *
 * @package    SellSuite
 * @subpackage SellSuite/includes
 * @author     AB Belal <info@ab-belal.com>
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Display Class
 *
 * Displays reward points information on product pages, checkout, and thank you page
 */
class SellSuite_Frontend_Display {

    /**
     * Display points earned on single product page
     *
     * Hook: woocommerce_after_add_to_cart_button
     */
    public static function display_product_points() {
        global $product;

        if (!$product) {
            return;
        }

        // Hide if reward points system is disabled
        if (!\SellSuite\Points::is_points_enabled()) {
            return;
        }

        $product_id = $product->get_id();
        $points = \SellSuite\Points::get_product_display_points($product_id);

        if ($points <= 0) {
            return;
        }

        ?>
        <div class="sellsuite-product-points">
            <p class="points-badge">
                <i class="fas fa-star"></i>
                <?php
                printf(
                    wp_kses_post(__('Earn <strong>%d Reward Points</strong> with this purchase', 'sellsuite')),
                    intval($points)
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add points column to checkout review order table
     *
     * Hook: woocommerce_checkout_table_shipping_title_html
     * We'll use a custom hook instead
     */
    public static function add_checkout_points_row() {
        if (!is_checkout() || WC()->cart->is_empty()) {
            return;
        }

        // Hide if reward points system is disabled
        if (!\SellSuite\Points::is_points_enabled()) {
            return;
        }

        // Calculate earned points based on product subtotal only (excluding shipping, taxes, fees)
        // Formula: Points = Cart Subtotal (1:1 ratio)
        // This excludes shipping, taxes, and fees - only product prices count
        $cart_subtotal = WC()->cart->get_subtotal();
        $total_points = intval(floor($cart_subtotal));

        if ($total_points <= 0) {
            return;
        }

        ?>
        <tr class="sellsuite-points-row">
            <th><?php esc_html_e('Points Earned', 'sellsuite'); ?></th>
            <td>
                <strong class="points-amount">
                    <i class="fas fa-star"></i> <?php echo intval($total_points); ?>
                </strong>
            </td>
        </tr>
        <?php
    }


    /**
     * Display product points in cart items
     *
     * Hook: woocommerce_after_cart_item_name (in cart page)
     *
     * @param array $cart_item Cart item data
     * @param string $cart_item_key Cart item key
     */
    public static function display_cart_item_points($cart_item, $cart_item_key) {
        // Hide if reward points system is disabled
        if (!\SellSuite\Points::is_points_enabled()) {
            return;
        }

        $product = $cart_item['data'];
        $quantity = $cart_item['quantity'];

        $product_id = $product->get_id();
        $points = \SellSuite\Points::get_product_display_points($product_id);

        if ($points <= 0) {
            return;
        }

        $total_points = $points * $quantity;

        ?>
        <div class="sellsuite-cart-item-points">
            <small>
                <i class="fas fa-star"></i>
                <?php
                printf(
                    wp_kses_post(__('Earn %d points', 'sellsuite')),
                    intval($total_points)
                );
                ?>
            </small>
        </div>
        <?php
    }

    /**
     * Add inline CSS for points display
     *
     * Hook: wp_head
     */
    public static function add_inline_css() {
        ?>
        <style>
            .sellsuite-product-points {
                margin: 15px 0;
                padding: 15px;
                background: #f0f8ff;
                border-left: 4px solid #007cba;
                border-radius: 3px;
            }

            .sellsuite-product-points .points-badge {
                margin: 0;
                font-size: 14px;
                color: #333;
            }

            .sellsuite-product-points i {
                color: #ffc107;
                margin-right: 5px;
            }

            .sellsuite-product-points strong {
                color: #007cba;
                font-weight: 600;
            }

            .sellsuite-thankyou-points,
            .sellsuite-thankyou-balance {
                margin: 20px 0;
                padding: 20px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 5px;
            }

            .sellsuite-thankyou-points h3,
            .sellsuite-thankyou-balance h3 {
                margin-top: 0;
                color: #333;
            }

            .points-info-box,
            .balance-info-box {
                background: white;
                padding: 15px;
                border-radius: 3px;
                margin-top: 10px;
            }

            .points-earned {
                margin: 0;
                font-size: 16px;
                color: #333;
            }

            .points-help {
                margin: 10px 0 0 0;
                font-size: 14px;
                color: #666;
            }

            .balance-display {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 18px;
            }

            .balance-label {
                font-weight: 600;
                color: #333;
            }

            .balance-amount {
                font-size: 24px;
                font-weight: 700;
                color: #ffc107;
            }

            .balance-amount i {
                margin-right: 8px;
            }

            .sellsuite-cart-item-points {
                display: block;
                margin-top: 5px;
            }

            .sellsuite-cart-item-points i {
                color: #ffc107;
                margin-right: 3px;
            }

            @media (max-width: 768px) {
                .balance-display {
                    flex-direction: column;
                    text-align: center;
                    gap: 10px;
                }
            }
        </style>
        <?php
    }

    /**
     * Display point redemption box on checkout
     *
     * Hook: woocommerce_review_order_after_shipping
     */
    public static function display_redemption_box() {
        // Only show on checkout page
        if (!is_checkout()) {
            return;
        }

        // Only show for logged-in users
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Hide if reward points system is disabled
        if (!\SellSuite\Points::is_points_enabled()) {
            return;
        }

        // Check if user already has pending redemption
        // If so, don't show the redemption box (it's already applied)
        $pending_redemption_data = get_user_meta($user_id, '_pending_point_redemption', true);
        if (!empty($pending_redemption_data)) {
            return; // Redemption already applied, hide the box
        }

        // Check if user has available points
        $available_points = \SellSuite\Points::get_available_points($user_id);
        
        // Get pending redemption points (points waiting to be permanently deducted)
        $pending_redemption_points = \SellSuite\Redeem_Handler::get_pending_redemption_points($user_id);
        
        // Calculate adjusted available (can only use points not in pending redemption)
        $adjusted_available = max(0, $available_points - $pending_redemption_points);
        
        if ($adjusted_available <= 0) {
            return;
        }

        // Load the template
        $template_path = SELLSUITE_PLUGIN_DIR . 'templates/woocommerce/checkout/point-redemption.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Enqueue point redemption JavaScript on checkout
     *
     * Hook: wp_enqueue_scripts
     */
    public static function enqueue_redemption_scripts() {
        // Only on checkout page
        if (!is_checkout()) {
            return;
        }

        // Only for logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        // Enqueue jQuery (WooCommerce already includes it)
        wp_enqueue_script('jquery');

        // Enqueue point redemption script
        wp_enqueue_script(
            'sellsuite-point-redemption',
            SELLSUITE_PLUGIN_URL . 'public/assets/js/src/point-redemption.js',
            array('jquery'),
            SELLSUITE_VERSION,
            true
        );

        // Get data for JavaScript
        $user_id = get_current_user_id();
        $available_points = \SellSuite\Points::get_available_points($user_id);
        
        // Get pending redemption points (points waiting to be permanently deducted)
        $pending_redemption_points = \SellSuite\Redeem_Handler::get_pending_redemption_points($user_id);
        
        // Calculate adjusted available (can only use points not in pending redemption)
        $adjusted_available = max(0, $available_points - $pending_redemption_points);
        
        $settings = \SellSuite\Points::get_settings();
        
        // Get pending redemption data from user meta (if page is reloaded after applying points)
        $pending_redemption_data = get_user_meta($user_id, '_pending_point_redemption', true);
        $pending_redeemed_points = 0;
        $pending_discount_value = 0;
        if ($pending_redemption_data && !empty($pending_redemption_data)) {
            $pending_redeemed_points = intval($pending_redemption_data['redeemed_points'] ?? 0);
            $pending_discount_value = floatval($pending_redemption_data['discount_value'] ?? 0);
        }
        
        // Get cart subtotal (product total without shipping, taxes, or fees)
        // This is used for calculating earned points
        $cart_subtotal = 0;
        if (WC()->cart) {
            $cart_subtotal = floatval(WC()->cart->get_subtotal());
        }
        
        // Get order total (for max redeemable percentage calculation)
        $order_total = 0;
        if (WC()->cart) {
            $order_total = floatval(WC()->cart->get_total(false));
        }

        // Localize data for JavaScript
        wp_localize_script(
            'sellsuite-point-redemption',
            'sellsuiteRedemptionData',
            array(
                'conversion_rate' => floatval($settings['conversion_rate'] ?? 1),
                'max_redeemable_percentage' => floatval($settings['max_redeemable_percentage'] ?? 20),
                'available_points' => intval($adjusted_available), // Use adjusted available instead of total
                'total_available_points' => intval($available_points), // Keep total for reference
                'pending_redemption_points' => intval($pending_redemption_points), // Show pending separately
                'cart_subtotal' => $cart_subtotal, // Product total without shipping (for points calculation)
                'order_total' => $order_total, // Full total including shipping (for max redeemable calculation)
                'has_pending_redemption' => !empty($pending_redemption_data), // Check if redemption is active
                'pending_redeemed_points' => $pending_redeemed_points, // Points being redeemed
                'pending_discount_value' => $pending_discount_value, // Discount being applied
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'currency_position' => get_option( 'woocommerce_currency_pos' ),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxurl' => admin_url('admin-ajax.php'),
            )
        );
    }
}
