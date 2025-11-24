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

        // REST API for products-info (DataTables server-side)
        add_action( 'rest_api_init', array( $this, 'register_products_info_rest_route' ) );

        // Enqueue DataTables & frontend script on products-info endpoint
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_products_info_assets' ) );
        
    }

    /**
     * Register REST route for DataTables server-side product listing.
     * Endpoint: /wp-json/sellsuite/v1/products-info
     *
     * Accepts: page, length, search (or DataTables standard params)
     * Returns JSON in DataTables server-side format: draw, recordsTotal, recordsFiltered, data[]
     *
     * Security: permission_callback ensures only users with 'product_viewer' capability can access.
     * Performance: consider caching the counts and results for unauthenticated or expensive queries.
     */
    public function register_products_info_rest_route() {
        register_rest_route( 'sellsuite/v1', '/products-info', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'products_info_rest_handler' ),
            'permission_callback' => array( $this, 'products_info_rest_permissions_check' ),
        ) );
    }

    /**
     * Permission callback for products-info route.
     * Only allow users with the 'product_viewer' capability.
     */
    public function products_info_rest_permissions_check( \WP_REST_Request $request ) {
        // Must be logged in and have capability 'product_viewer'
        if ( ! is_user_logged_in() ) {
            return new \WP_Error( 'rest_forbidden', __( 'You must be logged in to view this resource.', 'sellsuite' ), array( 'status' => 401 ) );
        }

        if ( ! current_user_can( 'product_viewer' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Insufficient permissions to view products.', 'sellsuite' ), array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * REST handler: returns products for DataTables server-side processing.
     */
    public function products_info_rest_handler( \WP_REST_Request $request ) {
        global $wpdb;

        // DataTables uses draw, start, length, search[value]
        $params = $request->get_query_params();

        // Support both DataTables and simple page/length/search
        $draw = isset( $params['draw'] ) ? intval( $params['draw'] ) : 0;
        $length = isset( $params['length'] ) ? intval( $params['length'] ) : ( isset( $params['length'] ) ? intval( $params['length'] ) : 10 );
        if ( $length <= 0 ) $length = 10;

        // DataTables sends 'start' (offset). Convert to page (1-based)
        $start = isset( $params['start'] ) ? intval( $params['start'] ) : 0;
        $page = 1;
        if ( isset( $params['page'] ) ) {
            $page = max( 1, intval( $params['page'] ) );
        } else {
            $page = floor( $start / $length ) + 1;
        }

        // Search term
        $search = '';
        if ( isset( $params['search'] ) && is_string( $params['search'] ) ) {
            $search = sanitize_text_field( $params['search'] );
        } elseif ( isset( $params['search']['value'] ) ) {
            $search = sanitize_text_field( $params['search']['value'] );
        }

        // Basic args for WP_Query
        $query_args = array(
            'post_type'      => 'product',
            'posts_per_page' => $length,
            'paged'          => $page,
            'post_status'    => 'publish',
            's'              => $search ? $search : '',
            'fields'         => 'ids', // we'll fetch minimal data then build output. improves performance.
        );

        // Use WP_Query (safer than custom SQL unless carefully prepared)
        $q = new \WP_Query( $query_args );

        $product_ids = $q->posts;

        // Total records (all published products) - fast via wp_count_posts
        $count_posts = wp_count_posts( 'product' );
        $recordsTotal = isset( $count_posts->publish ) ? intval( $count_posts->publish ) : 0;

        // recordsFiltered = total matching search
        $recordsFiltered = $q->found_posts;

        // Build data rows. Keep minimal fields to reduce payload.
        $data = array();
        if ( ! empty( $product_ids ) ) {
            foreach ( $product_ids as $pid ) {
                $product = wc_get_product( $pid );
                if ( ! $product ) continue;

                $row = array();
                $row['id'] = $pid;
                $row['sku'] = $product->get_sku();
                $row['title'] = $product->get_name();
                $row['price'] = wc_price( $product->get_price() );
                $row['stock_status'] = $product->get_stock_status();
                $row['link'] = get_permalink( $pid );

                $data[] = $row;
            }
        }

        // Return in DataTables server-side format
        $response = array(
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        );

        // Note: Consider caching the $recordsTotal and $recordsFiltered values, and caching product lists
        // for common queries (e.g. empty search, first page). Use transient caching with appropriate invalidation
        // when products are added/updated. Avoid caching per-user results unless necessary.

        return rest_ensure_response( $response );
    }

    /**
     * Enqueue DataTables and frontend JS only on the my-account 'products-info' endpoint page.
     */
    public function enqueue_products_info_assets() {
        // Only load assets on the WooCommerce 'products-info' endpoint page
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        // is_wc_endpoint_url is the preferred check but sometimes query vars aren't available
        // during certain hooks. Use a robust fallback that checks query var or request URI.
        $on_endpoint = false;
        if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'products-info' ) ) {
            $on_endpoint = true;
        } else {
            // Fallback: check query var directly
            $qv = get_query_var( 'products-info', '' );
            if ( $qv !== '' && $qv !== false ) {
                $on_endpoint = true;
            } else {
                // Last resort: check the REQUEST_URI for the endpoint slug
                if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( wp_unslash( $_SERVER['REQUEST_URI'] ), 'products-info' ) ) {
                    $on_endpoint = true;
                }
            }
        }

        if ( ! $on_endpoint ) {
            return;
        }

        // DataTables (CDN) - CSS
        wp_enqueue_style( 'sellsuite-datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', array(), '1.13.6' );

        // jQuery is a dependency of DataTables
        wp_enqueue_script( 'sellsuite-datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array( 'jquery' ), '1.13.6', true );

        // Our frontend script
        $asset_url = SELLSUITE_PLUGIN_URL . 'assets/js/products-info.js';
        wp_enqueue_script( 'sellsuite-products-info', $asset_url, array( 'jquery', 'sellsuite-datatables-js' ), filemtime( dirname( __DIR__ ) . '/assets/js/products-info.js' ), true );

        // Localize REST URL, nonce, and capability flag
        $rest_url = esc_url_raw( rest_url( 'sellsuite/v1/products-info' ) );
        $nonce = wp_create_nonce( 'wp_rest' );
        $can_view = current_user_can( 'product_viewer' );

        wp_localize_script( 'sellsuite-products-info', 'sellsuiteProductsInfo', array(
            'restUrl' => $rest_url,
            'nonce'   => $nonce,
            'canView' => $can_view,
        ) );
        
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
