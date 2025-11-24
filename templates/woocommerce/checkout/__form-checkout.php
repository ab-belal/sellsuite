<?php
/**
 * SellSuite plugin override for WooCommerce checkout form
 * Copy of WooCommerce's `checkout/form-checkout.php` (trimmed to main hooks)
 * Modified: adds a small marker so it's easy to confirm the plugin override is active.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Marker: visible in HTML so you can confirm this template is used by WooCommerce
echo "<!-- SellSuite override: templates/woocommerce/checkout/form-checkout.php -->\n";

// Ensure checkout object is available
/** @var WC_Checkout $checkout */
$checkout = WC()->checkout();

wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

// Pull per-field errors from WC session (set during validation) so we can show inline messages
$sellsuite_field_errors = array();
if ( function_exists( 'WC' ) && isset( WC()->session ) ) {
    $sellsuite_field_errors = WC()->session->get( 'sellsuite_field_errors', array() );
}

// If checkout registration is disabled and user is not logged in, the user cannot checkout
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<div class="sellsuite-checkout">
    <div class="sellsuite-checkout-container">
        <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

            <div class="sellsuite-checkout-left">
                <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
                
                <div class="col2-set" id="customer_details">
                    <div class="col-1">
                        <?php do_action( 'woocommerce_checkout_billing' ); ?>
                    </div>

                    <div class="col-2">
                        <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                    </div>
                </div>

                <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
            </div>

            <div class="sellsuite-checkout-right">
                <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
                
                <div id="order_review" class="woocommerce-checkout-review-order">
                    <h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
                    <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                </div>

                <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
            </div>

        </form>
    </div>
</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout );
// Clear session-held field errors after rendering so they don't persist on next page load
if ( function_exists( 'WC' ) && isset( WC()->session ) ) {
    WC()->session->__unset( 'sellsuite_field_errors' );
}
