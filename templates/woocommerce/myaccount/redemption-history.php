<?php
/**
 * Redemption History Page
 * 
 * Shows user's redemption history and details
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id = get_current_user_id();
if ( ! $user_id ) {
	wp_safe_remote_get( wp_login_url( wc_get_account_endpoint_url( 'redemption-history' ) ) );
	exit();
}

$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 10;

$redemptions = \SellSuite\Redeem_Handler::get_user_redemptions( $user_id, $per_page, ( $current_page - 1 ) * $per_page );
$total_redeemed = \SellSuite\Redeem_Handler::get_total_redeemed( $user_id );

// Calculate pagination
global $wpdb;
$total = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}sellsuite_point_redemptions WHERE user_id = %d",
		$user_id
	)
);
$total_pages = ceil( $total / $per_page );

$status_colors = array(
	'pending' => array( 'bg' => '#FFF3CD', 'text' => '#856404' ),
	'completed' => array( 'bg' => '#D4EDDA', 'text' => '#155724' ),
	'refunded' => array( 'bg' => '#F8D7DA', 'text' => '#721C24' ),
	'cancelled' => array( 'bg' => '#E2E3E5', 'text' => '#383D41' ),
);
?>

<div class="woocommerce-MyAccount-content">
	<div style="margin-bottom: 30px;">
		<h2><?php esc_html_e( 'Redemption History', 'sellsuite' ); ?></h2>
		<p style="color: #666; margin-top: 10px;">
			<?php esc_html_e( 'View and manage your point redemptions', 'sellsuite' ); ?>
		</p>
	</div>

	<!-- Stats Overview -->
	<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
		<div style="background: #f5f5f5; padding: 20px; border-radius: 6px; border-left: 4px solid #667eea;">
			<p style="margin: 0; font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600;">Total Redeemed</p>
			<p style="margin: 10px 0 0 0; font-size: 32px; font-weight: 700; color: #667eea;">
				<?php echo intval( $total_redeemed ); ?>
			</p>
		</div>
		<div style="background: #f5f5f5; padding: 20px; border-radius: 6px; border-left: 4px solid #28a745;">
			<p style="margin: 0; font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600;">Completed</p>
			<p style="margin: 10px 0 0 0; font-size: 32px; font-weight: 700; color: #28a745;">
				<?php
				$completed = 0;
				foreach ( $redemptions as $r ) {
					if ( $r->status === 'completed' ) {
						$completed++;
					}
				}
				echo intval( $completed );
				?>
			</p>
		</div>
		<div style="background: #f5f5f5; padding: 20px; border-radius: 6px; border-left: 4px solid #ffc107;">
			<p style="margin: 0; font-size: 12px; color: #999; text-transform: uppercase; font-weight: 600;">Pending</p>
			<p style="margin: 10px 0 0 0; font-size: 32px; font-weight: 700; color: #ffc107;">
				<?php
				$pending = 0;
				foreach ( $redemptions as $r ) {
					if ( $r->status === 'pending' ) {
						$pending++;
					}
				}
				echo intval( $pending );
				?>
			</p>
		</div>
	</div>

	<!-- Redemptions Table -->
	<?php if ( ! empty( $redemptions ) ) : ?>
		<div style="background: white; border: 1px solid #ddd; border-radius: 6px; overflow: hidden;">
			<table style="width: 100%; border-collapse: collapse;">
				<thead>
					<tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
						<th style="padding: 15px; text-align: left; font-weight: 600; font-size: 13px; color: #333;">Order ID</th>
						<th style="padding: 15px; text-align: center; font-weight: 600; font-size: 13px; color: #333;">Points Redeemed</th>
						<th style="padding: 15px; text-align: right; font-weight: 600; font-size: 13px; color: #333;">Discount</th>
						<th style="padding: 15px; text-align: center; font-weight: 600; font-size: 13px; color: #333;">Status</th>
						<th style="padding: 15px; text-align: center; font-weight: 600; font-size: 13px; color: #333;">Date</th>
						<th style="padding: 15px; text-align: center; font-weight: 600; font-size: 13px; color: #333;">Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $redemptions as $redemption ) : 
						$order = wc_get_order( $redemption->order_id );
						$status = $redemption->status ?? 'pending';
						$colors = $status_colors[ $status ] ?? array( 'bg' => '#E2E3E5', 'text' => '#383D41' );
						?>
						<tr style="border-bottom: 1px solid #eee; transition: background-color 0.2s;">
							<td style="padding: 15px; text-align: left;">
								<?php if ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" style="color: #667eea; text-decoration: none; font-weight: 600;">
										#<?php echo esc_html( $order->get_order_number() ); ?>
									</a>
								<?php else : ?>
									<span style="color: #999;">#<?php echo intval( $redemption->order_id ); ?></span>
								<?php endif; ?>
							</td>
							<td style="padding: 15px; text-align: center; font-weight: 600; color: #667eea;">
								<?php echo intval( $redemption->redeemed_points ); ?> pts
							</td>
							<td style="padding: 15px; text-align: right; font-weight: 600;">
								<?php 
								$currency = get_woocommerce_currency_symbol();
								echo wp_kses_post( $currency . number_format( floatval( $redemption->discount_value ), 2 ) );
								?>
							</td>
							<td style="padding: 15px; text-align: center;">
								<span style="background: <?php echo esc_attr( $colors['bg'] ); ?>; color: <?php echo esc_attr( $colors['text'] ); ?>; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; text-transform: capitalize;">
									<?php echo esc_html( $status ); ?>
								</span>
							</td>
							<td style="padding: 15px; text-align: center; font-size: 13px; color: #666;">
								<?php echo esc_html( date_i18n( 'M j, Y', strtotime( $redemption->created_at ) ) ); ?>
							</td>
							<td style="padding: 15px; text-align: center;">
								<?php if ( $order ) : ?>
									<a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="button button-small" style="background: #667eea; color: white; border: none; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px;">
										<?php esc_html_e( 'View Order', 'sellsuite' ); ?>
									</a>
								<?php else : ?>
									<span style="color: #ccc;">—</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div style="margin-top: 30px; display: flex; justify-content: center; gap: 10px;">
				<?php if ( $current_page > 1 ) : ?>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'redemption-history' ) . '?paged=' . ( $current_page - 1 ) ); ?>" class="button" style="border: 1px solid #ddd; background: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #333;">
						← <?php esc_html_e( 'Previous', 'sellsuite' ); ?>
					</a>
				<?php endif; ?>

				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === $current_page ) : ?>
						<span class="page-number current" style="background: #667eea; color: white; padding: 8px 16px; border-radius: 4px; font-weight: 600;">
							<?php echo intval( $i ); ?>
						</span>
					<?php elseif ( $i <= $current_page + 2 && $i >= $current_page - 2 ) : ?>
						<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'redemption-history' ) . '?paged=' . $i ); ?>" class="page-number" style="border: 1px solid #ddd; background: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #333;">
							<?php echo intval( $i ); ?>
						</a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'redemption-history' ) . '?paged=' . ( $current_page + 1 ) ); ?>" class="button" style="border: 1px solid #ddd; background: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; color: #333;">
						<?php esc_html_e( 'Next', 'sellsuite' ); ?> →
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div style="background: #f8f9fa; padding: 40px; border-radius: 6px; text-align: center;">
			<p style="font-size: 16px; color: #666; margin: 0;">
				<?php esc_html_e( 'No redemptions yet. Start earning points and redeem them for discounts!', 'sellsuite' ); ?>
			</p>
			<a href="<?php echo esc_url( home_url( '/shop' ) ); ?>" class="button button-primary" style="margin-top: 20px; background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 4px; text-decoration: none;">
				<?php esc_html_e( 'Start Shopping', 'sellsuite' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>
