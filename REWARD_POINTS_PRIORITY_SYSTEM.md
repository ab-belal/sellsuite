# Reward Points Priority System

**Last Updated:** December 8, 2025  
**Status:** ✅ Implementation Complete

---

## Overview

Implemented a two-tier priority system for displaying and calculating reward points for products:

### Priority Levels:

**1️⃣ Primary (Highest Priority):** Custom Product Reward Points  
If a product has a custom reward points value set in the "Reward Points Value" metabox, that value is used.

**2️⃣ Fallback (Secondary):** Automatic Calculation  
If no custom value is set, the system automatically calculates points using:
```
Reward Points = Product Price × "Points Per Dollar" (global setting)
```

---

## Implementation Details

### New Methods Added

#### 1. `Points::get_product_display_points($product_id, $price = null)`

**Location:** `class-sellsuite-points-manager.php`

**Purpose:** Calculate and display reward points for a product with priority logic

**Parameters:**
- `$product_id` (int) - Product ID
- `$price` (float, optional) - Custom price for calculation (uses product price if not provided)

**Return:** (int) Display points

**Logic Flow:**
```
1. Check for custom product points via Product_Meta::get_product_points()
   └─ IF found AND > 0 → RETURN custom points
   
2. Fallback to automatic calculation:
   └─ Get product price (use provided price or fetch from product)
   └─ IF price <= 0 → RETURN 0
   └─ Get "Points Per Dollar" from global settings
   └─ Calculate: floor(price × points_per_dollar)
   └─ RETURN calculated points
```

**Example Usage:**
```php
// Display product points on single product page
$points = Points::get_product_display_points(123);
echo "Earn $points reward points!";

// Display product points with custom price
$points = Points::get_product_display_points(123, 49.99);
```

---

#### 2. `Points::get_variation_display_points($variation_id, $price = null)`

**Location:** `class-sellsuite-points-manager.php`

**Purpose:** Calculate and display reward points for product variations with priority logic

**Parameters:**
- `$variation_id` (int) - Variation ID
- `$price` (float, optional) - Custom price for calculation

**Return:** (int) Display points

**Logic Flow:**
```
1. Check for custom variation points via Product_Meta::get_variation_points()
   └─ IF found AND > 0 → RETURN custom points
   
2. Fallback to automatic calculation:
   └─ Get variation price (use provided price or fetch from variation)
   └─ IF price <= 0 → RETURN 0
   └─ Get "Points Per Dollar" from global settings
   └─ Calculate: floor(price × points_per_dollar)
   └─ RETURN calculated points
```

**Example Usage:**
```php
// Display variation points
$points = Points::get_variation_display_points(456);

// Display variation points with custom price
$points = Points::get_variation_display_points(456, 29.99);
```

---

### Updated Methods

#### Modified: `SellSuite_Frontend_Display::display_product_points()`

**File:** `class-sellsuite-frontend-display.php` (Line 28)

**Change:**
```php
// BEFORE
$points = \SellSuite\Product_Meta::get_product_points($product_id);

// AFTER
$points = \SellSuite\Points::get_product_display_points($product_id);
```

**Impact:** Product points now shown on single product page with automatic calculation fallback

---

#### Modified: `SellSuite_Frontend_Display::add_checkout_points_row()`

**File:** `class-sellsuite-frontend-display.php` (Line 75)

**Change:**
```php
// BEFORE
$points = \SellSuite\Product_Meta::get_product_points($product_id);

// AFTER
$points = \SellSuite\Points::get_product_display_points($product_id);
```

**Impact:** Cart review section now shows accurate points with fallback calculation

---

#### Modified: `SellSuite_Frontend_Display::display_cart_item_points()`

**File:** `class-sellsuite-frontend-display.php` (Line 176)

**Change:**
```php
// BEFORE
$points = \SellSuite\Product_Meta::get_product_points($product_id);

// AFTER
$points = \SellSuite\Points::get_product_display_points($product_id);
```

**Impact:** Cart items now display accurate points with fallback calculation

---

#### Modified: `Order_Handler::order_on_placed()`

**File:** `class-sellsuite-order-handler.php` (Line 68)

**Change:**
```php
// BEFORE
$product_points = Product_Meta::get_product_points($product_id, $line_total / $quantity);

// AFTER
$product_points = Points::get_product_display_points($product_id, $line_total / $quantity);
```

**Impact:** Order point awarding now uses consistent priority logic with automatic calculation

---

## Data Flow

### When a Product is Displayed

```
Product Page / Cart / Checkout
         ↓
Points::get_product_display_points($product_id)
         ↓
    Check Custom Points
         ↓
    ┌─────────────────────────────────┐
    │ Is custom value set AND > 0?    │
    └─────────────────────────────────┘
         ↓ YES              ↓ NO
      [RETURN]        Fallback to Auto-Calc
      Custom             ↓
      Points         Get Product Price
                          ↓
                     Get Points Per Dollar
                          ↓
                    Calculate: price × ppd
                          ↓
                      [RETURN]
                    Calculated Points
```

### When an Order is Placed

```
Order Placed
    ↓
Order_Handler::order_on_placed()
    ↓
For Each Line Item:
    ├─ Get Product ID & Price
    ├─ Points::get_product_display_points()
    │  ├─ Custom Points? → Use it
    │  └─ No? → Calculate (price × ppd)
    ├─ Multiply by Quantity
    └─ Add to Total
    ↓
If Total Points > 0:
    └─ Award as "pending" points
    └─ Create ledger entry
    └─ Fire action hook
```

---

## Settings Reference

### Required Global Setting: "Points Per Dollar"

**Setting Key:** `sellsuite_settings['points_per_dollar']`

**Default Value:** `1` (1 point per $1 spent)

**Location:** Admin → SellSuite → Settings → Point Management → General

**Example Scenarios:**

| Points Per Dollar | Product Price | Result |
|-------------------|---------------|--------|
| 1                 | $10.00        | 10 points |
| 2                 | $10.00        | 20 points |
| 0.5               | $10.00        | 5 points |
| 1.5               | $15.99        | 23 points |

---

## Product Meta Structure

### Custom Reward Points Meta Keys

**Product Meta:**
- `_reward_points_value` - Integer reward points (fixed type) or percentage (percentage type)
- `_reward_points_type` - Either `"fixed"` or `"percentage"`

**Variation Meta:**
- `_reward_points_value` - Integer reward points for variation
- `_reward_points_type` - Either `"fixed"` or `"percentage"`

### How Custom Points Work

**Fixed Type:**
```php
Custom Points = Exact value from metabox
Example: "50 points" always means 50 points regardless of price
```

**Percentage Type:**
```php
Custom Points = floor(price × percentage / 100)
Example: "10%" on $50 product = floor($50 × 10 / 100) = 5 points
```

---

## Testing Scenarios

### Scenario 1: Product with Custom Points

**Setup:**
- Product ID: 123
- Price: $49.99
- Custom Points Value: 75 (fixed)
- Points Per Dollar: 1

**Expected Result:**
- Display Points: **75** (custom value used)
- Cart Display: **75**
- Order Award: **75** (per unit)

---

### Scenario 2: Product without Custom Points

**Setup:**
- Product ID: 456
- Price: $24.99
- Custom Points Value: (not set)
- Points Per Dollar: 1

**Expected Result:**
- Display Points: **24** (floor of 24.99 × 1)
- Cart Display: **24**
- Order Award: **24** (per unit)

---

### Scenario 3: Product without Custom, Higher PPD

**Setup:**
- Product ID: 789
- Price: $49.99
- Custom Points Value: (not set)
- Points Per Dollar: 2

**Expected Result:**
- Display Points: **99** (floor of 49.99 × 2)
- Cart Display: **99**
- Order Award: **99** (per unit)

---

### Scenario 4: Multi-item Order

**Setup:**
- Product A: $20 (custom 25 points)
- Product B: $15 (no custom, PPD=1)
- Product C: $10 (custom 20 points)

**Expected Result:**
- Product A Points: 25 × 1 = 25
- Product B Points: 15 × 1 = 15
- Product C Points: 20 × 1 = 20
- **Total Order Points: 60**

---

## Code Architecture

### Class Hierarchy

```
Points (class-sellsuite-points-manager.php)
├── get_product_display_points()      ← NEW
├── get_variation_display_points()    ← NEW
├── get_settings()
├── get_available_balance()
├── add_ledger_entry()
└── [Other methods...]

    ↓ Uses ↓

Product_Meta (class-sellsuite-product-meta.php)
├── get_product_points()              ← Gets custom value only
├── get_variation_points()            ← Gets custom value only
├── set_product_points()
└── [Other methods...]
```

### Separation of Concerns

- **`Product_Meta` methods:** Handle custom metabox values only
- **`Points` methods:** Handle priority logic + automatic calculation
- **Frontend classes:** Call `Points::get_*_display_points()` for consistent display

---

## Important Notes

### 1. Rounding Behavior
- Calculation uses `floor()` to round down
- Example: 49.99 × 1 = 49 points (not 50)

### 2. Zero Price Products
- Products with price ≤ $0 return 0 points
- Regardless of Points Per Dollar setting

### 3. Custom Points Override
- Custom points completely override the global calculation
- Even if Points Per Dollar changes later

### 4. Backward Compatibility
- `Product_Meta::get_product_points()` still works as before
- Returns custom value OR 0 (no fallback)
- Use new `Points::get_product_display_points()` for display

### 5. Multiple Quantity Items
- Points multiply by quantity
- Example: Product with 10 points × 3 qty = 30 points

---

## Usage Examples

### Example 1: Display Points on Product Page

```php
// In a template or custom code
$product_id = get_the_ID();
$points = \SellSuite\Points::get_product_display_points($product_id);

if ($points > 0) {
    echo "Earn <strong>$points</strong> reward points with this purchase!";
}
```

### Example 2: Custom Price Calculation

```php
// Calculate points for a specific price
$custom_price = 99.99;
$points = \SellSuite\Points::get_product_display_points($product_id, $custom_price);
echo "At $custom_price, you'd earn $points points";
```

### Example 3: Check if Product Has Custom Points

```php
// Get the raw custom value to determine if it was set
$custom_value = get_post_meta($product_id, '_reward_points_value', true);
$has_custom = !empty($custom_value);

if ($has_custom) {
    echo "This product has custom reward points";
} else {
    echo "This product uses automatic points calculation";
}
```

### Example 4: Variation Points

```php
// For variable products with variations
$variation_id = 456;
$points = \SellSuite\Points::get_variation_display_points($variation_id);
echo "This variation earns $points points";
```

---

## Debugging

### Check Global Settings

```php
// Verify Points Per Dollar setting
$settings = \SellSuite\Points::get_settings();
$ppd = $settings['points_per_dollar'] ?? 1;
echo "Points Per Dollar: $ppd";
```

### Check Custom Product Value

```php
// See if product has custom points
$custom = get_post_meta($product_id, '_reward_points_value', true);
$type = get_post_meta($product_id, '_reward_points_type', true);
echo "Custom Value: $custom ($type)";
```

### Calculate Manually

```php
// Manual calculation
$product = wc_get_product($product_id);
$price = (float) $product->get_price();
$ppd = $settings['points_per_dollar'] ?? 1;
$calculated = floor($price * $ppd);
echo "Calculated Points: $calculated";
```

---

## Related Functions

- `Product_Meta::get_product_points()` - Get custom value only
- `Product_Meta::get_variation_points()` - Get variation custom value
- `Points::get_settings()` - Get all global settings
- `Points::is_enabled()` - Check if points system is enabled
- `Order_Handler::order_on_placed()` - Award order points

---

## Files Modified

| File | Changes |
|------|---------|
| `class-sellsuite-points-manager.php` | Added 2 new methods |
| `class-sellsuite-frontend-display.php` | Updated 3 methods to use new logic |
| `class-sellsuite-order-handler.php` | Updated 1 method to use new logic |

---

## Version History

| Version | Date | Change |
|---------|------|--------|
| 1.0.0 | 2025-12-08 | Initial implementation of priority system |

---

**Summary:** ✅ Reward points now display with priority logic: custom product value first, fallback to automatic calculation (price × Points Per Dollar) if no custom value is set.
