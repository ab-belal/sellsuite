# Reward Points Priority System - Visual Guide

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│           REWARD POINTS DISPLAY SYSTEM                      │
└─────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │  Product Page    │
                    │  Cart Display    │
                    │  Checkout        │
                    │  Order Process   │
                    └────────┬─────────┘
                             │
                ┌────────────┴────────────┐
                │                         │
       Frontend Display          Order Handler
       (Frontend_Display)         (Order_Handler)
                │                         │
                └────────────┬────────────┘
                             │
                    ┌────────▼────────┐
                    │ Points Class    │
                    │ (NEW METHODS)   │
                    └────────┬────────┘
                             │
         ┌───────────────────┴───────────────────┐
         │                                       │
    ┌────▼──────┐                          ┌────▼──────┐
    │ get_      │                          │ get_      │
    │ product_  │                          │ variation_│
    │ display_  │                          │ display_  │
    │ points()  │                          │ points()  │
    └────┬──────┘                          └────┬──────┘
         │                                      │
         │  Priority Logic                      │  Priority Logic
         │  ─────────────                       │  ─────────────
         │                                      │
    ┌────▼──────────────────────┐         ┌────▼──────────────────────┐
    │ 1. Check Custom Points    │         │ 1. Check Custom Points    │
    │    (Product Meta)         │         │    (Variation Meta)       │
    │                           │         │                           │
    │ 2. If found → Return      │         │ 2. If found → Return      │
    │              custom value │         │              custom value │
    │                           │         │                           │
    │ 3. If not → Calculate:    │         │ 3. If not → Calculate:    │
    │    floor(Price × PPD)     │         │    floor(Price × PPD)     │
    │                           │         │                           │
    │ 4. Return calculated      │         │ 4. Return calculated      │
    │    points (or 0)          │         │    points (or 0)          │
    └───────────────────────────┘         └───────────────────────────┘
```

---

## Decision Tree

```
START: Display Reward Points for Product
        │
        ├─ Load product
        │
        ├─ Call: Points::get_product_display_points($product_id)
        │
        └─► PRIORITY CHECK
            │
            ├─ Check: Does product have custom points meta?
            │
            ├─ IF YES: Has value AND > 0?
            │  │
            │  ├─ YES → RETURN custom_value ✓
            │  │
            │  └─ NO → Continue to fallback
            │
            ├─ IF NO: Continue to fallback
            │
            └─► FALLBACK CALCULATION
                │
                ├─ Get product price
                │
                ├─ Is price > 0?
                │
                ├─ YES:
                │  │
                │  ├─ Get Points Per Dollar setting
                │  │
                │  ├─ Calculate: floor(price × PPD)
                │  │
                │  └─ RETURN calculated_points ✓
                │
                └─ NO: RETURN 0 ✓
```

---

## Data Flow: Product Display

```
User visits product page
           │
           ▼
Frontend sees woocommerce_after_add_to_cart_button
           │
           ▼
display_product_points() is called
           │
           ├─ Get product_id from global $product
           │
           ├─ Call Points::get_product_display_points($product_id)
           │
           │  ┌──────────────────────────────┐
           │  │ Inside get_product_display   │
           │  │ _points()                    │
           │  │                              │
           │  │ 1. Call Product_Meta::      │
           │  │    get_product_points()     │
           │  │    Result: $custom_points   │
           │  │                              │
           │  │ 2. IF $custom_points > 0:  │
           │  │    RETURN $custom_points    │
           │  │                              │
           │  │ 3. ELSE:                    │
           │  │    Get Price                │
           │  │    Get PPD from settings    │
           │  │    Calculate floor(P × PPD) │
           │  │    RETURN result            │
           │  └──────────────────────────────┘
           │
           ▼
$points returned
           │
           ▼
IF $points > 0:
  Render: "Earn $points Reward Points!"
ELSE:
  Don't display
```

---

## Data Flow: Order Processing

```
Customer places order
           │
           ▼
Order_Handler::order_on_placed() is called
           │
           ▼
Get order items
           │
           ├─ For each line item:
           │  │
           │  ├─ Get product_id
           │  │
           │  ├─ Get product price / quantity
           │  │
           │  ├─ Call Points::get_product_display_points()
           │  │  with price = line_total / quantity
           │  │
           │  │  ┌──────────────────────────────┐
           │  │  │ Same priority logic as       │
           │  │  │ display (see above)          │
           │  │  └──────────────────────────────┘
           │  │
           │  ├─ Multiply by quantity
           │  │
           │  └─ Add to total
           │
           ▼
IF total_points > 0:
  │
  ├─ Create ledger entry with "pending" status
  │
  ├─ Update post meta
  │
  └─ Fire action hook
```

---

## Settings Structure

```
┌─ WordPress wp_options Table
│
└─ Option: sellsuite_settings (serialized array)
   │
   ├─ points_enabled: true/false
   │
   ├─ conversion_rate: number
   │
   ├─ max_redeemable_percentage: number
   │
   ├─ enable_expiry: true/false
   │
   ├─ expiry_days: number
   │
   ├─ point_calculation_method: "fixed"/"percentage"
   │
   ├─ points_per_dollar: 1 ◄────── USED BY NEW SYSTEM
   │  │
   │  └─ Default: 1 (1 point per $1 spent)
   │     Can be: Any decimal number
   │
   └─ points_percentage: number
```

---

## Product Meta Structure

```
Product in WordPress
│
├─ Post Meta 1: _reward_points_value
│  │
│  ├─ If set: Use this value (PRIORITY 1)
│  │
│  ├─ If not set (empty/"0"): Use fallback
│  │
│  └─ Type: integer
│
└─ Post Meta 2: _reward_points_type
   │
   ├─ Value 1: "fixed" → Use _reward_points_value as-is
   │
   └─ Value 2: "percentage" → Calculate as % of price
```

---

## Calculation Examples

### Example 1: Custom Fixed Points

```
Product: Premium T-Shirt
├─ Price: $29.99
├─ _reward_points_value: 50
├─ _reward_points_type: "fixed"
├─ Settings Points Per Dollar: 1
│
Point Calculation:
├─ Custom value exists? YES (50)
├─ Is 50 > 0? YES
└─ RESULT: 50 points ✓

(Note: Global setting ignored when custom is set)
```

---

### Example 2: Custom Percentage Points

```
Product: Jeans
├─ Price: $79.99
├─ _reward_points_value: 10
├─ _reward_points_type: "percentage"
├─ Settings Points Per Dollar: 1
│
Point Calculation:
├─ Custom value exists? YES (10)
├─ Is percentage? YES
├─ Calculate: floor(79.99 × 10 / 100)
├─ = floor(7.999)
└─ RESULT: 7 points ✓
```

---

### Example 3: No Custom, PPD=1

```
Product: Hoodie
├─ Price: $49.99
├─ _reward_points_value: (not set)
├─ _reward_points_type: (not set)
├─ Settings Points Per Dollar: 1
│
Point Calculation:
├─ Custom value? NO (empty)
├─ Fallback to auto-calc
├─ Price: 49.99
├─ PPD: 1
├─ Calculate: floor(49.99 × 1)
├─ = floor(49.99)
└─ RESULT: 49 points ✓
```

---

### Example 4: No Custom, PPD=2

```
Product: Shoes
├─ Price: $89.99
├─ _reward_points_value: (not set)
├─ Settings Points Per Dollar: 2
│
Point Calculation:
├─ Custom value? NO
├─ Fallback to auto-calc
├─ Price: 89.99
├─ PPD: 2
├─ Calculate: floor(89.99 × 2)
├─ = floor(179.98)
└─ RESULT: 179 points ✓
```

---

### Example 5: No Custom, PPD=0.5

```
Product: Cap
├─ Price: $19.99
├─ _reward_points_value: (not set)
├─ Settings Points Per Dollar: 0.5
│
Point Calculation:
├─ Custom value? NO
├─ Fallback to auto-calc
├─ Price: 19.99
├─ PPD: 0.5
├─ Calculate: floor(19.99 × 0.5)
├─ = floor(9.995)
└─ RESULT: 9 points ✓
```

---

## Code Integration Map

```
┌─────────────────────────────────────────────────────────┐
│  Point of Use                                           │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  1. Product Page Display                               │
│     └─ File: class-sellsuite-frontend-display.php     │
│        Method: display_product_points()                │
│        Line: 36                                         │
│        Calls: Points::get_product_display_points()     │
│                                                         │
│  2. Checkout Review Display                            │
│     └─ File: class-sellsuite-frontend-display.php     │
│        Method: add_checkout_points_row()               │
│        Line: 76                                         │
│        Calls: Points::get_product_display_points()     │
│                                                         │
│  3. Cart Item Display                                  │
│     └─ File: class-sellsuite-frontend-display.php     │
│        Method: display_cart_item_points()              │
│        Line: 176                                        │
│        Calls: Points::get_product_display_points()     │
│                                                         │
│  4. Order Points Calculation                           │
│     └─ File: class-sellsuite-order-handler.php        │
│        Method: order_on_placed()                       │
│        Line: 68                                         │
│        Calls: Points::get_product_display_points()     │
│                                                         │
└─────────────────────────────────────────────────────────┘

         ↓ All paths lead to ↓

┌─────────────────────────────────────────────────────────┐
│  Central Logic Hub                                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  File: class-sellsuite-points-manager.php             │
│                                                         │
│  Methods:                                              │
│  • get_product_display_points()                        │
│  • get_variation_display_points()                      │
│                                                         │
│  Both implement same priority logic:                   │
│  1. Check custom points                                │
│  2. Fallback to calculation                            │
│                                                         │
│  Both use:                                              │
│  • Product_Meta::get_product_points() [for custom]     │
│  • get_settings() [for PPD]                            │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## Testing Workflow

```
Test Scenario 1: Product with Custom Points
├─ Create product with price $25
├─ Set custom reward points: 100
├─ Go to product page
├─ Expect: 100 points displayed ✓
├─ Add to cart
├─ Expect: 100 points in cart ✓
├─ Go to checkout
├─ Expect: 100 points in review ✓
└─ Place order
   └─ Expect: 100 points awarded ✓

Test Scenario 2: Product without Custom Points
├─ Create product with price $25
├─ Don't set custom reward points
├─ PPD setting: 1
├─ Go to product page
├─ Expect: 25 points displayed ✓
├─ Change PPD to 2
├─ Refresh page
├─ Expect: 50 points displayed ✓
├─ Add to cart
├─ Expect: 50 points in cart ✓
└─ Place order
   └─ Expect: 50 points awarded ✓

Test Scenario 3: Variable Product
├─ Create variable product
├─ Set parent price: $50, custom: (none)
├─ Create variation, price: $30, custom: 75
├─ View variation
├─ Expect: 75 points (custom) ✓
├─ PPD: 1
├─ Change parent custom to 100
├─ View same variation
├─ Expect: 75 points (variation custom takes priority) ✓
└─ Remove variation custom
   └─ Expect: 100 points (parent custom) ✓
```

---

## Error Scenarios

```
Scenario 1: Zero/Negative Price
├─ Product price: $0 or negative
├─ Custom points: (none)
├─ Result: 0 points
└─ Reason: Price check prevents calculation

Scenario 2: Invalid PPD
├─ PPD setting: not set
├─ Calculation: Uses default (1)
├─ Result: 1 point per dollar
└─ Reason: Fallback default

Scenario 3: Invalid Product
├─ Product ID: doesn't exist
├─ Result: 0 points
└─ Reason: Product validation fails

Scenario 4: Rounding Down
├─ Price: $9.99
├─ PPD: 1
├─ Calculation: floor(9.99 × 1) = floor(9.99)
├─ Result: 9 points (not 10)
└─ Reason: floor() rounds down
```

---

## Performance Considerations

```
Method Calls per Product Display:
├─ 1x get_product_display_points()
├─ 1x get_product_points() [custom check]
├─ 1x wc_get_product() [if no custom]
├─ 1x get_post_meta() [via wc_get_product]
├─ 1x get_option() [for settings]
└─ Total: ~5-6 calls per product

Optimization:
├─ Product meta fetched from cache (if available)
├─ Settings loaded once and reused
├─ No additional DB queries added
└─ Minimal performance impact ✓
```

---

**Visual Guide Complete** ✅

For more details, see:
- `REWARD_POINTS_PRIORITY_SYSTEM.md` - Full technical documentation
- `REWARD_POINTS_QUICK_REFERENCE.md` - Quick reference
- `IMPLEMENTATION_SUMMARY.md` - Implementation details
