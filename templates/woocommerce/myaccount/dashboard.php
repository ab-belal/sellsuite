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

// Simple modern dashboard template
/** @var WP_User $current_user */
global $current_user;
wp_get_current_user();

$user_id = get_current_user_id();

// Points system fallback (if you have a rewards plugin, replace this retrieval)
$points = intval( get_user_meta( $user_id, 'reward_points', true ) );
if ( $points < 0 ) $points = 0;

// Tier calculation (example)
if ( $points >= 5000 ) {
	$tier = 'Gold';
	$next_target = 0;
} elseif ( $points >= 1000 ) {
	$tier = 'Silver';
	$next_target = 5000 - $points;
} else {
	$tier = 'Bronze';
	$next_target = 1000 - $points;
}

$progress_total = ($tier === 'Gold') ? 100 : min( 100, intval( ($points / ( $tier === 'Silver' ? 5000 : 1000 )) * 100 ) );

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

// Recommended products (simple query: latest 3)
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
			// echo get_avatar( $user_id, 84 ); // echo avatar 
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

	<div class="points-card card">
		<div class="card-head">
			<span class="icon">🏆</span>
			<h3>Rewards</h3>
		</div>
		<div class="points-body">
			<div class="points-large"><?php echo esc_html( $points ); ?></div>
			<div class="points-meta">
				<span class="tier"><?php echo esc_html( $tier ); ?></span>
				<span class="next"><?php echo $next_target > 0 ? esc_html( $next_target . ' to next' ) : 'Top tier'; ?></span>
			</div>
			<div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( $progress_total ); ?>">
				<div class="progress-bar" style="width: <?php echo esc_attr( $progress_total ); ?>%; background: #2ecc71;"></div>
			</div>
		</div>
	</div>

	<div class="quick-actions card">
		<h4>Quick Actions</h4>
		<div class="actions-grid">
			<a class="qa" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>">📦 Orders</a>
			<a class="qa" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>">🏠 Addresses</a>
			<a class="qa" href="#">💚 Wishlist</a>
			<a class="qa" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>">⚙️ Settings</a>
		</div>
	</div>

	<div class="notifications card">
		<h4>Notifications</h4>
		<ul class="notes-list">
			<li>🎉 You earned <strong><?php echo esc_html( min( 100, $points ) ); ?> points</strong> on your last purchase.</li>
			<li>📣 New reward tier available — check your progress.</li>
		</ul>
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

