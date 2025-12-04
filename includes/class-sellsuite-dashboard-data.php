<?php
namespace SellSuite;

/**
 * User Dashboard Data Manager
 *
 * Handles all data retrieval and processing for the user dashboard template.
 * Centralizes queries, calculations, and data formatting.
 */
class User_Dashboard_Data {

	/**
	 * Get points summary data for the current user
	 *
	 * @param int $user_id User ID
	 * @return array Points summary with earned, available, and pending
	 */
	public static function get_points_summary( $user_id ) {
		$earned_points = Points::get_earned_points( $user_id );
		$available_balance = Points::get_available_balance( $user_id );
		$pending_points = self::get_pending_points( $user_id );

		return array(
			'earned' => intval( $earned_points ),
			'available' => intval( $available_balance ),
			'pending' => intval( $pending_points ),
		);
	}

	/**
	 * Get pending points from valid/active orders only (excluding cancelled and refunded)
	 *
	 * @param int $user_id User ID
	 * @return int Total pending points
	 */
	public static function get_pending_points( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sellsuite_points_ledger';

		$pending_points = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(points_amount), 0) FROM {$table}
				WHERE user_id = %d AND status = 'pending'
				AND action_type IN ('order_placement', 'purchase')",
				$user_id
			)
		);

		return intval( $pending_points );
	}

	/**
	 * Get paginated points history for the current user
	 *
	 * @param int $user_id User ID
	 * @param int $page Current page number (1-based)
	 * @param int $per_page Items per page
	 * @return array Containing 'entries', 'total_pages', 'current_page', 'total_entries'
	 */
	public static function get_history_paginated( $user_id, $page = 1, $per_page = 5 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sellsuite_points_ledger';

		$current_page = max( 1, intval( $page ) );
		$offset = ( $current_page - 1 ) * $per_page;

		// Get total count
		$total_entries = intval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table}
					WHERE user_id = %d AND action_type IN ('order_placement', 'purchase')",
					$user_id
				)
			)
		);

		// Get paginated entries
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d AND action_type IN ('order_placement', 'purchase')
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$per_page,
				$offset
			)
		);

		$total_pages = ceil( $total_entries / $per_page );

		return array(
			'entries' => $entries,
			'total_entries' => $total_entries,
			'total_pages' => $total_pages,
			'current_page' => $current_page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Format a single history entry with all necessary data
	 *
	 * @param object $entry Database entry from points ledger
	 * @return array Formatted entry data
	 */
	public static function format_history_entry( $entry ) {
		$order_id = intval( $entry->order_id );
		$product_id = intval( $entry->product_id );
		$points = intval( $entry->points_amount );
		$status = sanitize_text_field( $entry->status );
		$notes = sanitize_text_field( $entry->notes );
		$description = sanitize_text_field( $entry->description );

		// Get order and product details
		$order = wc_get_order( $order_id );
		$product = wc_get_product( $product_id );

		$product_name = $product ? $product->get_name() : __( 'Product not found', 'sellsuite' );
		$product_price = $product ? floatval( $product->get_price() ) : 0;
		$order_total = $order ? floatval( $order->get_total() ) : 0;

		// Find quantity from order items
		$quantity = 1;
		if ( $order && $product_id ) {
			foreach ( $order->get_items() as $item ) {
				if ( intval( $item->get_product_id() ) === $product_id ) {
					$quantity = intval( $item->get_quantity() );
					break;
				}
			}
		}

		// Get status display info
		$status_info = self::get_status_display_info( $status );

		return array(
			'order_id' => $order_id,
			'order' => $order,
			'order_number' => $order ? $order->get_order_number() : $order_id,
			'order_view_url' => $order ? $order->get_view_order_url() : '',
			'product_id' => $product_id,
			'product' => $product,
			'product_name' => $product_name,
			'product_price' => $product_price,
			'product_url' => $product ? $product->get_permalink() : '',
			'quantity' => $quantity,
			'total_price' => $product_price * $quantity,
			'points' => $points,
			'status' => $status,
			'status_text' => $status_info['text'],
			'status_color' => $status_info['color'],
			'status_bg' => $status_info['bg'],
			'notes' => $notes,
			'description' => $description,
		);
	}

	/**
	 * Get status display information (color, text, background)
	 *
	 * @param string $status Point status
	 * @return array Display info with 'text', 'color', 'bg'
	 */
	public static function get_status_display_info( $status ) {
		$status_map = array(
			'earned' => array(
				'text' => __( 'Earned', 'sellsuite' ),
				'color' => '#28a745',
				'bg' => '#d4edda',
			),
			'pending' => array(
				'text' => __( '⏳ Pending', 'sellsuite' ),
				'color' => '#ffc107',
				'bg' => '#fff3cd',
			),
			'redeemed' => array(
				'text' => __( 'Redeemed', 'sellsuite' ),
				'color' => '#6c757d',
				'bg' => '#e2e3e5',
			),
			'expired' => array(
				'text' => __( 'Expired', 'sellsuite' ),
				'color' => '#dc3545',
				'bg' => '#f8d7da',
			),
			'refunded' => array(
				'text' => __( 'Refunded', 'sellsuite' ),
				'color' => '#fd7e14',
				'bg' => '#ffe5cc',
			),
			'cancelled' => array(
				'text' => __( 'Cancelled', 'sellsuite' ),
				'color' => '#dc3545',
				'bg' => '#f8d7da',
			),
		);

		// Return matched status or default to 'earned'
		return isset( $status_map[ $status ] ) ? $status_map[ $status ] : $status_map['earned'];
	}

	/**
	 * Get pagination links HTML
	 *
	 * @param int $current_page Current page number
	 * @param int $total_pages Total number of pages
	 * @return string HTML for pagination links
	 */
	public static function get_pagination_html( $current_page, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<div style="margin-top: 20px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap;">
			<?php
			// Previous button
			if ( $current_page > 1 ) :
				$prev_url = add_query_arg( 'points_page', $current_page - 1 );
				?>
				<a href="<?php echo esc_url( $prev_url ); ?>" style="padding: 8px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333;">
					← Prev
				</a>
			<?php endif; ?>

			<?php
			// Page numbers
			$start_page = max( 1, $current_page - 2 );
			$end_page = min( $total_pages, $current_page + 2 );

			if ( $start_page > 1 ) :
				?>
				<a href="<?php echo esc_url( add_query_arg( 'points_page', 1 ) ); ?>" style="padding: 8px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333;">
					1
				</a>
				<?php if ( $start_page > 2 ) : ?>
					<span style="padding: 8px 12px;">...</span>
				<?php endif; ?>
			<?php endif; ?>

			<?php for ( $i = $start_page; $i <= $end_page; $i++ ) : ?>
				<?php if ( $i === $current_page ) : ?>
					<span style="padding: 8px 12px; background: #667eea; color: white; border-radius: 4px; font-weight: 600;">
						<?php echo intval( $i ); ?>
					</span>
				<?php else : ?>
					<a href="<?php echo esc_url( add_query_arg( 'points_page', $i ) ); ?>" style="padding: 8px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333;">
						<?php echo intval( $i ); ?>
					</a>
				<?php endif; ?>
			<?php endfor; ?>

			<?php if ( $end_page < $total_pages ) : ?>
				<?php if ( $end_page < $total_pages - 1 ) : ?>
					<span style="padding: 8px 12px;">...</span>
				<?php endif; ?>
				<a href="<?php echo esc_url( add_query_arg( 'points_page', $total_pages ) ); ?>" style="padding: 8px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333;">
					<?php echo intval( $total_pages ); ?>
				</a>
			<?php endif; ?>

			<?php
			// Next button
			if ( $current_page < $total_pages ) :
				$next_url = add_query_arg( 'points_page', $current_page + 1 );
				?>
				<a href="<?php echo esc_url( $next_url ); ?>" style="padding: 8px 12px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333;">
					Next →
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
