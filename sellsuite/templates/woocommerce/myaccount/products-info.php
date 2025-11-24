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

// Create dummy data array for the table
// In a real implementation, you would fetch this from the database
$dummy_products = array(
    array(
        'id'       => 101,
        'name'     => 'Wireless Bluetooth Headphones',
        'sku'      => 'WBH-2024-001',
        'price'    => '$79.99',
        'stock'    => 'In Stock (45)',
        'category' => 'Electronics',
    ),
    array(
        'id'       => 102,
        'name'     => 'Organic Cotton T-Shirt',
        'sku'      => 'OCT-2024-155',
        'price'    => '$24.99',
        'stock'    => 'Low Stock (8)',
        'category' => 'Clothing',
    ),
);

/**
 * Hook: sellsuite_before_products_info_table
 * 
 * Allow other plugins/themes to add content before the products table
 */
do_action('sellsuite_before_products_info_table');
?>

<div class="woocommerce-products-info">
    <h2><?php esc_html_e('Products Information', 'sellsuite'); ?></h2>
    <p><?php esc_html_e('View detailed information about available products.', 'sellsuite'); ?></p>

    <table class="woocommerce-table woocommerce-table--products-info shop_table shop_table_responsive">
        <thead>
            <tr>
                <th class="product-id"><?php esc_html_e('ID', 'sellsuite'); ?></th>
                <th class="product-name"><?php esc_html_e('Name', 'sellsuite'); ?></th>
                <th class="product-sku"><?php esc_html_e('SKU', 'sellsuite'); ?></th>
                <th class="product-price"><?php esc_html_e('Price', 'sellsuite'); ?></th>
                <th class="product-stock"><?php esc_html_e('Stock', 'sellsuite'); ?></th>
                <th class="product-category"><?php esc_html_e('Category', 'sellsuite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dummy_products as $product) : ?>
                <tr>
                    <td class="product-id" data-title="<?php esc_attr_e('ID', 'sellsuite'); ?>">
                        <?php echo esc_html($product['id']); ?>
                    </td>
                    <td class="product-name" data-title="<?php esc_attr_e('Name', 'sellsuite'); ?>">
                        <strong><?php echo esc_html($product['name']); ?></strong>
                    </td>
                    <td class="product-sku" data-title="<?php esc_attr_e('SKU', 'sellsuite'); ?>">
                        <?php echo esc_html($product['sku']); ?>
                    </td>
                    <td class="product-price" data-title="<?php esc_attr_e('Price', 'sellsuite'); ?>">
                        <?php echo esc_html($product['price']); ?>
                    </td>
                    <td class="product-stock" data-title="<?php esc_attr_e('Stock', 'sellsuite'); ?>">
                        <?php echo esc_html($product['stock']); ?>
                    </td>
                    <td class="product-category" data-title="<?php esc_attr_e('Category', 'sellsuite'); ?>">
                        <?php echo esc_html($product['category']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
/**
 * Hook: sellsuite_after_products_info_table
 * 
 * Allow other plugins/themes to add content after the products table
 */
do_action('sellsuite_after_products_info_table');
