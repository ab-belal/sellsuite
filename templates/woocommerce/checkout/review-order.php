<?php
/**
 * Review order table
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/review-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 5.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table">
	<thead>
		<tr>
			<th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
			<th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				?>
				<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<td class="product-name">
						<div class="checkout-product-image">
							<?php 
								$thumbnail = $_product->get_image( 'thumbnail' );
								echo $thumbnail;

								echo '<span class="checkout-product-name">';
									echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ) . '&nbsp;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

									echo apply_filters( 'woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf( '&times;&nbsp;%s', $cart_item['quantity'] ) . '</strong>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo '</span>';
							?>
						</div>

						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						
						<!-- SellSuite: Display points earned for this item -->
						<?php 
						if ( class_exists( 'SellSuite_Frontend_Display' ) && class_exists( 'SellSuite\Product_Meta' ) ) {
							echo '<div class="checkout-item-points">';
							$product_id = $_product->get_id();
							$quantity = $cart_item['quantity'];
							$points_per_unit = \SellSuite\Product_Meta::get_product_points( $product_id );
							$total_item_points = $points_per_unit * $quantity;
							if ( $total_item_points > 0 ) {
								printf(
									'<small><i class="fas fa-star"></i> ' . esc_html__( '%d points for this item', 'sellsuite' ) . '</small>',
									intval( $total_item_points )
								);
							}
							echo '</div>';
						}
						?>
					</td>
					<td class="product-total">
						<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	
	<tfoot>
		<tr class="cart-subtotal">
			<th><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>
		
		<?php foreach ( WC()->cart->get_fees() as $fee ) : 
			// Get fee discount value for displaying points info
			$fee_amount = floatval($fee->total);
			$fee_label = esc_html($fee->name);
			
			// Check if this is a point redemption fee and get points info
			$is_point_fee = stripos($fee->name, 'point') !== false;
			if ($is_point_fee && is_user_logged_in()) {
				$user_id = get_current_user_id();
				$pending_redemption = get_user_meta($user_id, '_pending_point_redemption', true);
				if (!empty($pending_redemption)) {
					$redeemed_points = intval($pending_redemption['redeemed_points'] ?? 0);
					$fee_label .= sprintf(
						'<br/><small style="display:block; color:#999; line-height:1.2; font-weight:normal;">%s</small>',
						sprintf(
							/* translators: %d = redeemed points */
							esc_html__( 'For %d points', 'sellsuite' ),
							intval( $redeemed_points )
						)
					);

				}
			}
			?>
			<tr class="fee sellsuite-point-redemption-fee">
				<th><?php echo wp_kses_post($fee_label); ?></th>
				<td>
					<?php wc_cart_totals_fee_html( $fee ); ?>
					
					<button type="button" class="sellsuite-cancel-redemption-btn" title="Cancel Point Discount" style="margin-left: 10px; background: none; border: none; color: #dc3545; cursor: pointer; padding: 0; font-size: 16px;">
						<span class="dashicons dashicons-no" style="width: auto; height: auto; font-size: 16px;"></span>
						<span class="dashicons dashicons-update" style="width: auto; height: auto; font-size: 16px; display: none;"></span>
					</button>
				</td>
			</tr>
		<?php endforeach; ?>

		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>

		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>

			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>

			<?php wc_cart_totals_shipping_html(); ?>

			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>

		<?php endif; ?>

		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<th><?php echo esc_html( $tax->label ); ?></th>
						<td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total">
					<th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th>
					<td><?php wc_cart_totals_taxes_total_html(); ?></td>
				</tr>
			<?php endif; ?>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<tr class="order-total">
			<th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</tfoot>
</table>
