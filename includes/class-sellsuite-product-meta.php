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
     * For variations:
     * 1. If custom value is set → use it
     * 2. If not set → do NOT use parent, return custom value OR calculate from price
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

        // NOTE: Do NOT fall back to parent product points per requirements
        // Variations with no custom value will be calculated via Points::get_variation_display_points()
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
     * NOTE: Do NOT show for variable products (variations handled separately)
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
            'high',
            array('exclude_post_types' => array('product_variation'))
        );
    }

    /**
     * Render product meta box.
     * 
     * Only shown for simple products, NOT for variable products.
     * 
     * @param WP_Post $post Post object
     * @return void
     */
    public static function render_product_meta_box($post) {
        $product = wc_get_product($post->ID);
        
        // Skip for variable products
        if ($product && $product->is_type('variable')) {
            echo '<p>' . esc_html__('Reward Points are managed per variation for variable products.', 'sellsuite') . '</p>';
            return;
        }

        $product_id = $post->ID;
        $points_value = get_post_meta($product_id, '_reward_points_value', true);
        $points_type = get_post_meta($product_id, '_reward_points_type', true) ?: 'fixed';

        wp_nonce_field('sellsuite_product_points_nonce', 'sellsuite_product_points_nonce');
        ?>
        <div class="sellsuite-reward-points-metabox">
            <p>
                <label for="reward_points_value"><?php esc_html_e('Reward Points Value', 'sellsuite'); ?>:</label>
                <input type="number" id="reward_points_value" name="reward_points_value" value="<?php echo esc_attr($points_value); ?>" min="0" style="width: 100px;" placeholder="">
            </p>

            <p>
                <label for="reward_points_type"><?php esc_html_e('Calculation Method', 'sellsuite'); ?>:</label>
                <select id="reward_points_type" name="reward_points_type">
                    <option value="fixed" <?php selected($points_type, 'fixed'); ?>><?php esc_html_e('Fixed Points', 'sellsuite'); ?></option>
                    <option value="percentage" <?php selected($points_type, 'percentage'); ?>><?php esc_html_e('Percentage of Price', 'sellsuite'); ?></option>
                </select>
            </p>

            <p class="description">
                <?php esc_html_e('Fixed: Award a set number of points. Percentage: Award points based on product price. Leave empty to use global "Points Per Dollar" setting.', 'sellsuite'); ?>
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
     * NOTE: Input fields are empty by default (no placeholder/default value).
     * If a variation has no custom value, it will use automatic calculation.
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
        ?>
        <div class="form-row form-row-full">
            <label><?php esc_html_e('Reward Points', 'sellsuite'); ?>:</label>
            <input type="number" class="variation_field" name="variation_reward_points[<?php echo esc_attr($loop); ?>]" value="<?php echo esc_attr($points_value); ?>" min="0">
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
    }
}
