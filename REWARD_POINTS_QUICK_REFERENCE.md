# Quick Reference: Reward Points Priority System

## The System in One Sentence
**Show custom product reward points if set, otherwise automatically calculate using product price × global "Points Per Dollar" setting.**

---

## Two New Methods

### 1. For Products
```php
$points = \SellSuite\Points::get_product_display_points($product_id);
$points = \SellSuite\Points::get_product_display_points($product_id, $custom_price);
```

### 2. For Variations
```php
$points = \SellSuite\Points::get_variation_display_points($variation_id);
$points = \SellSuite\Points::get_variation_display_points($variation_id, $custom_price);
```

---

## Priority Logic

```
┌─ Has Custom Points? ────── YES ──→ Return Custom Value
│
└─ NO ──→ Calculate: Price × Points Per Dollar ──→ Return Result
```

---

## Where Updated

| Component | Change | File |
|-----------|--------|------|
| Product Page Display | Uses new method | `class-sellsuite-frontend-display.php` |
| Cart Display | Uses new method | `class-sellsuite-frontend-display.php` |
| Checkout Display | Uses new method | `class-sellsuite-frontend-display.php` |
| Order Award | Uses new method | `class-sellsuite-order-handler.php` |

---

## Settings Reference

**Find:** Admin → SellSuite → Settings → Point Management → General → Points Per Dollar

**Default:** 1 (one point per dollar)

---

## Examples

### Example 1: Product with Custom Points
- Custom Points: 100
- Price: $50
- Points Per Dollar: 1
- **Result: 100 points** (custom used)

### Example 2: Product without Custom
- Custom Points: (none)
- Price: $50
- Points Per Dollar: 1
- **Result: 50 points** (calculated)

### Example 3: Product without Custom, Higher PPD
- Custom Points: (none)
- Price: $50
- Points Per Dollar: 2
- **Result: 100 points** (calculated)

---

## Code Files Modified

1. ✅ `class-sellsuite-points-manager.php` - Added 2 new methods
2. ✅ `class-sellsuite-frontend-display.php` - Updated 3 methods
3. ✅ `class-sellsuite-order-handler.php` - Updated 1 method

---

## How to Test

1. **Create test product without custom points**
   - Set price: $25
   - Don't set custom reward points
   - Visit product page
   - Should show: 25 points (if PPD=1)

2. **Create test product with custom points**
   - Set price: $25
   - Set custom reward points: 50
   - Visit product page
   - Should show: 50 points (custom override)

3. **Change Points Per Dollar setting**
   - Go to Settings → Point Management → General
   - Change "Points Per Dollar" to 2
   - Visit product without custom points
   - Should show: 50 points (recalculated)

---

## Key Features

✅ **Automatic Calculation** - No need to set custom points  
✅ **Custom Override** - Can still set fixed points per product  
✅ **Consistent Display** - Shows same points everywhere (product page, cart, checkout)  
✅ **Automatic Award** - Order points calculated correctly  
✅ **Backward Compatible** - Old custom points still work  

---

## Troubleshooting

**Points showing 0?**
- Check if product has price > $0
- Check if Points Per Dollar is set (default: 1)

**Points not updating after price change?**
- Price changes → auto-calculated points change (correct)
- Custom points → never change with price

**Different points in different places?**
- All use same new method now
- Should show consistent values

---

**Documentation:** See `REWARD_POINTS_PRIORITY_SYSTEM.md` for detailed info
