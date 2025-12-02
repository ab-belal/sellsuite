<?php
namespace SellSuite;

/**
 * Email template management for notifications.
 */
class Email_Templates {

    /**
     * Get email template by name.
     * 
     * @param string $template Template name
     * @param array  $data Template data
     * @return string HTML email body
     */
    public static function get_template($template, $data = array()) {
        $method = 'get_' . $template . '_template';

        if (method_exists(self::class, $method)) {
            return call_user_func(array(self::class, $method), $data);
        }

        return apply_filters('sellsuite_email_template_' . $template, '', $data);
    }

    /**
     * Get points awarded template.
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private static function get_points_awarded_template($data) {
        $user_name = isset($data['user_name']) ? sanitize_text_field($data['user_name']) : '';
        $points = isset($data['points']) ? intval($data['points']) : 0;
        $order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
        $balance = isset($data['available_balance']) ? intval($data['available_balance']) : 0;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007cba; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 5px 5px; }
                .points { font-size: 24px; color: #007cba; font-weight: bold; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Reward Points Earned!', 'sellsuite'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(esc_html__('Hi %s,', 'sellsuite'), $user_name); ?></p>
                    <p><?php esc_html_e('Great news! You have earned reward points from your recent purchase.', 'sellsuite'); ?></p>
                    
                    <div class="points">
                        +<?php echo esc_html($points); ?> <?php esc_html_e('points', 'sellsuite'); ?>
                    </div>

                    <p>
                        <?php printf(
                            esc_html__('Order #%d has earned you %d reward points!', 'sellsuite'),
                            $order_id,
                            $points
                        ); ?>
                    </p>

                    <p>
                        <?php printf(
                            esc_html__('Your current balance: %d points', 'sellsuite'),
                            $balance
                        ); ?>
                    </p>

                    <p><?php esc_html_e('You can use these points to get discounts on future purchases.', 'sellsuite'); ?></p>

                    <div class="footer">
                        <p><?php bloginfo('name'); ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get points earned template (status transition).
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private static function get_points_earned_template($data) {
        $user_name = isset($data['user_name']) ? sanitize_text_field($data['user_name']) : '';
        $points = isset($data['points']) ? intval($data['points']) : 0;
        $balance = isset($data['available_balance']) ? intval($data['available_balance']) : 0;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 5px 5px; }
                .points { font-size: 24px; color: #28a745; font-weight: bold; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Points Ready to Use!', 'sellsuite'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(esc_html__('Hi %s,', 'sellsuite'), $user_name); ?></p>
                    <p><?php esc_html_e('Your order has been confirmed and your reward points are now available!', 'sellsuite'); ?></p>
                    
                    <div class="points">
                        ✓ <?php echo esc_html($points); ?> <?php esc_html_e('points available', 'sellsuite'); ?>
                    </div>

                    <p>
                        <?php printf(
                            esc_html__('Total balance: %d points', 'sellsuite'),
                            $balance
                        ); ?>
                    </p>

                    <p><?php esc_html_e('Ready to redeem your points for a discount? Visit your account to start earning rewards!', 'sellsuite'); ?></p>

                    <div class="footer">
                        <p><?php bloginfo('name'); ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get redemption template.
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private static function get_redemption_template($data) {
        $user_name = isset($data['user_name']) ? sanitize_text_field($data['user_name']) : '';
        $points = isset($data['points']) ? intval($data['points']) : 0;
        $discount = isset($data['discount_value']) ? floatval($data['discount_value']) : 0;
        $balance = isset($data['remaining_balance']) ? intval($data['remaining_balance']) : 0;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #fd7e14; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 5px 5px; }
                .discount { font-size: 24px; color: #fd7e14; font-weight: bold; margin: 15px 0; }
                .details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Points Redeemed Successfully!', 'sellsuite'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(esc_html__('Hi %s,', 'sellsuite'), $user_name); ?></p>
                    <p><?php esc_html_e('Your points have been redeemed successfully!', 'sellsuite'); ?></p>
                    
                    <div class="details">
                        <p><strong><?php esc_html_e('Redemption Details:', 'sellsuite'); ?></strong></p>
                        <p>
                            <?php printf(
                                esc_html__('Points Redeemed: %d', 'sellsuite'),
                                $points
                            ); ?>
                        </p>
                        <p>
                            <strong><?php printf(
                                esc_html__('Discount Value: %s', 'sellsuite'),
                                wc_price($discount)
                            ); ?></strong>
                        </p>
                        <p>
                            <?php printf(
                                esc_html__('Remaining Balance: %d points', 'sellsuite'),
                                $balance
                            ); ?>
                        </p>
                    </div>

                    <p><?php esc_html_e('Your discount has been applied to your account.', 'sellsuite'); ?></p>

                    <div class="footer">
                        <p><?php bloginfo('name'); ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get refund template.
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private static function get_refund_template($data) {
        $user_name = isset($data['user_name']) ? sanitize_text_field($data['user_name']) : '';
        $points_deducted = isset($data['points_deducted']) ? intval($data['points_deducted']) : 0;
        $balance = isset($data['remaining_balance']) ? intval($data['remaining_balance']) : 0;
        $refund_id = isset($data['refund_id']) ? intval($data['refund_id']) : 0;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 5px 5px; }
                .deducted { font-size: 24px; color: #dc3545; font-weight: bold; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Refund Processed', 'sellsuite'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(esc_html__('Hi %s,', 'sellsuite'), $user_name); ?></p>
                    <p><?php esc_html_e('A refund has been processed on your account.', 'sellsuite'); ?></p>
                    
                    <div class="deducted">
                        -<?php echo esc_html($points_deducted); ?> <?php esc_html_e('points', 'sellsuite'); ?>
                    </div>

                    <p>
                        <?php printf(
                            esc_html__('Refund ID: #%d', 'sellsuite'),
                            $refund_id
                        ); ?>
                    </p>

                    <p>
                        <?php printf(
                            esc_html__('Your new balance: %d points', 'sellsuite'),
                            $balance
                        ); ?>
                    </p>

                    <p><?php esc_html_e('The reward points from the refunded order have been deducted from your account.', 'sellsuite'); ?></p>

                    <div class="footer">
                        <p><?php bloginfo('name'); ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get redemption canceled template.
     * 
     * @param array $data Template data
     * @return string HTML
     */
    private static function get_redemption_canceled_template($data) {
        $user_name = isset($data['user_name']) ? sanitize_text_field($data['user_name']) : '';
        $points_restored = isset($data['points_restored']) ? intval($data['points_restored']) : 0;
        $balance = isset($data['available_balance']) ? intval($data['available_balance']) : 0;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #17a2b8; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f5f5f5; padding: 20px; border-radius: 0 0 5px 5px; }
                .restored { font-size: 24px; color: #17a2b8; font-weight: bold; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e('Redemption Canceled', 'sellsuite'); ?></h1>
                </div>
                <div class="content">
                    <p><?php printf(esc_html__('Hi %s,', 'sellsuite'), $user_name); ?></p>
                    <p><?php esc_html_e('Your redemption has been canceled and your points have been restored.', 'sellsuite'); ?></p>
                    
                    <div class="restored">
                        +<?php echo esc_html($points_restored); ?> <?php esc_html_e('points restored', 'sellsuite'); ?>
                    </div>

                    <p>
                        <?php printf(
                            esc_html__('Total balance: %d points', 'sellsuite'),
                            $balance
                        ); ?>
                    </p>

                    <p><?php esc_html_e('You can use these points again for future redemptions.', 'sellsuite'); ?></p>

                    <div class="footer">
                        <p><?php bloginfo('name'); ?></p>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
