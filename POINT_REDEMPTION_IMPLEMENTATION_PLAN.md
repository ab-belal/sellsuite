# 🎯 SellSuite Point Redemption Feature - Implementation Plan

## 📋 Overview

This document provides a **keyword-based step-by-step implementation plan** for adding point redemption functionality to the checkout page. The plugin already has the backend `Redeem_Handler` class, so we'll focus on **frontend integration**.

### Current State
✅ Backend: `Redeem_Handler` class (fully functional)  
✅ Database: Point redemptions table exists  
✅ Validation: All server-side validations in place  
❌ Frontend: Checkout redemption UI needs implementation  
❌ Frontend: Real-time point calculation needs implementation  
❌ Order Meta: Redemption display in order details needs implementation  

---

## 🏗️ Architecture Overview

### Existing Components
```
Backend:
├── class-sellsuite-points-manager.php
│   ├── get_available_balance($user_id)
│   ├── get_earned_points($user_id)
│   ├── get_pending_points($user_id)
│   ├── get_settings() → conversion_rate, max_redeemable_percentage
│   └── is_points_enabled()
│
├── class-sellsuite-redeem-handler.php
│   ├── redeem_points($user_id, $points, $order_id, $options)
│   │   ├── Validation: user, points system, points amount
│   │   ├── Validation: sufficient balance
│   │   ├── Validation: order redemption limits
│   │   ├── Creates: redemption record (wp_sellsuite_point_redemptions)
│   │   ├── Creates: ledger entry (wp_sellsuite_points_ledger)
│   │   └── Returns: success, message, redemption_id, discount_value
│   ├── cancel_redemption($redemption_id)
│   └── validate_order_redemption($order_id, $discount_value, $settings)
│
├── class-sellsuite-loader.php
│   └── REST API: POST /wp-json/sellsuite/v1/redeem-points
│
└── Database Tables:
    ├── wp_sellsuite_points_ledger (tracks all point movements)
    └── wp_sellsuite_point_redemptions (tracks redemptions)

Frontend:
├── templates/woocommerce/checkout/
│   ├── order-review.php (EXISTING - shows order items)
│   └── point-redemption.php (NEEDS CREATION)
│
├── public/assets/js/
│   ├── checkout.js (NEEDS UPDATE for real-time calculation)
│   └── point-redemption.js (NEEDS CREATION)
│
└── Hooks:
    └── woocommerce_review_order_after_shipping (NEEDS HOOK)
```

---

## 📊 User Flow

```
Customer at Checkout
      │
      ▼
Display Order Details (existing)
      │
      ▼
[NEW] Display Point Redemption Box
      │
      ├─ Show: Available Points
      ├─ Show: Available Balance after redeem
      ├─ Show: Discount amount (real-time)
      │
      ▼
Customer inputs points to redeem
      │
      ├─ [REAL-TIME] Calculate discount (points × conversion_rate)
      ├─ [REAL-TIME] Update available balance
      ├─ [VALIDATION] Check max_redeemable_percentage
      │
      ▼
Customer clicks "Apply Redemption"
      │
      ├─ [AJAX] Send to backend
      ├─ [BACKEND] Validate all
      ├─ [BACKEND] Redeem_Handler::redeem_points()
      ├─ [SESSION] Store redemption_id
      │
      ▼
[NEW] Display Redemption in Order Review
      │
      ├─ Show: "Point Redemption: -50 points"
      ├─ Show: Discount: -$50
      └─ Show: [✕] Cancel button
      │
      ▼
Order Total Updated (with discount)
      │
      ▼
Customer Places Order
      │
      ├─ [BACKEND] Points deducted from balance
      ├─ [BACKEND] Ledger entry created: "redeemed"
      ├─ [BACKEND] Pending redemption until order completed
      │
      ▼
Order Completed
      │
      ├─ [BACKEND] Points moved from "redeemed" to "earned"
      └─ [DASHBOARD] Shows in "Points Redeemed" box

```

---

## 🔧 Implementation Keywords

Use these keywords to trigger each implementation step. Say the keyword to execute that step.

### PHASE 1: Frontend UI Implementation

#### Keyword: `INIT_REDEEM_BOX`
**Description:** Create point redemption box component on checkout

**Creates:**
- File: `templates/woocommerce/checkout/point-redemption.php`
- HTML structure with:
  - Available points display
  - Input field for points to redeem
  - Real-time discount display
  - Apply button
  - Cancel button

**After Execution:** Check `templates/woocommerce/checkout/`

---

#### Keyword: `INIT_REDEEM_SCRIPT`
**Description:** Create JavaScript for real-time calculation

**Creates:**
- File: `public/assets/js/src/point-redemption.js`
- Features:
  - Real-time point input validation
  - Real-time discount calculation
  - Available balance update
  - Max redeemable check
  - AJAX submission to backend
  - Error handling

**After Execution:** Check `public/assets/js/src/`

---

#### Keyword: `HOOK_REDEEM_BOX`
**Description:** Hook redemption box into checkout

**Updates:**
- File: `includes/class-sellsuite-frontend-display.php`
- Adds: Hook to `woocommerce_review_order_after_shipping`
- Action: Display point redemption box

**After Execution:** Redemption box appears after order details on checkout

---

### PHASE 2: Backend API & Validation

#### Keyword: `VERIFY_REDEEM_API`
**Description:** Verify REST API endpoint exists

**Checks:**
- Endpoint: `POST /wp-json/sellsuite/v1/redeem-points`
- Location: `class-sellsuite-loader.php`
- Status: Should already exist

**If Missing:** Will create it

---

#### Keyword: `ADD_REDEEM_VALIDATION`
**Description:** Add/verify client-side and server-side validation

**Validation List:**
- ✅ User authentication (server)
- ✅ User belongs to order (server)
- ✅ Sufficient redeemable points (server)
- ✅ Points are integers, positive (server)
- ✅ Conversion rate calculation valid (server)
- ✅ Max redeemable percentage check (server)
- ✅ Cannot exceed order subtotal (server)
- ✅ No double applying (session/order meta)
- ✅ Client-side min/max validation (frontend)
- ✅ Real-time feedback (frontend)

**Status:** Most validation exists in `Redeem_Handler`

---

### PHASE 3: Order Display & Integration

#### Keyword: `ADD_REDEMPTION_ROW`
**Description:** Add redemption row to order review table

**Updates:**
- File: `templates/woocommerce/checkout/order-review.php` or create hooks
- Shows:
  - "Points Redeemed: -50"
  - Discount amount: "-$50"
  - Cancel button (✕)

**After Execution:** Redemption shows in checkout review table

---

#### Keyword: `ADD_CANCEL_BUTTON`
**Description:** Add cancel redemption functionality

**Features:**
- Button: [✕] next to redemption row
- Action: AJAX call to cancel
- Backend: `Redeem_Handler::cancel_redemption()`
- Frontend: Remove from order, restore available points
- Updates: Order total, available balance

**After Execution:** Users can cancel redemption before checkout

---

### PHASE 4: Order Processing

#### Keyword: `HANDLE_REDEMPTION_ON_ORDER`
**Description:** Process redemption when order is placed

**Updates:**
- File: `includes/class-sellsuite-order-handler.php`
- Actions:
  - Retrieve session/stored redemption_id
  - Deduct points from available balance
  - Create ledger entry: status = "redeemed"
  - Store in order meta: _points_redeemed
  - Calculate discount value

**After Execution:** Points deducted when order placed

---

#### Keyword: `HANDLE_REDEMPTION_ON_COMPLETE`
**Description:** Process redemption when order completes

**Updates:**
- File: `includes/class-sellsuite-order-handler.php`
- Actions:
  - Get redeemed points from order meta
  - Update ledger: status "redeemed" → "earned"
  - Update order meta: completion timestamp
  - Trigger action: `sellsuite_redemption_completed`

**After Execution:** Points move from "redeemed" to "earned" on order completion

---

#### Keyword: `HANDLE_REDEMPTION_ON_REFUND`
**Description:** Process redemption when order refunded

**Updates:**
- File: `includes/class-sellsuite-refund-handler.php`
- Actions:
  - Get redeemed points from order meta
  - Restore points to available balance
  - Create ledger entry: action = "redemption_reversal"
  - Status: "earned"

**After Execution:** Points restored if order refunded

---

### PHASE 5: Dashboard Display

#### Keyword: `ADD_DASHBOARD_BOXES`
**Description:** Update customer dashboard with redemption stats

**Updates:**
- File: `templates/woocommerce/myaccount/dashboard.php`
- Adds boxes:
  - "Available Points" (existing)
  - "Points Earned" (existing)
  - "Pending Redemption" (NEW) - points waiting for order completion
  - "Redeemed Points" (NEW) - total redeemed

**After Execution:** Dashboard shows redemption status

---

#### Keyword: `ADD_REDEMPTION_HISTORY`
**Description:** Add redemption history to dashboard

**Updates:**
- File: `templates/woocommerce/myaccount/dashboard.php` or new template
- Shows:
  - Date of redemption
  - Points redeemed
  - Discount value
  - Status (Pending / Completed / Cancelled)
  - Order number

**After Execution:** Customers can see redemption history

---

### PHASE 6: Testing & Documentation

#### Keyword: `TEST_REDEMPTION`
**Description:** Test full redemption flow

**Tests:**
1. Display redemption box on checkout
2. Real-time calculation updates
3. Submit redemption
4. Redemption appears in order review
5. Place order
6. Points deducted from balance
7. Complete order
8. Points move to "redeemed"
9. Dashboard updates
10. Cancel redemption (before order)
11. Refund order
12. Points restored

---

#### Keyword: `DOCUMENT_REDEMPTION`
**Description:** Generate API documentation

**Creates:**
- File: `POINT_REDEMPTION_GUIDE.md`
- Content:
  - Feature overview
  - User flow diagrams
  - API endpoints
  - Database schema
  - Error codes
  - Examples

---

---

## 📱 Settings Available (Already in Backend)

```php
// Conversion Rate (points to currency)
$settings['conversion_rate'] = 1;  // 1 point = 1 currency unit (e.g., 1 point = 1 taka)

// Max Redeemable Percentage
$settings['max_redeemable_percentage'] = 20;  // Max 20% of order can be paid with points

// Points Enabled
$settings['points_enabled'] = true;

// WooCommerce Currency
get_woocommerce_currency();        // e.g., "BDT"
get_woocommerce_currency_symbol(); // e.g., "৳"
```

---

## 🔑 Key Functions Reference

### Backend Functions (Already Exist)

```php
// Get user's available balance
Points::get_available_balance($user_id);  // Returns: int

// Get user's earned points
Points::get_earned_points($user_id);  // Returns: int

// Get user's pending points
Points::get_pending_points($user_id);  // Returns: int

// Redeem points
Redeem_Handler::redeem_points(
    $user_id,
    $points,
    $order_id,  // 0 if not for specific order
    $options    // array('conversion_rate' => 1, 'currency' => 'BDT')
);  // Returns: array with success, message, redemption_id, discount_value

// Cancel redemption
Redeem_Handler::cancel_redemption($redemption_id);  // Returns: array

// Validate order redemption
Redeem_Handler::validate_order_redemption($order_id, $discount_value, $settings);
```

---

## 📝 Database Schema (Already Exists)

### Point Redemptions Table: `wp_sellsuite_point_redemptions`
```sql
id                  (bigint) - Primary key
user_id             (bigint) - User ID
order_id            (bigint) - Order ID
ledger_id           (bigint) - Ledger entry ID
redeemed_points     (int)    - Number of points redeemed
discount_value      (decimal)- Currency discount amount
conversion_rate     (decimal)- Rate used (1 point = X currency)
currency            (varchar)- Currency code (e.g., "BDT")
created_at          (datetime)- When redeemed
notes               (text)   - Any notes
```

### Points Ledger Table: `wp_sellsuite_points_ledger`
```sql
id                  (bigint) - Primary key
user_id             (bigint) - User ID
order_id            (bigint) - Order ID
action_type         (varchar)- "redemption", "redemption_reversal", etc.
points_amount       (int)    - Number of points
status              (varchar)- "earned", "redeemed", "pending", etc.
description         (text)   - Description
created_at          (datetime)- When created
expires_at          (datetime)- Expiry date (if applicable)
```

---

## 🎨 UI Mockup

```
═══════════════════════════════════════════════════════════
                   ORDER DETAILS
───────────────────────────────────────────────────────────
Product 1                           $100.00
Product 2                            $50.00
                          ─────────────────
Subtotal                            $150.00
Shipping                             $10.00
Tax                                  $16.00
                          ═════════════════
Order Total                         $176.00
═══════════════════════════════════════════════════════════

═══════════════════════════════════════════════════════════
              [NEW] REDEEM POINTS BOX
───────────────────────────────────────────────────────────
Available Points:  1000 pts

How many points to redeem?
┌─────────────────────────────────────┐
│ 500                                 │  [Apply Redemption]
└─────────────────────────────────────┘

Real-time Calculation:
  500 points × 1 (conversion rate) = ৳ 500.00 discount
  Available after: 500 points

Max Redeemable: ৳ 35.20 (20% of ৳176.00)
⚠️  Maximum redeemable is ৳35.20. You can redeem up to 36 points.

[✓ Apply Redemption]
═══════════════════════════════════════════════════════════

═══════════════════════════════════════════════════════════
                   ORDER DETAILS (UPDATED)
───────────────────────────────────────────────────────────
Product 1                           $100.00
Product 2                            $50.00
                          ─────────────────
Subtotal                            $150.00
Shipping                             $10.00
Tax                                  $16.00
Point Redemption (35 pts)           -$35.00  [✕]
                          ═════════════════
Order Total                         $141.00
═══════════════════════════════════════════════════════════
```

---

## ✅ Execution Checklist

When you run each keyword, check off the corresponding item:

- [ ] `INIT_REDEEM_BOX` - Checkout UI created
- [ ] `INIT_REDEEM_SCRIPT` - JavaScript created
- [ ] `HOOK_REDEEM_BOX` - Hooked to checkout
- [ ] `VERIFY_REDEEM_API` - REST API verified
- [ ] `ADD_REDEEM_VALIDATION` - All validation in place
- [ ] `ADD_REDEMPTION_ROW` - Shows in order review
- [ ] `ADD_CANCEL_BUTTON` - Can cancel redemption
- [ ] `HANDLE_REDEMPTION_ON_ORDER` - Points deducted on order
- [ ] `HANDLE_REDEMPTION_ON_COMPLETE` - Points marked as earned
- [ ] `HANDLE_REDEMPTION_ON_REFUND` - Points restored on refund
- [ ] `ADD_DASHBOARD_BOXES` - Dashboard updated
- [ ] `ADD_REDEMPTION_HISTORY` - History visible
- [ ] `TEST_REDEMPTION` - Full flow tested
- [ ] `DOCUMENT_REDEMPTION` - Documentation created

---

## 🚀 How to Use This Plan

### To Execute Step 1:
```
Say/Type: "INIT_REDEEM_BOX"
Then I will:
  1. Create the checkout redemption box template
  2. Add all necessary HTML elements
  3. Add CSS styling
  4. Confirm completion
```

### To Execute Step 2:
```
Say/Type: "INIT_REDEEM_SCRIPT"
Then I will:
  1. Create the JavaScript file
  2. Add real-time calculation logic
  3. Add AJAX submission
  4. Add error handling
  5. Confirm completion
```

### And so on for each keyword...

---

## 📞 Quick Reference

### Current Status
- **Backend:** ✅ Complete (Redeem_Handler exists)
- **Database:** ✅ Complete (tables created)
- **Settings:** ✅ Complete (conversion_rate, max_redeemable_percentage)
- **Frontend:** ❌ Not started

### Next Action
Ready when you are! Say any keyword above to begin implementation.

### Expected Timeline
- **PHASE 1 (UI):** 2 steps, ~30 minutes
- **PHASE 2 (API):** 2 steps, ~20 minutes
- **PHASE 3 (Order Display):** 2 steps, ~30 minutes
- **PHASE 4 (Order Processing):** 3 steps, ~40 minutes
- **PHASE 5 (Dashboard):** 2 steps, ~30 minutes
- **PHASE 6 (Testing):** 2 steps, ~45 minutes
- **Total:** ~3 hours for complete implementation

---

**Ready to start? Say one of the keywords to begin!** 🚀
