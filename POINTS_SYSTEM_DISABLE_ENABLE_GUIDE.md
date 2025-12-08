# Reward Points System - Disable/Enable Feature

**Date:** December 8, 2025  
**Status:** ✅ Implementation Complete  
**Feature:** Global Enable/Disable Toggle for Reward Points System

---

## Overview

A comprehensive disable/enable feature that provides complete control over the reward points system. When disabled, the system stops earning new points but preserves existing customer balances.

---

## What's Implemented

### 1. **When System is ENABLED** ✅

**Display Behavior:**
- ✅ Points shown on product detail page
- ✅ Points shown at checkout (order review area)
- ✅ Points shown in cart items
- ✅ Points shown on thank you page

**Earning Behavior:**
- ✅ Customers earn points on orders
- ✅ Points added to customer accounts
- ✅ Ledger entries recorded

**Example:**
```
Product Page: "Earn 50 Reward Points with this purchase"
Cart Item: "Earn 50 points"
Checkout: Points Earned: 50
Thank You: "You have earned 50 Reward Points!"
```

### 2. **When System is DISABLED** ✅

**Display Behavior:**
- ❌ NO points shown on product detail page
- ❌ NO points shown at checkout
- ❌ NO points shown in cart items
- ❌ NO points shown on thank you page (except past orders)
- ✅ Customer dashboard still accessible to view existing balance

**Earning Behavior:**
- ❌ Customers do NOT earn new points
- ❌ No points added to customer accounts
- ❌ No ledger entries created for new orders

**Preservation:**
- ✅ Existing points kept in database
- ✅ Customer balances unchanged
- ✅ Point history preserved

**Example:**
```
Product Page: (no points displayed)
Cart Item: (no points displayed)
Checkout: (no points row shown)
Thank You: (no points message for new order)
Customer Dashboard: Can still see past earned points
```

---

## System Architecture

### Control Point: Global Setting

**Setting Key:** `sellsuite_settings['points_enabled']`

**Location:** Admin Dashboard → SellSuite → Settings → Point Management → General

**Values:**
- `true` → System ENABLED (default)
- `false` → System DISABLED

**Default:** `true` (Enabled)

---

## Implementation Details

### Core Method: `Points::is_enabled()`

**File:** `class-sellsuite-points-manager.php`  
**Line:** ~95

```php
/**
 * Check if points system is enabled.
 * 
 * @return bool
 */
public static function is_enabled() {
    $settings = self::get_settings();
    return isset($settings['points_enabled']) ? (bool) $settings['points_enabled'] : true;
}
```

**Returns:**
- `true` if points system is enabled
- `false` if points system is disabled
- `true` (default) if setting is not found

---

## Files Modified

### 1. `class-sellsuite-frontend-display.php`

**4 Display Methods Updated**

#### Method 1: `display_product_points()`
**Purpose:** Show points on product detail page  
**Added Check:** Line 31-33

```php
// Hide if reward points system is disabled
if (!\SellSuite\Points::is_enabled()) {
    return;
}
```

**Impact:** Product page points badge hidden when disabled

---

#### Method 2: `add_checkout_points_row()`
**Purpose:** Show points in checkout order review  
**Added Check:** Line 58-60

```php
// Hide if reward points system is disabled
if (!\SellSuite\Points::is_enabled()) {
    return;
}
```

**Impact:** Checkout points row hidden when disabled

---

#### Method 3: `display_thankyou_points()`
**Purpose:** Show points on thank you page  
**Added Check:** Line 94-96

```php
// Hide if reward points system is disabled
if (!\SellSuite\Points::is_enabled()) {
    return;
}
```

**Impact:** Thank you page points message hidden when disabled

---

#### Method 4: `display_cart_item_points()`
**Purpose:** Show points in cart items  
**Added Check:** Line 163-165

```php
// Hide if reward points system is disabled
if (!\SellSuite\Points::is_enabled()) {
    return;
}
```

**Impact:** Cart item points display hidden when disabled

---

### 2. `class-sellsuite-order-handler.php`

**Already Had Check** ✅

**Method:** `award_points_for_order()`  
**Check Location:** Line ~48

```php
// Check if points system is enabled
if (!Points::is_enabled()) {
    return false;
}
```

**Impact:** No points awarded when disabled

---

## Data Flow: Enabled vs Disabled

### When ENABLED

```
Customer views product
        ↓
display_product_points()
        ↓
Points::is_enabled() → TRUE
        ↓
Calculate points
        ↓
Display points message ✓
        ↓
Customer checks out
        ↓
add_checkout_points_row()
        ↓
Points::is_enabled() → TRUE
        ↓
Show points in review ✓
        ↓
Order placed
        ↓
award_points_for_order()
        ↓
Points::is_enabled() → TRUE
        ↓
Award points to account ✓
        ↓
Create ledger entry ✓
```

### When DISABLED

```
Customer views product
        ↓
display_product_points()
        ↓
Points::is_enabled() → FALSE
        ↓
Return early (no display) ✓
        ↓
Customer checks out
        ↓
add_checkout_points_row()
        ↓
Points::is_enabled() → FALSE
        ↓
Return early (no display) ✓
        ↓
Order placed
        ↓
award_points_for_order()
        ↓
Points::is_enabled() → FALSE
        ↓
Return false (no points awarded) ✓
        ↓
Existing balance UNCHANGED ✓
```

---

## Usage Scenarios

### Scenario 1: Temporarily Disable During Maintenance

**Goal:** Stop earning points while fixing issues

**Steps:**
1. Go to Admin → SellSuite → Settings
2. Point Management → General
3. Set "Points Enabled" to OFF
4. Save settings

**Result:**
- New customers: NO points earned ✓
- Existing customers: Balances preserved ✓
- Points: Hidden from all pages ✓
- Re-enable: All resumes normally ✓

---

### Scenario 2: Disable for Specific Period

**Goal:** Pause rewards during low season

**Timeline:**
- Dec 1: Disable system (Black Friday special pricing)
- Jan 1: Re-enable system (Resume earning)

**Results:**
- December orders: 0 points ✓
- Existing balances: Unchanged ✓
- January: Resume earning normally ✓

---

### Scenario 3: Seasonal Toggle

**Goal:** Enable/disable on schedule

**Implementation:**
1. Set system to DISABLED by default
2. Enable during promotional periods
3. Disable off-season

**Results:**
- Complete control over earning periods ✓
- Existing balances safe ✓
- Clean, simple toggle ✓

---

## Testing Checklist

### Test 1: System Enabled (Default)

- [ ] Product page shows "Earn X points" message
- [ ] Cart items show points
- [ ] Checkout shows points row
- [ ] Thank you page shows points earned
- [ ] Points added to customer account
- [ ] Ledger entry created

### Test 2: Disable System

- [ ] Product page does NOT show points message
- [ ] Cart items do NOT show points
- [ ] Checkout does NOT show points row
- [ ] Thank you page does NOT show points message
- [ ] Points NOT added to customer account
- [ ] Ledger entry NOT created

### Test 3: Existing Balance Preserved

- [ ] Before disable: Customer has 100 points
- [ ] Disable system
- [ ] Check customer dashboard
- [ ] Points still show 100
- [ ] Re-enable system
- [ ] Points still 100
- [ ] Can earn new points normally

### Test 4: Re-enable System

- [ ] After enabling
- [ ] Product page shows points again
- [ ] Checkout shows points again
- [ ] New orders earn points
- [ ] New customers can earn

### Test 5: Multiple Enable/Disable Cycles

- [ ] Enable → Disable → Enable
- [ ] Points display correct at each stage
- [ ] Earning works correctly when enabled
- [ ] Earning stops when disabled
- [ ] Balances always preserved

---

## Admin UI Behavior

### Location

**Admin → SellSuite → Settings → Point Management → General**

### Setting Name

"Points Enabled" or "Enable Reward Points System"

### Control Type

Toggle/Checkbox

**Values:**
- ☑️ Checked = ENABLED
- ☐ Unchecked = DISABLED

### Default

Checked (Enabled)

---

## Code Integration Map

```
Points::is_enabled() ← Central Control Point
        │
        ├─ display_product_points()
        │  └─ Product page display
        │
        ├─ add_checkout_points_row()
        │  └─ Checkout display
        │
        ├─ display_thankyou_points()
        │  └─ Thank you page display
        │
        ├─ display_cart_item_points()
        │  └─ Cart item display
        │
        └─ award_points_for_order()
           └─ Order processing
```

---

## Performance Impact

### When ENABLED
- Minimal overhead
- Normal operation
- All calculations proceed
- All displays render

### When DISABLED
- **Reduced overhead** ✓
- Early returns prevent unnecessary calculations
- Display methods return immediately
- No ledger entries created
- No database writes for points

**Benefit:** Disabling system improves performance for stores not using rewards.

---

## Database Impact

### When DISABLED

**NO changes to database:**
- ✓ Existing points preserved
- ✓ Ledger entries unchanged
- ✓ Customer balances safe
- ✓ Historical data intact

**Safe to toggle:**
- Enable/disable multiple times
- No data loss
- No migrations needed
- No cleanup required

---

## Security & Validation

### Input Validation

```php
// Setting always converted to boolean
(bool) $settings['points_enabled']

// Default to true if missing
isset($settings['points_enabled']) ? (bool) $settings['points_enabled'] : true
```

### Sanitization

```php
// Value comes from WordPress option (already sanitized)
get_option('sellsuite_settings', array())

// Saved through WordPress settings API (sanitized on save)
```

### Checks Applied

| Location | Check | Impact |
|----------|-------|--------|
| Product page | Early return | No calculation |
| Checkout | Early return | No calculation |
| Thank you | Early return | No display |
| Cart items | Early return | No display |
| Order process | Early return | No points awarded |

---

## Behavior Matrix

| Scenario | Points Shown | Points Earned | Existing Balance |
|----------|-------------|---------------|-----------------|
| Enabled | ✓ Yes | ✓ Yes | ✓ Preserved |
| Disabled | ✗ No | ✗ No | ✓ Preserved |
| Enable→Disable | ✓→✗ | ✓→✗ | ✓ Preserved |
| Disable→Enable | ✗→✓ | ✗→✓ | ✓ Preserved |

---

## API Reference

### Check if System Enabled

```php
if (\SellSuite\Points::is_enabled()) {
    // Show points, earn points
} else {
    // Don't show, don't earn
}
```

### Get Settings

```php
$settings = \SellSuite\Points::get_settings();
$enabled = $settings['points_enabled'] ?? true;
```

### Update Setting

```php
$settings = \SellSuite\Points::get_settings();
$settings['points_enabled'] = false; // or true
update_option('sellsuite_settings', $settings);
```

---

## Example Implementations

### Custom Hook: Show Custom Message When Disabled

```php
if (!\SellSuite\Points::is_enabled()) {
    // Show custom message that rewards are paused
    echo 'Rewards temporarily unavailable';
}
```

### Custom Hook: Show Different Message When Enabled

```php
if (\SellSuite\Points::is_enabled()) {
    // Show normal reward points message
} else {
    // Show alternative message
}
```

### Conditional Checkout Display

```php
function maybe_show_checkout_message() {
    if (!\SellSuite\Points::is_enabled()) {
        // Don't show anything
        return;
    }
    
    // Show points info
}
```

---

## Troubleshooting

### Points Still Showing When Disabled

**Check:**
1. Verify setting saved correctly
2. Admin → SellSuite → Settings → Check "Points Enabled" toggle
3. Check admin cache (clear if using cache plugin)
4. Verify `sellsuite_settings['points_enabled']` is false in database

---

### Points Not Showing When Enabled

**Check:**
1. Verify setting is true
2. Check if `Points::is_enabled()` returns true
3. Check if points value > 0
4. Check if product has price
5. Clear any object cache

---

### Can't Toggle Setting

**Check:**
1. User has admin access
2. No PHP errors in error log
3. No option not writable (check file permissions)
4. Database connection working
5. Try without any caching plugins active

---

## Backward Compatibility

### Existing Sites

- ✓ Existing installations unaffected
- ✓ Points enabled by default
- ✓ No database migrations needed
- ✓ No data loss possible
- ✓ Safe to update

### Version History

| Version | Feature | Status |
|---------|---------|--------|
| 1.0.0+ | Points system | ✓ Working |
| 1.1.0+ | Disable/enable | ✓ Added |

---

## Support & Maintenance

### Common Tasks

**Disable system:**
```
Admin → SellSuite → Settings → Points Enabled → OFF → Save
```

**Enable system:**
```
Admin → SellSuite → Settings → Points Enabled → ON → Save
```

**Check if enabled (code):**
```php
\SellSuite\Points::is_enabled() // returns true or false
```

---

## Summary

✅ **Complete Control:**
- Toggle enable/disable with one setting
- Works across entire system
- Affects all 4 display locations
- Prevents new point earning

✅ **Data Safety:**
- Existing points preserved
- No deletions or modifications
- Safe to toggle multiple times
- No cleanup needed

✅ **User Experience:**
- Clean display behavior
- Consistent throughout site
- No confusion with partial system
- Professional presentation

✅ **Performance:**
- Early returns save processing
- Reduced calculations when disabled
- No unnecessary database queries
- Efficient implementation

---

**Status: PRODUCTION READY** ✅

The reward points system can now be completely enabled or disabled with a single setting, while preserving all existing customer data.
