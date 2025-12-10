<?php
/**
 * Point Redemption Box
 * 
 * Displayed on checkout page to allow customers to redeem points
 * 
 * @package SellSuite
 * @subpackage Templates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current user
$user_id = get_current_user_id();
if (!$user_id) {
    return; // Only show for logged-in users
}

// Get points data
$available_points = \SellSuite\Points::get_available_balance($user_id);
if ($available_points <= 0) {
    return; // No points to redeem
}

// Get settings
$settings = \SellSuite\Points::get_settings();
$conversion_rate = $settings['conversion_rate'] ?? 1;
$max_redeemable_percentage = $settings['max_redeemable_percentage'] ?? 20;

// Get WooCommerce currency info
$currency = get_woocommerce_currency();
$currency_symbol = get_woocommerce_currency_symbol();

// Get order total (estimate from cart)
$order_total = WC()->cart->get_total(false);
$max_redeemable = ($order_total * $max_redeemable_percentage) / 100;

// If no order total, use sensible default
if ($order_total <= 0) {
    return;
}
?>

<div id="sellsuite-redemption-box" class="sellsuite-redemption-box woocommerce-notices-wrapper">
    <div class="sellsuite-redemption-header">
        <h3>
            <span class="dashicons dashicons-star-filled"></span>
            <?php esc_html_e('Redeem Your Points', 'sellsuite'); ?>
        </h3>
    </div>

    <div class="sellsuite-redemption-content">
        <!-- Available Points Display -->
        <div class="sellsuite-points-info">
            <div class="info-item">
                <span class="label"><?php esc_html_e('Available Points:', 'sellsuite'); ?></span>
                <span class="value" id="sellsuite-available-points"><?php echo intval($available_points); ?></span>
            </div>
            <div class="info-item">
                <span class="label"><?php esc_html_e('Max Redeemable:', 'sellsuite'); ?></span>
                <span class="value" id="sellsuite-max-redeemable">
                    <?php echo $currency_symbol . number_format($max_redeemable, 2); ?>
                </span>
            </div>
        </div>

        <!-- Input Field -->
        <div class="sellsuite-input-group">
            <label for="sellsuite-redeem-points-input">
                <?php esc_html_e('How many points do you want to redeem?', 'sellsuite'); ?>
            </label>
            <div class="input-wrapper">
                <input 
                    type="number" 
                    id="sellsuite-redeem-points-input" 
                    name="sellsuite_redeem_points" 
                    min="0" 
                    max="<?php echo intval($available_points); ?>" 
                    placeholder="0"
                    class="form-control"
                />
                <button type="button" id="sellsuite-apply-redemption-btn" class="button button-primary">
                    <?php esc_html_e('Apply Redemption', 'sellsuite'); ?>
                </button>
            </div>
        </div>

        <!-- Real-time Calculation Display -->
        <div id="sellsuite-redemption-calculation" class="sellsuite-calculation-display">
            <p class="sellsuite-no-selection">
                <?php esc_html_e('Enter points amount to see discount calculation', 'sellsuite'); ?>
            </p>
        </div>

        <!-- Hidden input to store redemption ID -->
        <input type="hidden" name="sellsuite_redemption_id" value="" />
    </div>
</div>

<style>
#sellsuite-redemption-box {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin: 20px 0;
}

#sellsuite-redemption-box .sellsuite-redemption-header {
    margin: 0 0 20px 0;
    padding-bottom: 15px;
    border-bottom: 2px solid #007cba;
}

#sellsuite-redemption-box .sellsuite-redemption-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

#sellsuite-redemption-box .dashicons {
    color: #ffc107;
    font-size: 24px;
}

#sellsuite-redemption-box .sellsuite-points-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
    background: white;
    padding: 15px;
    border-radius: 3px;
}

#sellsuite-redemption-box .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

#sellsuite-redemption-box .info-item .label {
    font-weight: 600;
    color: #666;
    font-size: 14px;
}

#sellsuite-redemption-box .info-item .value {
    font-size: 18px;
    font-weight: 700;
    color: #ffc107;
}

#sellsuite-redemption-box .sellsuite-input-group {
    margin-bottom: 20px;
}

#sellsuite-redemption-box .sellsuite-input-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #333;
}

#sellsuite-redemption-box .input-wrapper {
    display: flex;
    gap: 10px;
}

#sellsuite-redemption-box .input-wrapper input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 16px;
}

#sellsuite-redemption-box .input-wrapper input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

#sellsuite-redemption-box .input-wrapper button {
    padding: 10px 20px;
    white-space: nowrap;
}

#sellsuite-redemption-box .input-wrapper button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

#sellsuite-redemption-box .sellsuite-calculation-display {
    background: white;
    padding: 15px;
    border-radius: 3px;
    border-left: 4px solid #ffc107;
}

#sellsuite-redemption-box .sellsuite-calculation-row {
    margin: 10px 0;
}

#sellsuite-redemption-box .sellsuite-calculation-row .label {
    color: #666;
    font-size: 14px;
}

#sellsuite-redemption-box .sellsuite-calculation-row strong {
    color: #007cba;
    font-weight: 600;
}

#sellsuite-redemption-box .sellsuite-no-selection {
    margin: 0;
    color: #999;
    font-style: italic;
    font-size: 13px;
}

#sellsuite-redemption-box .sellsuite-redemption-warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 3px;
    padding: 10px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #856404;
    font-size: 13px;
}

#sellsuite-redemption-box .sellsuite-redemption-warning .dashicons {
    color: #ffc107;
    font-size: 16px;
}

.sellsuite-redemption-row td button.sellsuite-cancel-redemption-btn {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    padding: 0 5px;
    font-size: 16px;
    margin-left: 10px;
}

.sellsuite-redemption-row td button.sellsuite-cancel-redemption-btn:hover {
    color: #c82333;
}

@media (max-width: 768px) {
    #sellsuite-redemption-box .sellsuite-points-info {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    #sellsuite-redemption-box .input-wrapper {
        flex-direction: column;
    }

    #sellsuite-redemption-box .input-wrapper input {
        width: 100%;
    }

    #sellsuite-redemption-box .input-wrapper button {
        width: 100%;
    }
}
</style>
