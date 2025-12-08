# Variable Products & Dynamic Points Update - Implementation

**Date:** December 8, 2025  
**Status:** ✅ Complete

---

## Overview

Implemented three new requirements for reward points on variable products:

1. ✅ **Variable Products:** Don't show custom reward points metabox on parent product
2. ✅ **Per-Variation Points:** Each variation can have custom points OR auto-calculated
3. ✅ **Dynamic Display:** Product page updates points when variation is selected

---

## Requirements Implemented

### 1. Variable Products (Parent Product)

**Requirement:** Do NOT show the custom reward points metabox on the parent variable product.

**Implementation:**
- Updated `Product_Meta::render_product_meta_box()` to check product type
- If product is variable → Show message instead of form
- Metabox still registered for all products, but shows conditional message

**File:** `class-sellsuite-product-meta.php` (lines 115-145)

**Code:**
```php
$product = wc_get_product($post->ID);

// Skip for variable products
if ($product && $product->is_type('variable')) {
    echo '<p>' . esc_html__('Reward Points are managed per variation for variable products.', 'sellsuite') . '</p>';
    return;
}
```

---

### 2. Variation Points Rules

**Requirement:** 
- Each variation can have custom reward points
- If variation has custom value → use it
- If variation has NO custom value → calculate (price × points_per_dollar)
- Do NOT use parent product's reward points

**Implementation:**

#### Updated: `Product_Meta::get_variation_points()`
- Now returns 0 if variation has no custom value (instead of falling back to parent)
- Removed: Parent product fallback logic
- Kept: Fixed/percentage type calculation for custom values

**File:** `class-sellsuite-product-meta.php` (lines 45-76)

#### Updated: `Product_Meta::add_variation_options()`
- Removed placeholder="0" to show empty fields by default
- Updated help text to explain fallback behavior

**File:** `class-sellsuite-product-meta.php` (lines 203-223)

**Flow for Variation Points:**
```
1. Check if variation has custom reward points meta
   ├─ YES (and > 0) → Return custom value
   └─ NO → Return 0
   
2. When Points::get_variation_display_points() is called:
   ├─ Get result from get_variation_points() (custom value)
   ├─ If > 0 → Return custom value
   └─ If = 0 → Calculate: floor(variation_price × points_per_dollar)
```

---

### 3. Dynamic Frontend Updates

**Requirement:** When a user selects a variation on the product page, update the displayed reward points dynamically based on:
- Custom variation reward points (if set)
- Calculated reward points (price × points_per_dollar)

**Implementation:**

#### New REST API Endpoint
```
GET /wp-json/sellsuite/v1/products/{variation_id}/points
```

**File:** `class-sellsuite-loader.php` (lines 137-145, 410-450)

**Response:**
```json
{
    "variation_id": 123,
    "points": 50,
    "price": "49.99"
}
```

**Handler Method:**
```php
public function get_variation_points($request) {
    $variation_id = intval($request->get_param('variation_id'));
    
    // Get points using priority logic
    $points = Points::get_variation_display_points($variation_id);
    
    return rest_ensure_response(array(
        'variation_id' => $variation_id,
        'points' => $points,
        'price' => $variation->get_price(),
    ));
}
```

#### New Frontend Script: `variation-points.js`
**File:** `assets/js/variation-points.js` (NEW)

**Features:**
- Listens for variation selection changes
- Calls REST API to get updated points
- Updates the `.sellsuite-product-points` element dynamically
- Uses fade animations for smooth updates
- Handles both WooCommerce variation events

**Key Functions:**
```javascript
init_variation_points()        // Initialize listeners
handle_variation_change()      // Handle selection change
fetch_variation_points()       // Fetch from REST API
update_points_display()        // Update DOM
```

#### Updated: `Frontend::enqueue_scripts()`
**File:** `class-sellsuite-frontend.php` (lines 10-33)

**Changes:**
- Enqueues `variation-points.js` on product pages only
- Localizes script with REST URL and nonce
- Passes `sellsuitePoints` object to JavaScript

**Code:**
```php
if (is_product()) {
    wp_enqueue_script(
        'sellsuite-variation-points',
        SELLSUITE_PLUGIN_URL . 'assets/js/variation-points.js',
        array('jquery'),
        SELLSUITE_VERSION,
        true
    );

    wp_localize_script('sellsuite-variation-points', 'sellsuitePoints', array(
        'restUrl' => rest_url('sellsuite/v1/products'),
        'nonce' => wp_create_nonce('wp_rest'),
    ));
}
```

---

## Data Flow

### Product Page Load - Variable Product

```
Page Loads
    ↓
Frontend::enqueue_scripts() called
    ↓
is_product() check passes
    ↓
variation-points.js enqueued
    ↓
sellsuitePoints object passed to JS:
    ├─ restUrl: "https://site.com/wp-json/sellsuite/v1/products"
    └─ nonce: [security token]
    ↓
JavaScript ready (init_variation_points)
    ↓
Display initial points (if any)
```

### User Selects Variation

```
User clicks variation dropdown
    ↓
woocommerce_variation_select event fired
    ↓
variation-points.js listener triggered
    ↓
Get variation_id from form
    ↓
fetch(restUrl + "/" + variation_id + "/points")
    ↓
REST API handler called:
    get_variation_points($request)
    ↓
Points::get_variation_display_points($variation_id)
    ├─ Check custom value
    ├─ If yes → return custom
    └─ If no → calculate (price × PPD)
    ↓
Return JSON with points
    ↓
JavaScript updates .sellsuite-product-points
    ↓
User sees updated points
```

---

## Database Changes

**None.** All changes use existing meta keys:
- `_reward_points_value` - Custom points for product/variation
- `_reward_points_type` - Type: fixed or percentage

---

## API Documentation

### GET `/wp-json/sellsuite/v1/products/{variation_id}/points`

**Public Endpoint** - No authentication required

**Parameters:**
- `variation_id` (int, required) - Product variation ID

**Response:**
```json
{
    "variation_id": 456,
    "points": 50,
    "price": "49.99"
}
```

**Error Responses:**
```json
// Invalid variation ID
{
    "code": "invalid_variation",
    "message": "Invalid variation ID",
    "data": {"status": 400}
}

// Variation not found
{
    "code": "not_found",
    "message": "Variation not found",
    "data": {"status": 404}
}
```

**Example Request:**
```javascript
fetch('/wp-json/sellsuite/v1/products/456/points')
  .then(r => r.json())
  .then(data => console.log(data.points))
```

---

## Files Modified

| File | Changes | Type |
|------|---------|------|
| `class-sellsuite-product-meta.php` | Updated get_variation_points(), render metabox, add_variation_options() | Modified |
| `class-sellsuite-loader.php` | Added REST route + handler for variation points | Modified |
| `class-sellsuite-frontend.php` | Updated enqueue_scripts() for variation-points.js | Modified |
| `assets/js/variation-points.js` | NEW - Dynamic update script | Created |

---

## Code Examples

### Get Variation Points (Backend)

```php
// Get variation points with priority logic
$points = Points::get_variation_display_points(456);

// If custom value is set: returns custom value
// If not set: returns floor(variation_price × points_per_dollar)

echo "Earn $points points";
```

### Get Variation Points (Frontend AJAX)

```javascript
// Fetch variation points via REST API
fetch(window.sellsuitePoints.restUrl + '/456/points')
  .then(r => r.json())
  .then(data => {
    console.log('Points:', data.points);
    console.log('Price:', data.price);
  });
```

### HTML Elements

**Product Points Display:**
```html
<div class="sellsuite-product-points">
    <p class="points-badge">
        <i class="fas fa-star"></i>
        Earn <strong>50 Reward Points</strong> with this purchase
    </p>
</div>
```

This element is updated dynamically when variation is selected.

---

## Testing

### Test Case 1: Variable Product with Variation Custom Points

**Setup:**
- Product ID: 100 (variable)
- Variation 1: Price $25, Custom Points: 75
- Global PPD: 1

**Steps:**
1. Go to product page
2. Select Variation 1
3. Observe points display

**Expected:**
- Points show: 75
- Reason: Custom value used

---

### Test Case 2: Variable Product without Variation Custom Points

**Setup:**
- Product ID: 100 (variable)
- Variation 1: Price $25, Custom Points: (empty)
- Global PPD: 1

**Steps:**
1. Go to product page
2. Select Variation 1
3. Observe points display

**Expected:**
- Points show: 25 (calculated)
- Calculation: floor(25 × 1) = 25

---

### Test Case 3: Different PPD Values

**Setup:**
- Product ID: 100 (variable)
- Variation 1: Price $50, Custom Points: (empty)
- Global PPD: 2

**Steps:**
1. Go to product page
2. Select Variation 1
3. Observe points
4. Admin: Change PPD to 3
5. Frontend: Select variation again
6. Observe updated points

**Expected:**
1. First display: 100 points (50 × 2)
2. After PPD change: 150 points (50 × 3)

---

### Test Case 4: Admin Panel - Product Edit

**Variable Product:**
1. Go to product edit
2. Check: Metabox shows message (not form)
3. Expected: "Reward Points are managed per variation"

**Variation Settings:**
1. Scroll to variation section
2. Check: "Reward Points" field is empty (no default/placeholder)
3. Can enter custom value or leave empty
4. Save variation

---

## JavaScript API

### `variation-points.js` Functions

#### `init_variation_points()`
Initializes the variation points handler.

```javascript
init_variation_points();
```

**Called:** Automatically on document ready

#### `handle_variation_change()`
Handles variation selection change event.

```javascript
handle_variation_change();
```

**Called:** When variation selection changes

#### `fetch_variation_points(variation_id)`
Fetches points from REST API.

```javascript
fetch_variation_points(456);
```

**Parameters:**
- `variation_id` (number) - Variation product ID

#### `update_points_display(points)`
Updates the points display on the page.

```javascript
update_points_display(50);
```

**Parameters:**
- `points` (number) - Number of reward points

**Behavior:**
- If points = 0: Fades out the display
- If points > 0: Fades in/updates the display

---

## Integration Points

### WooCommerce Hooks Used

**Frontend:**
- `wp_enqueue_scripts` - Load script on product pages
- `woocommerce_variation_select` - Detect variation changes

**Admin:**
- `add_meta_box` - Show/hide metabox based on product type

### Custom Hooks

None added in this update.

---

## Performance Considerations

### REST API
- **Public endpoint:** No auth required (safe)
- **Caching:** No caching applied (data may be dynamic with PPD)
- **Queries:** 1 query per request (product fetch + meta)

### JavaScript
- **Event listeners:** Efficient jQuery event delegation
- **API calls:** Only when variation changes
- **DOM updates:** Minimal - only updates relevant element

### Database
- **No new tables created**
- **No migrations needed**
- **Uses existing meta keys**

---

## Troubleshooting

### Points Don't Update on Variation Change

**Check:**
1. Is `variation-points.js` enqueued?
   - Check browser console for errors
   - Check page source for script tag

2. Does `sellsuitePoints` object exist?
   - Check browser console: `console.log(window.sellsuitePoints)`

3. Is REST API accessible?
   - Test URL: `https://site.com/wp-json/sellsuite/v1/products/123/points`

### Always Shows 0 Points

**Check:**
1. Is variation price set?
   - Variation must have price > 0

2. What is Points Per Dollar?
   - Go to Admin → Settings → Point Management → General
   - Check "Points Per Dollar" value

3. Does variation have custom value?
   - Edit variation → Check "Reward Points" field

---

## Migration Notes

### For Existing Variable Products

**If variable product had parent custom points before:**
- Parent points are now ignored
- Add custom values to variations instead
- Variations without custom values will auto-calculate

**How to migrate:**
1. Get parent product's custom points value
2. Add that value to variations (if you want)
3. Or leave variations empty and they'll auto-calculate

---

## Security

### REST Endpoint
- **Authentication:** Public (no auth check)
- **Validation:** Checks variation ID is valid product
- **Data:** Only returns public information (points, price)
- **Nonce:** Optional header (not required)

### JavaScript
- **Nonce included:** Yes (for future security)
- **Data validation:** Input sanitized (intval on ID)
- **XSS prevention:** jQuery and proper escaping

---

## Summary

✅ **Variable Products:** Parent metabox hidden, message shown  
✅ **Variations:** Each has own points field, no default value  
✅ **Calculation:** Custom first, then auto-calc (price × PPD)  
✅ **Dynamic Updates:** REST API + JavaScript for live updates  
✅ **Performance:** Minimal queries, efficient DOM updates  
✅ **Security:** Public data only, proper validation  

**Status:** PRODUCTION READY ✅
