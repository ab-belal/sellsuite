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

        $total_points = 0;

        // Calculate total points for all items in cart
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            
            $product_id = $product->get_id();
            $points = \SellSuite\Points::get_product_display_points($product_id);
            
            $total_points += ($points * $quantity);
        }

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
     * Display points earned on thank you / order confirmation page
     *
     * Hook: woocommerce_thankyou
     *
     * @param int $order_id Order ID
     */
    public static function display_thankyou_points($order_id) {
        if (!$order_id) {
            return;
        }

        // Hide if reward points system is disabled
        if (!\SellSuite\Points::is_points_enabled()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if ($user_id <= 0) {
            return;
        }

        // Get order points from ledger
        global $wpdb;
        $table = $wpdb->prefix . 'sellsuite_points_ledger';

        $order_points = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(points_amount) FROM {$table}
                WHERE order_id = %d AND user_id = %d",
                $order_id,
                $user_id
            )
        );

        $order_points = intval($order_points ?? 0);

        if ($order_points <= 0) {
            return;
        }

        ?>
        <div class="sellsuite-thankyou-points">
            <h3><?php esc_html_e('Reward Points', 'sellsuite'); ?></h3>
            <div class="points-info-box">
                <p class="points-earned">
                    <?php
                    printf(
                        wp_kses_post(__('You have earned <strong>%d Reward Points</strong> from this order!', 'sellsuite')),
                        $order_points
                    );
                    ?>
                </p>
                <p class="points-help">
                    <?php
                    printf(
                        wp_kses_post(__('View your <a href="%s">reward points balance</a> in your account.', 'sellsuite')),
                        esc_url(wc_get_account_endpoint_url('dashboard'))
                    );
                    ?>
                </p>
            </div>
        </div>
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

        // Check if user has available points
        $available_points = \SellSuite\Points::get_available_balance($user_id);
        if ($available_points <= 0) {
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
        $available_points = \SellSuite\Points::get_available_balance($user_id);
        $settings = \SellSuite\Points::get_settings();
        
        // Get order total
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
                'available_points' => intval($available_points),
                'order_total' => $order_total,
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxurl' => admin_url('admin-ajax.php'),
            )
        );
    }
}
