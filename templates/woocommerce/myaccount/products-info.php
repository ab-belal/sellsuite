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

<style>
#ss-loader {
    display:none;
    text-align:center;
    padding:20px;
}
#ss-loader svg {
    width:40px;
    height:40px;
    animation:ss-spin 1s linear infinite;
}
@keyframes ss-spin {
    100% { transform: rotate(360deg); }
}
</style>


<div id="sellsuite-products-wrapper">

    <input type="search" id="ss-search" placeholder="Search products…">

    <select id="ss-filter-cat">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= esc_attr($cat->term_id) ?>"><?= esc_html($cat->name) ?></option>
        <?php endforeach; ?>
    </select>

    <select id="ss-filter-stock">
        <option value="">All Stock</option>
        <option value="instock">In stock</option>
        <option value="outofstock">Out of stock</option>
        <option value="onbackorder">On backorder</option>
    </select>

    <?php if (!empty($brand_taxonomy)): ?>
    <select id="ss-filter-brand">
        <option value="">All Brands</option>
        <?php foreach ($brand_terms as $b): ?>
        <option value="<?= esc_attr($b->term_id) ?>"><?= esc_html($b->name) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <select id="ss-per-page">
        <option value="5">5 per page</option>
        <option value="10">10 per page</option>
        <option value="25">25 per page</option>
        <option value="50">50 per page</option>
        <option value="100">100 per page</option>
        <option value="all">All</option>
    </select>

    <!-- Sorting -->
    <!-- <select id="sellsuite-sort">
        <option value="">Sort: Default</option>
        <option value="id_asc">ID ↑</option>
        <option value="id_desc">ID ↓</option>
        <option value="name_asc">Name ↑</option>
        <option value="name_desc">Name ↓</option>
        <option value="price_asc">Price ↑</option>
        <option value="price_desc">Price ↓</option>
    </select> -->

    
    <div id="ss-loader">
        <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10" stroke="#555" stroke-width="3" fill="none" stroke-linecap="round" stroke-dasharray="60 20"/>
        </svg>
    </div>


    <!-- Dynamic output container -->
    <div id="ss-product-table"></div>
</div>



<?php
/**
 * Hook: sellsuite_after_products_info_table
 *
 * Allow other plugins/themes to add content after the products table
 */
do_action( 'sellsuite_after_products_info_table' );

?>
<script>
    document.addEventListener("DOMContentLoaded", function () {

        let timer;

        function showLoader() {
            document.getElementById("ss-loader").style.display = "block";
        }
        function hideLoader() {
            document.getElementById("ss-loader").style.display = "none";
        }

        function loadProducts(page = 1) {
            showLoader();

            const data = {
                action: "sellsuite_load_products",
                s: document.getElementById("ss-search").value,
                cat: document.getElementById("ss-filter-cat").value,
                stock: document.getElementById("ss-filter-stock").value,
                brand: document.getElementById("ss-filter-brand")?.value || "",
                per_page: document.getElementById("ss-per-page").value,
                // sort: document.getElementById("sellsuite-sort").value,
                paged: page
            };

            fetch("<?= admin_url('admin-ajax.php') ?>", {
                method: "POST",
                body: new URLSearchParams(data),
                headers: { "Content-Type": "application/x-www-form-urlencoded" }
            })
            .then(res => res.text())
            .then(html => {
                document.getElementById("ss-product-table").innerHTML = html;
                hideLoader();

                document.querySelectorAll(".ss-page").forEach(btn => {
                    btn.addEventListener("click", function () {
                        loadProducts(this.dataset.page);
                    });
                });
            });
        }

        // Initial load
        loadProducts();

        // Search on typing (debounced)
        document.getElementById("ss-search").addEventListener("keyup", function () {
            clearTimeout(timer);
            timer = setTimeout(() => loadProducts(1), 300);
        });

        // Enter loads instantly
        document.getElementById("ss-search").addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
                clearTimeout(timer);
                loadProducts(1);
            }
        });

        // Filters + per page (change event)
        ["ss-filter-cat", "ss-filter-stock", "ss-filter-brand", "ss-per-page"]
            .forEach(id => {
                let el = document.getElementById(id);
                if (el) el.addEventListener("change", () => loadProducts(1));
            });

    });


</script>
