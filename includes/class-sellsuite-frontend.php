<?php
namespace SellSuite;

/**
 * The public-facing functionality of the plugin.
 */
class Frontend {

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        // if (!is_account_page()) {
        //     return;
        // }

        wp_enqueue_style(
            'sellsuite-frontend-css',
            SELLSUITE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            SELLSUITE_VERSION
        );

        // wp_enqueue_script(
        //     'sellsuite-frontend-js',
        //     SELLSUITE_PLUGIN_URL . 'assets/js/frontend.js',
        //     array('jquery'),
        //     SELLSUITE_VERSION,
        //     true
        // );

    }

    /**
     * Register frontend points display hooks.
     */
    public function register_frontend_hooks() {
        // Product page points display
        add_action('woocommerce_after_add_to_cart_button', array('SellSuite_Frontend_Display', 'display_product_points'));

        // Cart page points display
        add_action('woocommerce_after_cart_item_name', array('SellSuite_Frontend_Display', 'display_cart_item_points'), 10, 2);

        // Checkout review order table points row
        add_action('woocommerce_review_order_before_order_total', array('SellSuite_Frontend_Display', 'add_checkout_points_row'));

        // Thank you page points display
        add_action('woocommerce_thankyou', array('SellSuite_Frontend_Display', 'display_thankyou_points'), 5, 1);
        add_action('woocommerce_thankyou', array('SellSuite_Frontend_Display', 'display_thankyou_balance'), 15, 1);

        // Inline CSS for points display
        add_action('wp_head', array('SellSuite_Frontend_Display', 'add_inline_css'));
    }

    /**
     * Register custom WooCommerce My Account endpoint.
     * 
     * This method adds a new endpoint called 'products-info' to WooCommerce.
     * The endpoint will appear as a menu item in the My Account page.
     * 
     * Hook: init
     */
    public function add_products_info_endpoint() {
        // Register the custom endpoint
        // First parameter: endpoint slug (URL friendly)
        // Second parameter: endpoint mask (EP_ROOT = available at root level)
        add_rewrite_endpoint('products-info', EP_ROOT | EP_PAGES);
    }

    /**
     * Add the endpoint to WooCommerce My Account menu items.
     * 
     * This filter adds our custom endpoint to the My Account navigation menu.
     * You can control the position by adjusting where you insert it in the array.
     * 
     * Hook: woocommerce_account_menu_items
     * 
     * @param array $items Existing menu items
     * @return array Modified menu items with our endpoint added
     */
    public function add_products_info_menu_item($items) {
        // Insert the new menu item after 'orders' and before 'downloads'
        // You can change the position by modifying the array manipulation below
        
        $new_items = array();
        
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            
            // Insert our custom endpoint after the 'orders' menu item
            if ($key === 'orders') {
                // The key 'products-info' should match the endpoint slug registered above
                // The value is the menu label that will be displayed
                $new_items['products-info'] = __('Products', 'sellsuite');
            }
        }
        
        return $new_items;
    }

    /**
     * Display content for the products-info endpoint page.
     * 
     * This method is called when the user navigates to the products-info page
     * in their My Account area. It loads the template file that displays the
     * products information table.
     * 
     * Hook: woocommerce_account_products-info_endpoint
     */
    public function products_info_endpoint_content() {
        // Load the template file
        // This keeps the display logic separate from the controller logic
        $template_path = SELLSUITE_PLUGIN_DIR . 'templates/woocommerce/myaccount/products-info.php';
        
        // Check if template file exists before loading
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback error message if template is missing
            ?>
            <div class="woocommerce-notices-wrapper">
                <div class="woocommerce-error" role="alert">
                    <?php esc_html_e('Template file not found. Please contact the site administrator.', 'sellsuite'); ?>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Set the title for the products-info endpoint page.
     * 
     * This filter changes the page title when viewing the products-info endpoint.
     * 
     * Hook: the_title
     * 
     * @param string $title Original title
     * @return string Modified title
     */
    public function products_info_endpoint_title($title) {
        global $wp_query;

        // Check if we're on the products-info endpoint in My Account
        if (isset($wp_query->query_vars['products-info']) && in_the_loop()) {
            $title = __('Products Info', 'sellsuite');
        }

        return $title;
    }
}
