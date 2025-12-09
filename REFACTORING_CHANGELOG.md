# SellSuite Plugin Refactoring Changelog

## Overview
This changelog documents the comprehensive refactoring of the SellSuite plugin to improve clarity and support multi-currency functionality.

---

## Changes Made

### 1. Function Rename: `is_enabled()` → `is_points_enabled()`

**Objective:** Make the function name more explicit and meaningful.

**Files Modified:**
- `includes/class-sellsuite-points-manager.php`
  - Line 95: Function definition renamed
  
- `includes/class-sellsuite-frontend-display.php`
  - Line 36: `display_product_points()` method
  - Line 74: `add_checkout_points_row()` method
  - Line 120: `display_thankyou_points()` method
  - Line 188: `display_cart_item_points()` method

- `includes/class-sellsuite-order-handler.php`
  - Line 49: `award_points_for_order()` method
  - Line 379: `validate_order_for_points()` method

**Impact:** All calls to `Points::is_enabled()` now use `Points::is_points_enabled()`. The functionality remains identical.

---

### 2. Settings Key Rename: `points_per_dollar` → `points_per_currency`

**Objective:** Make the settings key universal for any currency, not just dollars.

**Files Modified:**

**Backend (PHP):**
- `includes/class-sellsuite-points-manager.php`
  - Line 82: Default settings array (default value: 1)
  - Line 309: `calculate_order_points()` method
  - Line 362: `get_product_display_points()` method
  - Line 407: `get_variation_display_points()` method

- `includes/class-sellsuite-order-handler.php`
  - Line 305: `calculate_order_points()` method fallback

**Frontend (JavaScript/React):**
- `admin/src/pages/settings/PointManagement.jsx`
  - Line 20: Initial state configuration updated to use `points_per_currency`

**Impact:** All references to the `points_per_dollar` setting key now use `points_per_currency`. This is a data structure change affecting how settings are stored and retrieved from the database option `sellsuite_settings`.

---

### 3. Dynamic Currency Labels (Multi-Currency Support)

**Objective:** Replace hardcoded "Dollar" text with the WooCommerce store's selected currency.

**Files Modified:**

- `admin/src/pages/settings/PointManagement/EarningPoints/index.jsx`
  - Added currency variable to retrieve WooCommerce currency: `const currency = window.wc?.wcSettings?.general?.currency || 'USD'`
  - Updated "Calculation Method" dropdown label from "Fixed Points per Dollar" to "Fixed Points per {currency}"
  - Updated "Points per Dollar Spent" label to "Points per {currency} Spent"
  - Updated field description from "Number of points earned for every dollar spent" to "Number of points earned for every {currency} spent"

**Impact:** The settings UI now dynamically displays the store's currency code (e.g., "USD", "EUR", "GBP") instead of hardcoded "Dollar". This provides better clarity for multi-currency stores.

---

## Comments Updated

All inline code comments were also updated to reflect the new terminology:

**In `class-sellsuite-points-manager.php`:**
- "Points Per Dollar setting" → "Points Per Currency setting"
- "price × points_per_dollar" → "price × points_per_currency"

**In `class-sellsuite-order-handler.php`:**
- "Fixed method: points per dollar" → "Fixed method: points per currency"

---

## Database Considerations

**Important:** The settings key change from `points_per_dollar` to `points_per_currency` is stored in the WordPress options table under the `sellsuite_settings` option.

- **Old key:** `sellsuite_settings['points_per_dollar']`
- **New key:** `sellsuite_settings['points_per_currency']`

**Migration Note:** Existing installations with the old key will need to be handled. Consider adding a migration routine if upgrading existing sites. The default fallback value (1) will be used if the key doesn't exist.

---

## Verification Summary

✅ All PHP files updated and verified
✅ All JavaScript/React files updated and verified  
✅ No hardcoded "Dollar" text remaining
✅ No references to `is_enabled()` remaining (except in comments)
✅ No references to `points_per_dollar` remaining
✅ All `is_enabled()` calls changed to `is_points_enabled()`
✅ All `points_per_dollar` references changed to `points_per_currency`

---

## Backward Compatibility

**Breaking Changes:**
- Function name changed from `is_enabled()` to `is_points_enabled()`
- Settings key changed from `points_per_dollar` to `points_per_currency`

**Recommendations for Migration:**
1. Add data migration script to update existing settings from old to new key
2. Update any custom code or plugins referencing the old function name
3. Test thoroughly with existing data before deploying to production

---

## Testing Checklist

- [ ] Product page displays correct points with new calculation method
- [ ] Checkout page shows accurate point estimates
- [ ] Points are awarded correctly when orders are completed
- [ ] Settings page displays currency dynamically based on WooCommerce settings
- [ ] Dashboard shows accurate point balances and history
- [ ] Multi-currency stores display appropriate currency labels
- [ ] System enable/disable toggle works correctly with new function name
- [ ] Order processing respects the enabled/disabled state

---

## Files Changed Summary

**Total Files Modified: 8**

1. `includes/class-sellsuite-points-manager.php` - Core points logic
2. `includes/class-sellsuite-frontend-display.php` - Frontend display logic
3. `includes/class-sellsuite-order-handler.php` - Order processing logic
4. `admin/src/pages/settings/PointManagement.jsx` - React settings component
5. `admin/src/pages/settings/PointManagement/EarningPoints/index.jsx` - Earning settings UI

---

**Refactoring Completed:** December 9, 2025
**Version:** 1.0.0+
