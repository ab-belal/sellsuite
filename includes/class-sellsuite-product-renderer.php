<?php
/**
 * SellSuite Product Renderer
 * 
 * Shared utility to render product table, pagination, and product count.
 * Used by both AJAX handler and My Account template.
 * 
 * @package SellSuite
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SellSuite_Product_Renderer {

    /**
     * Build product query from filters and search parameters
     * 
     * @param array $params Filter parameters (paged, search, cat, stock, brand, per_page)
     * @return WP_Query
     */
    public static function build_product_query($params = []) {
        $paged   = max(1, intval($params['paged'] ?? 1));
        $search  = sanitize_text_field($params['s'] ?? '');
        $cat     = intval($params['cat'] ?? 0);
        $stock   = sanitize_text_field($params['stock'] ?? '');
        $brand   = intval($params['brand'] ?? 0);
        $per_raw = sanitize_text_field($params['per_page'] ?? '10');
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
            if (ctype_digit($search)) {
                $args['post__in'] = [ intval($search) ];
            } else {
                $args['s'] = $search;
            }

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

        return new WP_Query($args);
    }

    /**
     * Handle AJAX product loading request
     */
    public static function handle_ajax_load_products() {
        $params = [
            'paged'   => $_POST['paged'] ?? 1,
            's'       => $_POST['s'] ?? '',
            'cat'     => $_POST['cat'] ?? 0,
            'stock'   => $_POST['stock'] ?? '',
            'brand'   => $_POST['brand'] ?? 0,
            'per_page' => $_POST['per_page'] ?? '10',
        ];

        $query = self::build_product_query($params);
        $per_page = ($params['per_page'] === 'all') ? -1 : max(1, intval($params['per_page']));
        
        $html = self::render_products_table($query, max(1, intval($params['paged'])), $per_page);
        echo $html;
        wp_die();
    }

    /**
     * Render pagination controls and product count
     * 
     * @param WP_Query $query The WP_Query object
     * @param int $current_page Current page number
     * @param int $per_page Items per page
     */
    private static function render_pagination_and_count($query, $current_page = 1, $per_page = 10) {
        $total_products = $query->found_posts;
        $display_per_page = ($per_page === -1) ? $total_products : $per_page;
        $start_num = (($current_page - 1) * $display_per_page) + 1;
        $end_num = min($current_page * $display_per_page, $total_products);
        
        // Convert per_page value to option value for select
        $per_page_option = ($per_page === -1) ? 'all' : (string)$per_page;
        ?>

        <div style="margin-top:15px; margin-bottom:15px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:15px;">
            <!-- Product Count Display and Per-Page Selector -->
            <div class="product-showing" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
                <select class="ss-per-page-select" data-current="<?php echo esc_attr($per_page_option); ?>" style="padding:6px 10px; border:1px solid #ddd; border-radius:4px; font-size:14px;">
                    <option value="5" <?php echo $per_page_option === '5' ? 'selected' : ''; ?>>5 per page</option>
                    <option value="10" <?php echo $per_page_option === '10' ? 'selected' : ''; ?>>10 per page</option>
                    <option value="25" <?php echo $per_page_option === '25' ? 'selected' : ''; ?>>25 per page</option>
                    <option value="50" <?php echo $per_page_option === '50' ? 'selected' : ''; ?>>50 per page</option>
                    <option value="100" <?php echo $per_page_option === '100' ? 'selected' : ''; ?>>100 per page</option>
                    <option value="all" <?php echo $per_page_option === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
                
                <div style="font-size:14px; color:#666;">
                    <?php echo esc_html("Showing $start_num – $end_num of $total_products products"); ?>
                </div>
            </div>

            <!-- Pagination -->
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <?php
                // Previous button
                if ($current_page > 1) {
                ?>
                    <button class='ss-page' data-page='<?php echo esc_attr($current_page - 1); ?>' style='padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer;'>
                        &laquo; Previous
                    </button>
                <?php
                } else {
                ?>
                    <button disabled style='padding:6px 10px; border:1px solid #ddd; background:#f5f5f5; color:#999; cursor:not-allowed;'>
                        &laquo; Previous
                    </button>
                <?php
                }

                // Page numbers
                for ($i = 1; $i <= $query->max_num_pages; $i++) {
                    $active_style = ($i === $current_page) ? 'background:#0073aa; color:#fff;' : 'background:#fff; color:#0073aa;';
                ?>
                    <button class='ss-page' data-page='<?php echo esc_attr($i); ?>' style='padding:6px 10px; border:1px solid #ddd; <?php echo esc_attr($active_style); ?> cursor:pointer;'>
                        <?php echo esc_html($i); ?>
                    </button>
                <?php
                }

                // Next button
                if ($current_page < $query->max_num_pages) {
                ?>
                    <button class='ss-page' data-page='<?php echo esc_attr($current_page + 1); ?>' style='padding:6px 10px; border:1px solid #ddd; background:#fff; cursor:pointer;'>
                        Next &raquo;
                    </button>
                <?php
                } else {
                ?>
                    <button disabled style='padding:6px 10px; border:1px solid #ddd; background:#f5f5f5; color:#999; cursor:not-allowed;'>
                        Next &raquo;
                    </button>
                <?php
                }
                ?>
            </div>
        </div>
        <?php
    }

    public static function render_products_table($query, $current_page = 1, $per_page = 10) {
        if (!$query->have_posts()) {
            ?>
            <div class="woocommerce-info">
                <?php esc_html_e('No products found.', 'sellsuite'); ?>
            </div>
            <?php
            return;
        }

        // Display pagination and count at top
        self::render_pagination_and_count($query, $current_page, $per_page);
        ?>

        <table class="shop_table">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'sellsuite'); ?></th>
                    <th><?php esc_html_e('Name', 'sellsuite'); ?></th>
                    <th><?php esc_html_e('SKU', 'sellsuite'); ?></th>
                    <th><?php esc_html_e('Price', 'sellsuite'); ?></th>
                    <th><?php esc_html_e('Stock', 'sellsuite'); ?></th>
                    <th><?php esc_html_e('Categories', 'sellsuite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($query->have_posts()) {
                    $query->the_post();
                    $pid = get_the_ID();
                    $product = wc_get_product($pid);

                    if (!$product) continue;

                    $product_name = $product->get_name();
                    $sku = $product->get_sku();

                    // Price rendering
                    $regular_price = '';
                    $sale_price = '';
                    if ($product->is_type('variable')) {
                        $regular_price = $product->get_variation_regular_price('min');
                        $sale_price = $product->get_variation_sale_price('min');
                    } else {
                        $regular_price = $product->get_regular_price();
                        $sale_price = $product->get_sale_price();
                    }

                    if (empty($regular_price) && $product->get_price()) {
                        $regular_price = $product->get_price();
                    }

                    if ($sale_price && $sale_price !== $regular_price) {
                        $price_html = '<span class="sellsuite-regular-price" style="text-decoration:line-through;color:#6b7280;margin-right:6px;">' . wc_price($regular_price) . '</span>';
                        $price_html .= '<span class="sellsuite-sale-price" style="color:#0b6646;font-weight:600;">' . wc_price($sale_price) . '</span>';
                    } else {
                        $price_html = $regular_price ? wc_price($regular_price) : '&ndash;';
                    }

                    // Stock status
                    $stock_status = self::get_stock_status($product);

                    // Thumbnail
                    $thumb = get_the_post_thumbnail($pid, array(60, 60), array('loading' => 'lazy', 'alt' => get_the_title($pid)));
                    if (!$thumb) {
                        $thumb = '<svg width="60" height="60" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="24" height="24" fill="#eef2f6"/><path d="M5 16l3-4 4 5 5-7 2 3v4H5z" fill="#d1dbe3"/></svg>';
                    }

                    // Categories
                    $cats = wc_get_product_category_list($pid, ', ');
                ?>
                    <tr>
                        <td data-title="<?php esc_attr_e('ID', 'sellsuite'); ?>">
                            <?php echo esc_html($pid); ?>
                        </td>
                        <td data-title="<?php esc_attr_e('Name', 'sellsuite'); ?>">
                            <div class="sellsuite-product-cell" style="display:flex;align-items:center;gap:10px;">
                                <div class="sellsuite-product-thumb" style="width:60px;height:60px;flex:0 0 60px;overflow:hidden;border-radius:6px;background:#f6f7f9;display:flex;align-items:center;justify-content:center;">
                                    <?php echo wp_kses_post($thumb); ?>
                                </div>
                                <div class="sellsuite-product-title">
                                    <a href="<?php echo esc_url(get_permalink($pid)); ?>" target="_blank" style="color:inherit;text-decoration:none;">
                                        <?php echo esc_html($product_name); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td data-title="<?php esc_attr_e('SKU', 'sellsuite'); ?>">
                            <?php echo esc_html($sku); ?>
                        </td>
                        <td data-title="<?php esc_attr_e('Price', 'sellsuite'); ?>">
                            <?php echo wp_kses_post($price_html); ?>
                        </td>
                        <td data-title="<?php esc_attr_e('Stock', 'sellsuite'); ?>">
                            <?php echo wp_kses_post($stock_status); ?>
                        </td>
                        <td data-title="<?php esc_attr_e('Categories', 'sellsuite'); ?>">
                            <?php echo wp_kses_post($cats); ?>
                        </td>
                    </tr>
                <?php
                }
                wp_reset_postdata();
                ?>
            </tbody>
        </table>

        <?php
        // Display pagination and count at bottom
        self::render_pagination_and_count($query, $current_page, $per_page);
        wp_reset_postdata();
    }

    /**
     * Get stock status HTML for a product
     * 
     * @param WC_Product $product
     * @return string Stock status HTML
     */
    private static function get_stock_status($product) {
        $stock_status = '';
        $stock_qty = $product->get_stock_quantity();

        if ($product->managing_stock()) {
            if (null !== $stock_qty) {
                $stock_label = wc_get_stock_html($product);
                $stock_status = sprintf('%s', wp_kses_post($stock_label));
            } else {
                $stock_status = wp_kses_post(wc_get_stock_html($product));
            }
        } else {
            $status = $product->get_stock_status();
            $status_map = array(
                'instock'     => __('In stock', 'sellsuite'),
                'outofstock'  => __('Out of stock', 'sellsuite'),
                'onbackorder' => __('On backorder', 'sellsuite'),
            );
            $stock_status = isset($status_map[$status]) ? esc_html($status_map[$status]) : esc_html($status);
        }

        return $stock_status;
    }
}
