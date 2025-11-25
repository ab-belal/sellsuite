<?php
/**
 * My Account Products Info Endpoint Template
 *
 * This template displays the products information table on the My Account page.
 * 
 * @package SellSuite
 * @version 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Security check: Ensure user is logged in
if (!is_user_logged_in()) {
    ?>
    <div class="woocommerce-notices-wrapper">
        <div class="woocommerce-info" role="alert">
            <?php esc_html_e('You must be logged in to view this page.', 'sellsuite'); ?>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="woocommerce-Button button">
                <?php esc_html_e('Login', 'sellsuite'); ?>
            </a>
        </div>
    </div>
    <?php
    return;
}

// Check if user has the product_viewer capability
// Only users with this capability can view this page
if (!current_user_can('product_viewer')) {
    ?>
    <div class="woocommerce-notices-wrapper">
        <div class="woocommerce-message" role="alert">
            <?php esc_html_e('You do not have permission to view product information.', 'sellsuite'); ?>
        </div>
    </div>
    <?php
    return;
}

/**
 * Hook: sellsuite_before_products_info_table
 *
 * Allow other plugins/themes to add content before the products table
 */
do_action( 'sellsuite_before_products_info_table' );

if ( ! is_user_logged_in() || ! current_user_can('product_viewer') ) {
    echo '<p>You do not have permission to view this page.</p>';
    return;
}
?>
<h2>Products Info</h2>

<?php
// --- Filters (UI-only, dynamic population) ---
// Populate product categories and (if available) a brand taxonomy. These selects are UI placeholders
// and do not alter the query yet. They provide dynamic options similar to the WP admin product table.
$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );

// Detect a brand taxonomy (common names used by themes/plugins)
$brand_taxonomy = '';
$possible_brand_taxonomies = array( 'product_brand', 'pa_brand', 'brand' );
foreach ( $possible_brand_taxonomies as $bt ) {
    if ( taxonomy_exists( $bt ) ) {
        $terms = get_terms( array( 'taxonomy' => $bt, 'hide_empty' => false ) );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $brand_taxonomy = $bt;
            $brand_terms = $terms;
            break;
        }
    }
}
?>

<div class="sellsuite-filters" style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
    <div class="sellsuite-filter-item">
        <label for="sellsuite-filter-category" class="screen-reader-text"><?php esc_html_e( 'Filter by category', 'sellsuite' ); ?></label>
        <select id="sellsuite-filter-category" name="filter_cat" style="min-width:200px;">
            <option value=""><?php esc_html_e( 'All Categories', 'sellsuite' ); ?></option>
            <?php if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) : foreach ( $categories as $cat ) : ?>
                <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
            <?php endforeach; endif; ?>
        </select>
    </div>

    <div class="sellsuite-filter-item">
        <label for="sellsuite-filter-stock" class="screen-reader-text"><?php esc_html_e( 'Filter by stock status', 'sellsuite' ); ?></label>
        <select id="sellsuite-filter-stock" name="filter_stock" style="min-width:160px;">
            <option value=""><?php esc_html_e( 'All Stock', 'sellsuite' ); ?></option>
            <option value="instock"><?php esc_html_e( 'In stock', 'sellsuite' ); ?></option>
            <option value="outofstock"><?php esc_html_e( 'Out of stock', 'sellsuite' ); ?></option>
            <option value="onbackorder"><?php esc_html_e( 'On backorder', 'sellsuite' ); ?></option>
        </select>
    </div>

    <?php if ( ! empty( $brand_taxonomy ) ) : ?>
    <div class="sellsuite-filter-item">
        <label for="sellsuite-filter-brand" class="screen-reader-text"><?php esc_html_e( 'Filter by brand', 'sellsuite' ); ?></label>
        <select id="sellsuite-filter-brand" name="filter_brand" style="min-width:200px;">
            <option value=""><?php esc_html_e( 'All Brands', 'sellsuite' ); ?></option>
            <?php foreach ( $brand_terms as $bt ) : ?>
                <option value="<?php echo esc_attr( $bt->term_id ); ?>"><?php echo esc_html( $bt->name ); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

</div>

<?php
// Server-side product list: supports basic search (GET parameter 's') and pagination ('paged')
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
// Items per page: allow user selection via GET `per_page`.
$per_page_raw = isset( $_GET['per_page'] ) ? sanitize_text_field( wp_unslash( $_GET['per_page'] ) ) : '';
if ( $per_page_raw === '' ) {
    $per_page = 5; // default
} elseif ( strtolower( $per_page_raw ) === 'all' ) {
    $per_page = -1;
} else {
    $per_page = max( 1, intval( $per_page_raw ) );
}

$args = array(
    'post_type'      => 'product',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'post_status'    => 'publish',
);

if ( $search ) {
    $args['s'] = $search;
}

$products_query = new WP_Query( $args );
$total = $products_query->found_posts;
$max_pages = $products_query->max_num_pages;


?>

<form method="get" class="sellsuite-products-search" style="margin-bottom:12px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
    <label class="screen-reader-text" for="s"><?php esc_html_e( 'Search products', 'sellsuite' ); ?></label>
    <input type="search" name="s" id="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search products by title or SKU...', 'sellsuite' ); ?>" />
    <label for="sellsuite-per-page" class="screen-reader-text"><?php esc_html_e( 'Products per page', 'sellsuite' ); ?></label>
    <button type="submit" class="button"><?php esc_html_e( 'Search', 'sellsuite' ); ?></button>

    <select id="sellsuite-per-page" name="per_page" style="min-width:120px;">
        <?php
        $per_page_options = array( 5, 10, 25, 50, 100 );
        foreach ( $per_page_options as $opt ) :
        ?>
            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $per_page, $opt ); ?>><?php echo esc_html( $opt ); ?> <?php esc_html_e( 'per page', 'sellsuite' ); ?></option>
        <?php endforeach; ?>
        <option value="all" <?php selected( $per_page, -1 ); ?>><?php esc_html_e( 'All', 'sellsuite' ); ?></option>
    </select>

    
</form>



<?php if ( $products_query->have_posts() ) : ?>

<div id="sellsuite-products-container">
    
    <div class="sellsuite-pagination" style="margin-top:16px;">
        <?php
        // Pagination links
        echo paginate_links( array(
            'base'      => add_query_arg( 'paged', '%#%' ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $max_pages,
            'prev_text' => '&laquo; ' . __( 'Prev', 'sellsuite' ),
            'next_text' => __( 'Next', 'sellsuite' ) . ' &raquo;',
        ) );
        ?>
    </div>

    <table class="woocommerce-table woocommerce-table--products-info shop_table shop_table_responsive">
        <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Name', 'sellsuite' ); ?></th>
                <!-- <th><?php esc_html_e( 'SKU', 'sellsuite' ); ?></th> -->
                <th><?php esc_html_e( 'Price', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Stock', 'sellsuite' ); ?></th>
                <th><?php esc_html_e( 'Categories', 'sellsuite' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ( $products_query->have_posts() ) : $products_query->the_post();
                $pid = get_the_ID();
                $product = wc_get_product( $pid );

                if ( ! $product ) continue;

                $product_name = $product->get_name();
                $sku = $product->get_sku();

                // Price rendering: show both regular and sale price when applicable.
                // Handle simple and variable products. For variable, show minimum prices.
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
                    <!-- <td data-title="<?php esc_attr_e( 'SKU', 'sellsuite' ); ?>"><?php echo esc_html( $sku ); ?></td> -->
                    <td data-title="<?php esc_attr_e( 'Price', 'sellsuite' ); ?>"><?php echo wp_kses_post( $price_html ); ?></td>
                    <td data-title="<?php esc_attr_e( 'Stock', 'sellsuite' ); ?>"><?php echo wp_kses_post( $stock_status ); ?></td>
                    <td data-title="<?php esc_attr_e( 'Categories', 'sellsuite' ); ?>"><?php echo wp_kses_post( $cats ); ?></td>
                </tr>
            <?php endwhile; wp_reset_postdata(); ?>
        </tbody>
    </table>


</div>

<?php else : ?>
    <div class="woocommerce-info">
        <?php esc_html_e( 'No products found.', 'sellsuite' ); ?>
    </div>
<?php endif; ?>

<noscript>
    <div class="woocommerce-info">
        <?php esc_html_e( 'JavaScript is required to load the product listing. Please enable JavaScript in your browser.', 'sellsuite' ); ?>
    </div>
</noscript>


<?php
/**
 * Hook: sellsuite_after_products_info_table
 *
 * Allow other plugins/themes to add content after the products table
 */
do_action( 'sellsuite_after_products_info_table' );

?>

<script type="text/javascript">
// (function(){
//     var container = document.getElementById('sellsuite-products-container');
//     var form = document.querySelector('.sellsuite-products-search');
//     if (!container || !form) return;

//     function buildUrlWithParams(overrides) {
//         var url = new URL(window.location.href);
//         if (overrides && typeof overrides === 'object') {
//             Object.keys(overrides).forEach(function(k){
//                 var v = overrides[k];
//                 if (v === null || v === undefined || v === '') {
//                     url.searchParams.delete(k);
//                 } else {
//                     url.searchParams.set(k, v);
//                 }
//             });
//         }
//         return url.toString();
//     }

//     function setBusy(state){
//         if (state) {
//             container.setAttribute('aria-busy','true');
//             container.style.opacity = '0.6';
//         } else {
//             container.removeAttribute('aria-busy');
//             container.style.opacity = '';
//         }
//     }

//     async function loadProducts(params){
//         var url = buildUrlWithParams(params || {});
//         setBusy(true);
//         try{
//             var resp = await fetch(url, { credentials: 'same-origin' });
//             if (!resp.ok) throw new Error('Network response was not ok');
//             var text = await resp.text();
//             var parser = new DOMParser();
//             var doc = parser.parseFromString(text, 'text/html');
//             var newContainer = doc.getElementById('sellsuite-products-container');
//             if (newContainer) {
//                 container.innerHTML = newContainer.innerHTML;
//             } else {
//                 // Fallback: try to extract the table and pagination separately
//                 var newTable = doc.querySelector('.woocommerce-table--products-info');
//                 var newPager = doc.querySelector('.sellsuite-pagination');
//                 if (newTable) {
//                     var oldTable = container.querySelector('.woocommerce-table--products-info');
//                     if (oldTable) oldTable.parentNode.replaceChild(newTable, oldTable);
//                 }
//                 if (newPager) {
//                     var oldPager = container.querySelector('.sellsuite-pagination');
//                     if (oldPager) oldPager.parentNode.replaceChild(newPager, oldPager);
//                 }
//             }
//             // update URL without reloading
//             window.history.pushState({}, '', url);
//             // sync per_page select with current URL params
//             var currentUrl = new URL(url);
//             var currentPerPage = currentUrl.searchParams.get('per_page') || '5';
//             var perSelect = document.getElementById('sellsuite-per-page');
//             if (perSelect) {
//                 perSelect.value = currentPerPage;
//             }
//             // re-attach pagination handlers for new links
//             attachPaginationHandlers();
//         } catch(e){
//             console.error(e);
//         } finally{
//             setBusy(false);
//         }
//     }

//     function attachPaginationHandlers(){
//         var pagLinks = container.querySelectorAll('.sellsuite-pagination a');
//         pagLinks.forEach(function(a){
//             a.addEventListener('click', function(e){
//                 e.preventDefault();
//                 var href = a.href;
//                 try{
//                     // Support both query-based and pretty permalinks (/page/2/)
//                     var u = new URL(href, window.location.origin);
//                     var paged = u.searchParams.get('paged');
//                     if ( ! paged ) {
//                         // try common permalink pattern /page/2/
//                         var m = u.pathname.match(/\/page\/(\d+)\/?$/i);
//                         if ( m && m[1] ) {
//                             paged = m[1];
//                         }
//                     }
//                     if ( ! paged ) {
//                         // fallback to pagenum or default
//                         paged = u.searchParams.get('pagenum') || 1;
//                     }
//                     var per = document.getElementById('sellsuite-per-page');
//                     var per_val = per ? per.value : '';
//                     loadProducts({ paged: paged, per_page: per_val });
//                 } catch(err){
//                     // fallback: request current URL (no params)
//                     loadProducts({});
//                 }
//             });
//         });
//     }

//     // per-page control
//     var perSelect = document.getElementById('sellsuite-per-page');
//     if (perSelect) {
//         perSelect.addEventListener('change', function(){
//             var val = perSelect.value;
//             // reset to page 1 when changing per-page
//             var pagedInput = form.querySelector('input[name="paged"]');
//             if (pagedInput) pagedInput.value = 1;
//             loadProducts({ paged: 1, per_page: val });
//         });
//     }

//     // intercept search form submit
//     form.addEventListener('submit', function(e){
//         e.preventDefault();
//         var fd = new FormData(form);
//         var params = {};
//         fd.forEach(function(v,k){ params[k]=v; });
//         loadProducts(params);
//     });

//     // handle back/forward
//     window.addEventListener('popstate', function(){
//         loadProducts({});
//     });

//     // initial attach
//     attachPaginationHandlers();

// })();
</script>
<?php
