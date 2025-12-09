# SellSuite Reward Points - Developer Quick Reference

## 🚀 Quick Start

### Initialize on Activation
```php
// Automatic via class-sellsuite-activator.php
// Drops old tables and creates fresh schema on activation
```

### Check if System is Enabled
```php
if (Points_Manager::is_points_enabled()) {
    // System is active
}
```

### Get User Balance
```php
$available = Points_Manager::get_available_balance($user_id);
$earned = Points_Manager::get_earned_points($user_id);
$pending = Points_Manager::get_pending_points($user_id);
```

---

## 📝 Common Operations

### Award Points Manually
```php
$ledger_id = Points_Manager::add_ledger_entry(
    $user_id,           // User ID
    $order_id,          // Order ID (0 if not order-related)
    $product_id,        // Product ID (0 if not product-related)
    'manual',           // action_type
    100,                // points_amount
    'earned',           // status (pending|earned|redeemed|refunded|expired)
    'Manual point award',  // description
    null                // notes (optional)
);
```

### Get User's Point History
```php
$history = Points_Manager::get_history(
    $user_id,
    $limit = 20,
    $filters = array(
        'action_type' => 'order_placement',  // optional
        'status' => 'earned',                // optional
        'date_from' => '2025-01-01',        // optional
        'date_to' => '2025-12-31'           // optional
    )
);
```

### Update Ledger Entry Status
```php
Points_Manager::update_ledger_status(
    $ledger_id,
    'earned',  // new_status
    'Order completed'  // notes
);
```

---

## 🛍️ Product Points

### Set Product Points
```php
Product_Meta::set_product_points(
    $product_id,
    100,        // points value
    'fixed'     // type: 'fixed' or 'percentage'
);
```

### Get Product Points
```php
$points = Product_Meta::get_product_points(
    $product_id,
    $price  // optional, for percentage calculation
);
```

### Set Variation Points
```php
Product_Meta::set_variation_points(
    $variation_id,
    50,
    'percentage'  // percentage of price
);
```

---

## 💳 Point Redemption

### Redeem Points
```php
$result = Redeem_Handler::redeem_points(
    $user_id,
    50,           // points to redeem
    $order_id,    // 0 if not order-related
    array(        // options
        'conversion_rate' => 1.0,
        'currency' => 'USD'
    )
);

if ($result['success']) {
    echo "Redeemed successfully!";
    echo "Discount: " . $result['discount_value'];
    echo "Remaining: " . $result['remaining_balance'];
} else {
    echo "Error: " . $result['message'];
    echo "Code: " . $result['code'];  // insufficient_balance, redemption_limit_exceeded, etc.
}
```

### Cancel Redemption
```php
$result = Redeem_Handler::cancel_redemption($redemption_id);
if ($result['success']) {
    echo $result['points_restored'] . " points restored";
}
```

### Get Redemption History
```php
$redemptions = Redeem_Handler::get_user_redemptions(
    $user_id,
    $limit = 20,
    $offset = 0
);
```

---

## 📦 Order Handling

### Validate Order for Points
```php
$validation = Order_Handler::validate_order($order_id);
if ($validation['valid']) {
    // Safe to process
}
```

### Get Order Points Summary
```php
$summary = Order_Handler::get_order_points_summary($order_id);
// Returns: points_awarded, points_status, created_at
```

---

## 🔄 Refunds

### Handle Full Refund
```php
Refund_Handler::on_full_refund($order_id, $refund_id);
// Automatically deducts all points
```

### Handle Partial Refund
```php
Refund_Handler::on_partial_refund($order_id, $refund_id);
// Automatically deducts proportional points
```

### Reverse a Refund
```php
$result = Refund_Handler::reverse_refund($refund_id);
if ($result['success']) {
    echo "Points restored: " . $result['points_restored'];
}
```

---

## 📊 Dashboard & Analytics

### Get System Overview
```php
$overview = Dashboard::get_overview();
// Returns: total_users, total_awarded, total_redeemed, 
// total_expired, pending, avg_per_user, redemption_rate
```

### Get User Dashboard
```php
$dashboard = Dashboard::get_user_dashboard($user_id);
// Returns: total_earned, available_balance, pending_points,
// total_redeemed, recent_transactions, recent_redemptions
```

### Get Top Earners
```php
$earners = Dashboard::get_top_earners($limit = 10);
// Returns array of users with points and transactions
```

### Get Timeline Data
```php
$timeline = Dashboard::get_points_timeline($days = 30);
// Returns daily: awarded, deducted, transaction_count
```

### Get User Segments
```php
$segments = Dashboard::get_user_segments();
// Returns: no_points, low, medium, high, premium counts
```

### Generate Report
```php
$report = Dashboard::generate_report(
    'detailed',  // 'summary' or 'detailed'
    array(
        'date_from' => '2025-01-01',
        'date_to' => '2025-12-31',
        'user_id' => 0,  // 0 for all users
        'action_type' => 'order_placement'  // null for all
    )
);
```

### Get Expiry Forecast
```php
$forecast = Dashboard::get_expiry_forecast($days = 30);
// Shows points expiring in next N days
```

---

## 🌐 REST API Quick Reference

### Fetch Dashboard Overview
```
GET /wp-json/sellsuite/v1/dashboard/overview
Authorization: WordPress Admin
```

### Redeem Points (User)
```
POST /wp-json/sellsuite/v1/redeem
{
  "points": 50,
  "order_id": 123,
  "options": {"currency": "USD"}
}
```

### Get User Redemptions
```
GET /wp-json/sellsuite/v1/redemptions?limit=20&page=1
Authorization: User Logged In
```

### Get Analytics Timeline
```
GET /wp-json/sellsuite/v1/analytics/timeline?days=30
Authorization: WordPress Admin
```

### Get Top Earners
```
GET /wp-json/sellsuite/v1/analytics/top-earners?limit=10
Authorization: WordPress Admin
```

### Get User Segments
```
GET /wp-json/sellsuite/v1/analytics/segments
Authorization: WordPress Admin
```

---

## 🔧 Settings Management

### Get All Settings
```php
$settings = Points_Manager::get_settings();
// Returns all point system configuration
```

### Available Settings Keys
```php
'points_enabled' => bool
'conversion_rate' => float (default: 1)
'max_redeemable_percentage' => int (default: 20)
'enable_expiry' => bool (default: false)
'expiry_days' => int (default: 365)
'point_calculation_method' => string ('fixed' or 'percentage')
'points_per_dollar' => int
'points_percentage' => int
```

---

## 🎣 Custom Hooks

### Action Hooks (for custom handling)
```php
// After points awarded for order
do_action('sellsuite_product_points_awarded', $product_id, $qty, $points, $order_id);

// After pending points created
do_action('sellsuite_points_awarded_pending', $order_id, $user_id, $total_points);

// After pending → earned transition
do_action('sellsuite_points_earned', $order_id, $user_id, $points_amount);

// After refund deduction
do_action('sellsuite_points_deducted_refund', $order_id, $user_id, $points_deducted, $refund_id);

// After redemption
do_action('sellsuite_points_redeemed', $user_id, $points, $discount, $order_id, $redemption_id);

// After redemption canceled
do_action('sellsuite_redemption_canceled', $redemption_id, $user_id, $points_restored);
```

### Filter Hooks
```php
// Modify dashboard overview data
$overview = apply_filters('sellsuite_dashboard_overview', $overview_data);
```

---

## 🔐 Security Patterns

### Always Sanitize Input
```php
$user_id = intval($_POST['user_id']);
$points = intval($_REQUEST['points']);
$note = sanitize_text_field($_POST['note']);
```

### Always Verify Capabilities
```php
if (!current_user_can('manage_woocommerce')) {
    wp_die('Insufficient permissions');
}
```

### Use Prepared Statements
```php
global $wpdb;
$user_points = $wpdb->get_var($wpdb->prepare(
    "SELECT points_amount FROM $table WHERE user_id = %d",
    $user_id
));
```

### Check Nonces
```php
if (!wp_verify_nonce($_POST['_wpnonce'], 'redeem_points')) {
    wp_die('Invalid request');
}
```

---

## ❌ Error Codes Reference

### Redemption Errors
- `invalid_user` - User not found
- `system_disabled` - Points system disabled
- `invalid_points_amount` - Points <= 0
- `insufficient_balance` - Not enough points
- `redemption_limit_exceeded` - Exceeds order max
- `database_error` - Database operation failed
- `ledger_error` - Failed to create ledger entry
- `system_error` - Unexpected error

---

## 📱 Common Scenarios

### Award Points After Custom Event
```php
// In your plugin/theme code
$ledger_id = Points_Manager::add_ledger_entry(
    $user_id,
    0,  // No order
    0,  // No product
    'referral_bonus',  // Custom action type
    100,  // Points
    'earned',
    'Referral bonus for friend signup'
);
```

### Apply Points Discount at Checkout
```php
// Hook: woocommerce_cart_calculate_fees
$result = Redeem_Handler::redeem_points(
    get_current_user_id(),
    $points_to_use,
    WC()->cart->get_cart_hash(),
    array('conversion_rate' => 1.0)
);

if ($result['success']) {
    WC()->cart->add_fee(-$result['discount_value']);
}
```

### Check Expiry Status
```php
$user_points = Points_Manager::get_available_balance($user_id);
$pending_expiry = Dashboard::get_expiry_forecast(7);  // Next 7 days
```

---

## 🐛 Debugging

### Check Error Log
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

### Enable WP_DEBUG
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Inspect Ledger
```php
global $wpdb;
$entries = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}sellsuite_points_ledger 
    WHERE user_id = $user_id 
    ORDER BY created_at DESC LIMIT 10"
);
```

---

**Last Updated:** December 2, 2025
**Version:** 1.0.0
**Status:** ✅ Production Ready
