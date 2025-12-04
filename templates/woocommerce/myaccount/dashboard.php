<?php
/**
 * My Account Dashboard
 *
 * Shows the first intro screen on the account dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/dashboard.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Template variables setup
/** @var WP_User $current_user */
global $current_user;
wp_get_current_user();

$user_id = get_current_user_id();

// Recent orders
$recent_orders = array();
if ( function_exists( 'wc_get_orders' ) ) {
	$recent_orders = wc_get_orders( array(
		'customer' => $user_id,
		'limit'    => 5,
		'orderby'  => 'date',
		'order'    => 'DESC',
	) );
}

// Recommended products
$recommended = array();
if ( function_exists( 'wc_get_products' ) ) {
	$recommended = wc_get_products( array(
		'limit' => 3,
		'status' => 'publish',
	) );
}

?>


<aside class="account-sidebar dashboard-from-plugin">
	<div class="account-welcome card">
		<div class="avatar">
			<?php 
			$avatar = get_user_meta( $user_id, 'profile_picture', true );
			$avatar_url = $avatar ? $avatar : 'https://yourwebsite.com/default-avatar.png';
			echo '<div class="customer-avatar"><img src="' . esc_url( $avatar_url ) . '" alt="Profile Picture" width="80" height="80" style="border-radius:50%;"></div>';
			?>
		</div>
		<div class="welcome-text">
			<h2>Hello, <?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?></h2>
			<p class="muted">Welcome back to your account</p>
		</div>
	</div>

	<!-- SellSuite Rewards Points Section -->
	<?php if ( class_exists( 'SellSuite\Points' ) && \SellSuite\Points::is_enabled() ) : ?>
		<div class="rewards-points-summary" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
			<h4 style="margin-bottom: 15px; font-size: 14px; font-weight: 600; color: #333;">Reward Points</h4>
			
			<div class="points-stats" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
			<?php 
			// Get processed points summary data - all calculations handled in User_Dashboard_Data class
			$points_summary = \SellSuite\User_Dashboard_Data::get_points_summary( $user_id );
			?>				<!-- Total Earned -->
				<div class="point-stat-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border-radius: 6px; text-align: center;">
					<p style="margin: 0; font-size: 12px; opacity: 0.9;">Total Earned</p>
					<p style="margin: 5px 0 0 0; font-size: 20px; font-weight: 700;">
						<i class="fas fa-star" style="margin-right: 5px;"></i><?php echo intval( $points_summary['earned'] ); ?>
					</p>
				</div>

				<!-- Available Balance -->
				<div class="point-stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 12px; border-radius: 6px; text-align: center;">
					<p style="margin: 0; font-size: 12px; opacity: 0.9;">Available</p>
					<p style="margin: 5px 0 0 0; font-size: 20px; font-weight: 700;">
						<i class="fas fa-coins" style="margin-right: 5px;"></i><?php echo intval( $points_summary['available'] ); ?>
					</p>
				</div>

				<?php if ( $points_summary['pending'] > 0 ) : ?>
					<!-- Pending Points -->
					<div class="point-stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #333; padding: 12px; border-radius: 6px; text-align: center;">
						<p style="margin: 0; font-size: 12px; opacity: 0.9;">⏳ Pending</p>
						<p style="margin: 5px 0 0 0; font-size: 20px; font-weight: 700;">
							<?php echo intval( $points_summary['pending'] ); ?>
						</p>
					</div>

					<div class="point-pending-info" style="background: #fef3cd; color: #856404; padding: 10px; border-radius: 4px; border: 1px solid #ffeaa7; font-size: 12px; text-align: center; grid-column: 1 / -1;">
						<p style="margin: 0;"><strong>💡</strong> <?php esc_html_e( 'Your pending points will be confirmed when your order is completed.', 'sellsuite' ); ?></p>
					</div>
				<?php endif; ?>
			</div>

		</div>
	<?php endif; ?>
	

	<!-- SellSuite Points History Table -->
	<?php if ( class_exists( 'SellSuite\Points' ) && \SellSuite\Points::is_enabled() ) : ?>
		<div class="points-history-card card">
			<div class="card-head">
				<h3>Points Earning History</h3>
			</div>
			<div class="card-body">
			<?php
			// Get current page from URL
			$current_page = isset( $_GET['points_page'] ) ? max( 1, intval( $_GET['points_page'] ) ) : 1;
			
			// Retrieve paginated history data - all queries and pagination handled in User_Dashboard_Data class
			$history_data = \SellSuite\User_Dashboard_Data::get_history_paginated( $user_id, $current_page, 5 );
			$history = $history_data['entries'];
			$total_pages = $history_data['total_pages'];
			?>				<?php if ( ! empty( $history ) ) : ?>
					<div style="overflow-x: auto;">
						<table style="width: 100%; border-collapse: collapse; font-size: 14px;">
							<thead>
								<tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
									<th style="padding: 10px; text-align: left;">Order ID</th>
									<th style="padding: 10px; text-align: left;">Product</th>
									<th style="padding: 10px; text-align: right;">Total</th>
									<th style="padding: 10px; text-align: center;">Points</th>
									<th style="padding: 10px; text-align: center;">Status</th>
									<th style="padding: 10px; text-align: center;">Note</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ( $history as $entry ) : 
								// Format entry data - all calculations and formatting handled in User_Dashboard_Data class
								$formatted = \SellSuite\User_Dashboard_Data::format_history_entry( $entry );
								?>
									<tr style="border-bottom: 1px solid #eee; transition: background-color 0.2s;">
										<td style="padding: 10px; text-align: left;">
											<?php if ( $formatted['order'] ) : ?>
												<a href="<?php echo esc_url( $formatted['order_view_url'] ); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
													#<?php echo esc_html( $formatted['order_number'] ); ?>
												</a>
											<?php else : ?>
												<span style="color: #999;">#<?php echo intval( $formatted['order_id'] ); ?></span>
											<?php endif; ?>
										</td>
										<td style="padding: 10px; text-align: left;">
											<?php if ( $formatted['product'] ) : ?>
												<a href="<?php echo esc_url( $formatted['product_url'] ); ?>" style="color: #333; text-decoration: none;">
													<?php echo esc_html( $formatted['product_name'] ); ?>
												</a>
												<br/>
												<?php echo intval( $formatted['quantity'] ) . ' × ' . wc_price( $formatted['product_price'], array( 'echo' => false ) ); ?>
											<?php else : ?>
												<span style="color: #999;"><?php echo esc_html( $formatted['product_name'] ); ?></span>
											<?php endif; ?>
										</td>
										<td style="padding: 10px; text-align: right; font-weight: 600;">
											<?php echo wp_kses_post( wc_price( $formatted['total_price'] ) ); ?>
										</td>
										<td style="padding: 10px; text-align: center; font-weight: 700; color: #667eea;">
											<i class="fas fa-star" style="margin-right: 3px;"></i><?php echo intval( $formatted['points'] ); ?>
										</td>
										<td style="padding: 10px; text-align: center;">
											<span style="background: <?php echo esc_attr( $formatted['status_bg'] ); ?>; color: <?php echo esc_attr( $formatted['status_color'] ); ?>; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; display: inline-block;">
												<?php echo esc_html( $formatted['status_text'] ); ?>
											</span>
										</td>
										<td style="padding: 10px; text-align: left; font-size: 12px; color: #666;">
											<?php 
											if ( $formatted['notes'] ) {
												echo esc_html( substr( $formatted['notes'], 0, 50 ) ) . ( strlen( $formatted['notes'] ) > 50 ? '...' : '' );
											} elseif ( $formatted['description'] ) {
												echo esc_html( substr( $formatted['description'], 0, 50 ) ) . ( strlen( $formatted['description'] ) > 50 ? '...' : '' );
											} else {
												echo '<span style="color: #ccc;">—</span>';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					
				<!-- Pagination HTML - generated from User_Dashboard_Data class -->
				<?php 
				$pagination_html = \SellSuite\User_Dashboard_Data::get_pagination_html( $history_data['current_page'], $total_pages );
				if ( $pagination_html ) {
					echo wp_kses_post( $pagination_html );
				}
				?>				<?php else : ?>
					<p class="muted" style="text-align: center; padding: 20px;">
						<?php esc_html_e( 'No points history yet. Start earning by placing an order!', 'sellsuite' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="quick-actions card">
		<h4>Quick Actions</h4>
		<div class="actions-grid">
			<a class="qa" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">📦 Orders</a>
			<a class="qa" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>">🏠 Addresses</a>
			<a class="qa" href="#">💚 Wishlist</a>
			<a class="qa" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>">⚙️ Settings</a>
		</div>
	</div>
</aside>

<main class="account-main">
	<div class="card recent-orders">
		<div class="card-head">
			<h3>Recent Orders</h3>
			<a class="view-all" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">View all</a>
		</div>
		<div class="card-body">
			<?php if ( ! empty( $recent_orders ) ) : ?>
				<table class="orders-table">
					<thead>
						<tr>
							<th>Order</th>
							<th>Status</th>
							<th>Total</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent_orders as $order ) : /** @var WC_Order $order */ ?>
							<tr>
								<td>#<?php echo esc_html( $order->get_order_number() ); ?></td>
								<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
								<td><a class="btn btn-sm" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">View</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="muted">You have no recent orders.</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="card recommended-products">
		<div class="card-head">
			<h3>Recommended for you</h3>
		</div>
		<div class="card-body products-grid">
			<?php if ( ! empty( $recommended ) ) : ?>
				<?php foreach ( $recommended as $prod ) : // WC_Product
					$pid = $prod->get_id();
					$permalink = get_permalink( $pid );
					$thumb = get_the_post_thumbnail( $pid, 'medium' );
					$title = $prod->get_name();
					$price = $prod->get_price_html();
				?>
					<div class="product-card">
						<div class="p-thumb"><?php echo $thumb ?: '<div class="thumb-placeholder">🛍️</div>'; ?></div>
						<div class="p-info">
							<h4 class="p-title"><?php echo esc_html( $title ); ?></h4>
							<div class="p-price"><?php echo wp_kses_post( $price ); ?></div>
						</div>
						<div class="p-actions">
							<a class="btn btn-outline" href="<?php echo esc_url( $permalink ); ?>">View</a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="muted">No recommendations yet — browse our store to get suggestions.</p>
			<?php endif; ?>
		</div>
	</div>

</main>

<?php
/**
 * My Account dashboard action points for compatibility
 */
do_action( 'woocommerce_account_dashboard' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */

