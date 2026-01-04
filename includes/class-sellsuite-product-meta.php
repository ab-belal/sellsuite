<?php
namespace SellSuite;

/**
 * Handle product reward points meta.
 * 
 * Manages reward points assignment for products, including simple and variable products.
 */
class Product_Meta {

    /**
     * Get product reward points.
     * 
     * @param int   $product_id Product ID
     * @param float $price Optional price for percentage calculation
     * @return int Points value
     */
    public static function get_product_points($product_id, $price = null) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }

        // Check if points are disabled for this product
        $disable_points = get_post_meta($product_id, '_disable_product_points', true);
        if ($disable_points) {
            return 0;
        }

        // Get reward points from product meta
        $points_value = get_post_meta($product_id, '_reward_points_value', true);
        $points_type = get_post_meta($product_id, '_reward_points_type', true);

        // Default: no custom points set
        if (!$points_value) {
            return 0;
        }

        // If type is percentage, calculate based on price
        if ($points_type === 'percentage') {
            if ($price === null) {
                $price = $product->get_price();
            }
            return floor(($price * intval($points_value)) / 100);
        }

        // Fixed type
        return intval($points_value);
    }

    /**
     * Get variation reward points.
     * 
     * @param int   $variation_id Variation ID
     * @param float $price Optional price for percentage calculation
     * @return int Points value
     */
    public static function get_variation_points($variation_id, $price = null) {
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return 0;
        }

        // Check if points are disabled for this variation
        $disable_points = get_post_meta($variation_id, '_disable_product_points', true);
        if ($disable_points) {
            return 0;
        }

        // Check if variation has custom points
        $variation_points = get_post_meta($variation_id, '_reward_points_value', true);
        $variation_type = get_post_meta($variation_id, '_reward_points_type', true);

        if ($variation_points) {
            if ($variation_type === 'percentage') {
                if ($price === null) {
                    $price = $variation->get_price();
                }
                return floor(($price * intval($variation_points)) / 100);
            }
            return intval($variation_points);
        }

        // Fall back to parent product points
        $parent_id = $variation->get_parent_id();
        if ($parent_id) {
            return self::get_product_points($parent_id, $price);
        }

        return 0;
    }

    /**
     * Set product reward points.
     * 
     * @param int    $product_id Product ID
     * @param int    $points Points value
     * @param string $type Type of points (fixed or percentage)
     * @return bool Success
     */
    public static function set_product_points($product_id, $points, $type = 'fixed') {
        update_post_meta($product_id, '_reward_points_value', intval($points));
        update_post_meta($product_id, '_reward_points_type', sanitize_text_field($type));
        return true;
    }

    /**
     * Set variation reward points.
     * 
     * @param int    $variation_id Variation ID
     * @param int    $points Points value
     * @param string $type Type of points (fixed or percentage)
     * @return bool Success
     */
    public static function set_variation_points($variation_id, $points, $type = 'fixed') {
        update_post_meta($variation_id, '_reward_points_value', intval($points));
        update_post_meta($variation_id, '_reward_points_type', sanitize_text_field($type));
        return true;
    }

    /**
     * Add metabox to product edit page.
     * 
     * @return void
     */
    public static function add_product_meta_box() {
        add_meta_box(
            'sellsuite_product_points',
            __('Reward Points', 'sellsuite'),
            array(self::class, 'render_product_meta_box'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render product meta box.
     * 
     * @param WP_Post $post Post object
     * @return void
     */
    public static function render_product_meta_box($post) {
        $product_id = $post->ID;
        $points_value = get_post_meta($product_id, '_reward_points_value', true);
        $points_type = get_post_meta($product_id, '_reward_points_type', true) ?: 'fixed';
        $disable_points = get_post_meta($product_id, '_disable_product_points', true);

        wp_nonce_field('sellsuite_product_points_nonce', 'sellsuite_product_points_nonce');
        ?>
        <div class="sellsuite-reward-points-metabox">
            <p>
                <label for="disable_product_points" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" id="disable_product_points" name="disable_product_points" value="1" <?php checked($disable_points, 1); ?>>
                    <span><?php esc_html_e('No point for this product', 'sellsuite'); ?></span>
                </label>
            </p>

            <p>
                <label for="reward_points_value"><?php esc_html_e('Reward Points Value', 'sellsuite'); ?>:</label>
                <input type="number" id="reward_points_value" name="reward_points_value" value="<?php echo esc_attr($points_value); ?>" min="0" style="width: 100px;">
            </p>

            <p>
                <label for="reward_points_type"><?php esc_html_e('Calculation Method', 'sellsuite'); ?>:</label>
                <select id="reward_points_type" name="reward_points_type">
                    <option value="fixed" <?php selected($points_type, 'fixed'); ?>><?php esc_html_e('Fixed Points', 'sellsuite'); ?></option>
                    <option value="percentage" <?php selected($points_type, 'percentage'); ?>><?php esc_html_e('Percentage of Price', 'sellsuite'); ?></option>
                </select>
            </p>

            <p class="description">
                <?php esc_html_e('Fixed: Award a set number of points. Percentage: Award points based on product price.', 'sellsuite'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Save product meta box data.
     * 
     * @param int $post_id Post ID
     * @return void
     */
    public static function save_product_meta_box($post_id) {
        // Verify nonce
        if (!isset($_POST['sellsuite_product_points_nonce']) || !wp_verify_nonce($_POST['sellsuite_product_points_nonce'], 'sellsuite_product_points_nonce')) {
            return;
        }

        // Verify user capability
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save disable points setting
        if (isset($_POST['disable_product_points'])) {
            update_post_meta($post_id, '_disable_product_points', 1);
        } else {
            delete_post_meta($post_id, '_disable_product_points');
        }

        // Save points value
        if (isset($_POST['reward_points_value'])) {
            update_post_meta($post_id, '_reward_points_value', intval($_POST['reward_points_value']));
        }

        // Save points type
        if (isset($_POST['reward_points_type'])) {
            update_post_meta($post_id, '_reward_points_type', sanitize_text_field($_POST['reward_points_type']));
        }
    }

    /**
     * Add meta fields to product variations.
     * 
     * @param int $loop Loop index
     * @param array $variation_data Variation data
     * @param WP_Post $variation Variation post
     * @return void
     */
    public static function add_variation_options($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;
        $points_value = get_post_meta($variation_id, '_reward_points_value', true);
        $points_type = get_post_meta($variation_id, '_reward_points_type', true) ?: 'fixed';
        $disable_points = get_post_meta($variation_id, '_disable_product_points', true);
        ?>
        <div class="form-row form-row-full">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" class="variation_field" name="variation_disable_points[<?php echo esc_attr($loop); ?>]" value="1" <?php checked($disable_points, 1); ?>>
                <span><?php esc_html_e('No point for this variation', 'sellsuite'); ?></span>
            </label>
        </div>
        <div class="form-row form-row-full">
            <label><?php esc_html_e('Reward Points', 'sellsuite'); ?>:</label>
            <input type="number" class="variation_field" name="variation_reward_points[<?php echo esc_attr($loop); ?>]" value="<?php echo esc_attr($points_value); ?>" min="0" placeholder="0">
            <select class="variation_field" name="variation_reward_points_type[<?php echo esc_attr($loop); ?>]" value="<?php echo esc_attr($points_type); ?>">
                <option value="fixed" <?php selected($points_type, 'fixed'); ?>><?php esc_html_e('Fixed', 'sellsuite'); ?></option>
                <option value="percentage" <?php selected($points_type, 'percentage'); ?>><?php esc_html_e('Percentage', 'sellsuite'); ?></option>
            </select>
        </div>
        <?php
    }

    /**
     * Save variation meta data.
     * 
     * @param int $variation_id Variation ID
     * @param int $loop Loop index
     * @return void
     */
    public static function save_variation_meta($variation_id, $loop) {
        // Save disable points setting
        if (isset($_POST['variation_disable_points'][$loop])) {
            update_post_meta($variation_id, '_disable_product_points', 1);
        } else {
            delete_post_meta($variation_id, '_disable_product_points');
        }

        if (isset($_POST['variation_reward_points'][$loop])) {
            $points = intval($_POST['variation_reward_points'][$loop]);
            $type = isset($_POST['variation_reward_points_type'][$loop]) ? sanitize_text_field($_POST['variation_reward_points_type'][$loop]) : 'fixed';
            
            update_post_meta($variation_id, '_reward_points_value', $points);
            update_post_meta($variation_id, '_reward_points_type', $type);
        }
    }

    /**
     * Delete product reward points when product is deleted.
     * 
     * @param int $product_id Product ID
     * @return void
     */
    public static function on_product_delete($product_id) {
        delete_post_meta($product_id, '_reward_points_value');
        delete_post_meta($product_id, '_reward_points_type');
        delete_post_meta($product_id, '_disable_product_points');
        delete_post_meta($product_id, '_product_cost_price');
    }

    /**
     * Get product cost price.
     * 
     * @param int $product_id Product ID
     * @return float Cost price
     */
    public static function get_product_cost_price($product_id) {
        $cost_price = get_post_meta($product_id, '_product_cost_price', true);

        if ($cost_price === '' || $cost_price === null) {
            return 0.0;
        }

        return (float) $cost_price;
    }




    /*--------------------------------------------------------------
    # SIMPLE PRODUCT
    --------------------------------------------------------------*/

    /**
     * Add cost price field to Simple Product → General → Pricing
     */
    public static function add_cost_price_simple() {
        woocommerce_wp_text_input([
            'id'                => '_product_cost_price',
            'label'             => __('Cost Price', 'sellsuite'),
            'placeholder'       => wc_format_localized_price(0),
            'description'       => __('Internal cost price used for profit calculations.', 'sellsuite'),
            'desc_tip'          => true,
            'type'              => 'number',
            'custom_attributes' => [
                'step' => 'any',
                'min'  => '0',
            ],
            'data_type'         => 'price',
        ]);
    }

    /**
     * Save cost price for Simple Product
     *
     * @param int $product_id
     */
    public static function save_cost_price_simple($product_id) {

        if (!isset($_POST['_product_cost_price'])) {
            return;
        }

        // Avoid autosave & revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $product_id)) {
            return;
        }

        $cost_price = wc_clean(wp_unslash($_POST['_product_cost_price']));

        if ($cost_price === '') {
            delete_post_meta($product_id, '_product_cost_price');
        } else {
            update_post_meta($product_id, '_product_cost_price', (float) $cost_price);
        }
    }


    /*--------------------------------------------------------------
    # VARIABLE PRODUCT (VARIATIONS)
    --------------------------------------------------------------*/
    /**
     * Add cost price field to each variation (after sale price)
     *
     * @param int     $loop
     * @param array   $variation_data
     * @param WP_Post $variation
     */
    public static function add_cost_price_variation($loop, $variation_data, $variation) {
        woocommerce_wp_text_input([
            'id'                => "_product_cost_price[$loop]",
            'name'              => "_product_cost_price[$loop]",
            'label'             => __('Cost Price', 'sellsuite'),
            'placeholder'       => wc_format_localized_price(0),
            'description'       => __('Internal cost price for this variation.', 'sellsuite'),
            'desc_tip'          => true,
            'type'              => 'number',
            'value'             => get_post_meta($variation->ID, '_product_cost_price', true),
            'custom_attributes' => [
                'step' => 'any',
                'min'  => '0',
            ],
            'data_type'         => 'price',
        ]);
    }

    /**
     * Save cost price for variations
     *
     * @param int $variation_id
     * @param int $i
     */
    public static function save_cost_price_variation($variation_id, $i) {
        if (!isset($_POST['_product_cost_price'][$i])) {
            return;
        }

        $cost_price = wc_clean(wp_unslash($_POST['_product_cost_price'][$i]));

        if ($cost_price === '') {
            delete_post_meta($variation_id, '_product_cost_price');
        } else {
            update_post_meta($variation_id, '_product_cost_price', (float) $cost_price);
        }
    }

}
