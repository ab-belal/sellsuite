<?php
/**
 * Plugin Name: SellSuite
 * Plugin URI: https://example.com/sellsuite
 * Description: A modular WooCommerce extension that enhances store management with customer loyalty points, product management, and customer dashboard.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sellsuite
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('SELLSUITE_VERSION', '1.0.0');
define('SELLSUITE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SELLSUITE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SELLSUITE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_sellsuite() {
    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-activator.php';
    SellSuite\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_sellsuite() {
    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-deactivator.php';
    SellSuite\Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_sellsuite');
register_deactivation_hook(__FILE__, 'deactivate_sellsuite');

/**
 * Check if WooCommerce is active
 */
function sellsuite_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'sellsuite_woocommerce_missing_notice');
        deactivate_plugins(SELLSUITE_PLUGIN_BASENAME);
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function sellsuite_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('SellSuite requires WooCommerce to be installed and active. The plugin has been deactivated.', 'sellsuite'); ?></p>
    </div>
    <?php
}

/**
 * Begin execution of the plugin.
 */
function run_sellsuite() {
    if (!sellsuite_check_woocommerce()) {
        return;
    }

    require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-loader.php';
    
    $plugin = new SellSuite\Loader();
    $plugin->run();
}

add_action('plugins_loaded', 'run_sellsuite');

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});


add_action('wp_ajax_sellsuite_load_products', 'sellsuite_load_products');
add_action('wp_ajax_nopriv_sellsuite_load_products', 'sellsuite_load_products');


function sellsuite_load_products() {

    $paged   = max(1, intval($_POST['paged'] ?? 1));
    $search  = sanitize_text_field($_POST['s'] ?? '');
    $cat     = intval($_POST['cat'] ?? 0);
    $stock   = sanitize_text_field($_POST['stock'] ?? '');
    $brand   = intval($_POST['brand'] ?? 0);
    $per_raw = sanitize_text_field($_POST['per_page'] ?? '10');
    $per_page = ($per_raw === 'all') ? -1 : max(1, intval($per_raw));


    $tax_query = [];

    if ($cat) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $cat
        ];
    }

    if ($brand) {
        $tax_query[] = [
            'taxonomy' => 'product_brand',
            'field'    => 'term_id',
            'terms'    => $brand
        ];
    }

    $meta_query = [];

    if ($stock) {
        $meta_query[] = [
            'key'   => '_stock_status',
            'value' => $stock,
        ];
    }

    if ($search !== "") {

        // If the search is a number → treat as Product ID search
        if (ctype_digit($search)) {
            $args['post__in'] = [ intval($search) ];

        } else {
            // Normal WP search (title, content)
            $args['s'] = $search;
        }

        // Also allow search by SKU
        $meta_query[] = [
            'key'     => '_sku',
            'value'   => $search,
            'compare' => 'LIKE',
        ];
    }


    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        's'              => $search,
        'tax_query'      => $tax_query,
        'meta_query'     => $meta_query,
        'posts_per_page' => $per_page,
        'paged'          => $paged,
    ];


    if (!empty($sort)) {
        switch ($sort) {

            case "id_asc":
                $args['orderby'] = 'ID';
                $args['order']   = 'ASC';
                break;

            case "id_desc":
                $args['orderby'] = 'ID';
                $args['order']   = 'DESC';
                break;

            case "name_asc":
                $args['orderby'] = 'title';
                $args['order']   = 'ASC';
                break;

            case "name_desc":
                $args['orderby'] = 'title';
                $args['order']   = 'DESC';
                break;

            case "price_asc":
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;

            case "price_desc":
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
        }
    }


    $q = new WP_Query($args);

    ob_start();
?>
    <table class="shop_table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Name', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'SKU', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Price', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Stock', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Categories', 'sellsuite' ); ?></th>
            </tr>
        </thead>

        <tbody>
        <?php if ($q->have_posts()) : 
            while ($q->have_posts()) : $q->the_post();
                $pid = get_the_ID();
                $product = wc_get_product($pid);

                $product_name = $product->get_name();
                $sku = $product->get_sku();
                $regular_price = '';
                $sale_price = '';
                if ( $product->is_type( 'variable' ) ) {
                    $regular_price = $product->get_variation_regular_price( 'min' );
                    $sale_price    = $product->get_variation_sale_price( 'min' );
                } else {
                    $regular_price = $product->get_regular_price();
                    $sale_price    = $product->get_sale_price();
                }

                // Fallback: if no regular price, use get_price()
                if ( empty( $regular_price ) && $product->get_price() ) {
                    $regular_price = $product->get_price();
                }

                if ( $sale_price && $sale_price !== $regular_price ) {
                    $price_html = '<span class="sellsuite-regular-price" style="text-decoration:line-through;color:#6b7280;margin-right:6px;">' . wc_price( $regular_price ) . '</span>';
                    $price_html .= '<span class="sellsuite-sale-price" style="color:#0b6646;font-weight:600;">' . wc_price( $sale_price ) . '</span>';
                } else {
                    $price_html = $regular_price ? wc_price( $regular_price ) : '&ndash;';
                }

                // Stock status: prefer a readable label and include quantity when managing stock.
                $stock_status = '';
                $stock_qty = $product->get_stock_quantity();
                if ( $product->managing_stock() ) {
                    // When managing stock, include the quantity if available, otherwise show stock HTML
                    if ( null !== $stock_qty ) {
                        $stock_label = wc_get_stock_html( $product ); // includes HTML with class
                        $stock_status = sprintf( '%s', wp_kses_post( $stock_label ));
                    } else {
                        $stock_status = wp_kses_post( wc_get_stock_html( $product ) );
                    }
                } else {
                    // Not managing stock: map known statuses to readable labels
                    $status = $product->get_stock_status(); // 'instock', 'outofstock', 'onbackorder'
                    $status_map = array(
                        'instock'     => __( 'In stock', 'sellsuite' ),
                        'outofstock'  => __( 'Out of stock', 'sellsuite' ),
                        'onbackorder' => __( 'On backorder', 'sellsuite' ),
                    );
                    if ( isset( $status_map[ $status ] ) ) {
                        $stock_status = esc_html( $status_map[ $status ] );
                    } else {
                        $stock_status = esc_html( $status );
                    }
                }

                $cats = wc_get_product_category_list( $pid, ', ' );
            ?>

            <tr>
                <td data-title="<?php esc_attr_e( 'ID', 'sellsuite' ); ?>"><?php echo esc_html( $pid ); ?></td>
                <td data-title="<?php esc_attr_e( 'Name', 'sellsuite' ); ?>">
                    <div class="sellsuite-product-cell" style="display:flex;align-items:center;gap:10px;">
                        <div class="sellsuite-product-thumb" style="width:60px;height:60px;flex:0 0 60px;overflow:hidden;border-radius:6px;background:#f6f7f9;display:flex;align-items:center;justify-content:center;">
                            <?php
                            // Thumbnail 60x60. Fallback to placeholder if no image.
                            $thumb = get_the_post_thumbnail( $pid, array(60,60), array( 'loading' => 'lazy', 'alt' => get_the_title( $pid ) ) );
                            if ( $thumb ) {
                                echo $thumb;
                            } else {
                                // Simple svg placeholder
                                echo '<svg width="60" height="60" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="24" height="24" fill="#eef2f6"/><path d="M5 16l3-4 4 5 5-7 2 3v4H5z" fill="#d1dbe3"/></svg>';
                            }
                            ?>
                        </div>
                        <div class="sellsuite-product-title">
                            <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" style="color:inherit;text-decoration:none;">
                                <?php echo $product_name; ?>
                            </a>
                        </div>
                    </div>
                </td>
                <td data-title="<?php esc_attr_e( 'SKU', 'sellsuite' ); ?>"><?php echo esc_html( $sku ); ?></td>
                <td data-title="<?php esc_attr_e( 'Price', 'sellsuite' ); ?>"><?php echo wp_kses_post( $price_html ); ?></td>
                <td data-title="<?php esc_attr_e( 'Stock', 'sellsuite' ); ?>"><?php echo wp_kses_post( $stock_status ); ?></td>
                <td data-title="<?php esc_attr_e( 'Categories', 'sellsuite' ); ?>"><?php echo wp_kses_post( $cats ); ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="5">No products found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

<?php
    // Product count display: "X-Y of Z products"
    $total_products = $q->found_posts;
    $current_page = $paged;
    // Calculate actual per_page for display (if -1, show all on this page)
    $display_per_page = ($per_page === -1) ? $total_products : $per_page;
    $start_num = (($current_page - 1) * $display_per_page) + 1;
    $end_num = min($current_page * $display_per_page, $total_products);
    
    echo '<div style="margin-top:15px; margin-bottom:10px; font-size:14px; color:#666;">';
    echo esc_html("Showing $start_num - $end_num of $total_products products");
    echo '</div>';

    // Pagination with Previous/Next and page numbers
    if ($q->max_num_pages > 1) {
        echo '<div style="margin-top:10px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">';
        
        // Previous button
        if ($current_page > 1) {
            echo "<button class='ss-page' data-page='" . ($current_page - 1) . "' style='padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer;'>&laquo; Previous</button>";
        } else {
            echo "<button disabled style='padding:6px 10px; border:1px solid #ddd; background:#f5f5f5; color:#999; cursor:not-allowed;'>&laquo; Previous</button>";
        }
        
        // Page numbers
        for ($i = 1; $i <= $q->max_num_pages; $i++) {
            $is_active = ($i === $current_page) ? 'true' : 'false';
            $active_style = ($i === $current_page) ? 'background:#0073aa; color:#fff;' : 'background:#fff; color:#0073aa;';
            echo "<button class='ss-page' data-page='$i' data-active='$is_active' style='padding:6px 10px; border:1px solid #ddd; $active_style cursor:pointer;'>$i</button>";
        }
        
        // Next button
        if ($current_page < $q->max_num_pages) {
            echo "<button class='ss-page' data-page='" . ($current_page + 1) . "' style='padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer;'>Next &raquo;</button>";
        } else {
            echo "<button disabled style='padding:6px 10px; border:1px solid #ddd; background:#f5f5f5; color:#999; cursor:not-allowed;'>Next &raquo;</button>";
        }
        
        echo '</div>';
    }

    wp_reset_postdata();

    echo ob_get_clean();
    wp_die();
}
