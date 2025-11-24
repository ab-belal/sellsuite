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

        wp_enqueue_script(
            'sellsuite-frontend-js',
            SELLSUITE_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            SELLSUITE_VERSION,
            true
        );
    }

    /**
     * Display customer points on account page.
     */
    public function display_customer_points() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $points = Points::get_user_total_points($user_id);

        ?>
        <div class="sellsuite-points-display">
            <h3><?php esc_html_e('Your Loyalty Points', 'sellsuite'); ?></h3>
            <p class="sellsuite-points-total">
                <?php 
                printf(
                    esc_html__('You have %s points', 'sellsuite'),
                    '<strong>' . esc_html($points) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}
