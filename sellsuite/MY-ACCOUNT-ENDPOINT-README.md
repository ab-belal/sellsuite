# WooCommerce My Account Endpoint - Implementation Guide

## Overview
This implementation adds a custom **"Products Info"** endpoint to the WooCommerce My Account page that displays a table with product information. The content is separated into a template file for better organization and maintainability.

**Security Features:**
- ✅ Checks if user is logged in (double layer security)
- ✅ Checks if user has `product_viewer` capability
- ✅ Direct file access prevention in template

---

## 📍 Where the Code Was Placed

### 1. **Frontend Class** (Controller Logic)
**File**: `includes/class-sellsuite-frontend.php`

#### Methods Added:

**a) `add_products_info_endpoint()`**
- **Hook**: `init`
- **Purpose**: Registers the custom endpoint with WordPress
- **What it does**: Adds the `products-info` endpoint to the rewrite rules

**b) `add_products_info_menu_item($items)`**
- **Hook**: `woocommerce_account_menu_items` (filter)
- **Purpose**: Adds the menu item to My Account navigation
- **What it does**: Inserts "Products" menu link after "Orders"

**c) `products_info_endpoint_content()`**
- **Hook**: `woocommerce_account_products-info_endpoint`
- **Purpose**: Loads the template file for the endpoint
- **What it does**: 
  - **Security**: Checks if user is logged in (first layer)
  - Shows login button if not authenticated
  - Includes the template file
  - Shows error if template is missing

**d) `products_info_endpoint_title($title)`**
- **Hook**: `the_title` (filter)
- **Purpose**: Sets the page title for the endpoint
- **What it does**: Changes the title to "Products Info" when on the endpoint page

---

### 2. **Template File** (View/Display Logic)
**File**: `templates/woocommerce/myaccount-products-info.php`

This is where all the HTML and display logic lives:

**Security Checks:**
- Direct access prevention (`!defined('ABSPATH')`)
- Logged-in user check (`is_user_logged_in()`)
- Capability check (`current_user_can('product_viewer')`)

**Content:**
- Dummy product data array
- HTML table with 6 columns (ID, Name, SKU, Price, Stock, Category)
- 3 dummy product rows
- Action hooks for extensibility

**Why separate template?**
- ✅ Cleaner code organization
- ✅ Easier to customize
- ✅ Follows WordPress template hierarchy pattern
- ✅ Can be overridden by themes (future feature)
- ✅ Better maintainability

### 2. **Loader Class** (Hook Registration)
**File**: `includes/class-sellsuite-loader.php`

In the `define_frontend_hooks()` method, these hooks were registered:

```php
// Register the endpoint
$this->add_action('init', $frontend, 'add_products_info_endpoint');

// Add menu item
$this->add_filter('woocommerce_account_menu_items', $frontend, 'add_products_info_menu_item');

// Display content
$this->add_action('woocommerce_account_products-info_endpoint', $frontend, 'products_info_endpoint_content');

// Set title
$this->add_filter('the_title', $frontend, 'products_info_endpoint_title');
```

---

### 3. **Activator Class** (Plugin Activation)
**File**: `includes/class-sellsuite-activator.php`

Added endpoint registration before flushing rewrite rules:

```php
// Register custom endpoints before flushing rewrite rules
add_rewrite_endpoint('products-info', EP_ROOT | EP_PAGES);

// Flush rewrite rules
flush_rewrite_rules();
```

This ensures the endpoint is available immediately after plugin activation.

---

### 4. **Styling**
**File**: `assets/css/frontend.css`

Added CSS styles for:
- Table layout and styling
- Responsive mobile view
- Hover effects
- Proper spacing and borders

---

## 🚀 How It Works

### Architecture Overview
```
User Request → Frontend Class → Security Check → Template File → Display
```

### Step 1: Endpoint Registration
When the plugin loads, the `add_products_info_endpoint()` method registers a new endpoint:
- **URL slug**: `products-info`
- **Full URL**: `yoursite.com/my-account/products-info/`

### Step 2: Menu Addition
The `add_products_info_menu_item()` filter adds a menu link in the My Account sidebar navigation.

### Step 3: Security Checks (Double Layer)
When a user clicks the "Products" menu item:

**First Layer** (in `class-sellsuite-frontend.php`):
1. ✅ Checks if user is logged in with `is_user_logged_in()`
2. If NO → Shows login button
3. If YES → Loads template file

**Second Layer** (in template file):
1. ✅ Prevents direct file access
2. ✅ Checks if user is logged in again
3. ✅ Checks if user has `product_viewer` capability
4. If NO capability → Shows access denied message
5. If YES → Displays product table

### Step 4: Data Display
The template displays 3 dummy product rows with columns:
- ID, Name, SKU, Price, Stock, Category

---

## 🔐 Security Features

### Why Check `is_user_logged_in()` in Both Places?

**YES, it's necessary and recommended for security:**

1. **Defense in Depth**: Multiple layers of security
2. **Template Protection**: If someone directly accesses the template file
3. **Best Practice**: WordPress recommendation for endpoint security
4. **Prevents Direct Access**: Even if rewrite rules fail

### Security Layers:

```
Layer 1: is_user_logged_in() in Frontend class
    ↓
Layer 2: !defined('ABSPATH') in template (prevents direct access)
    ↓
Layer 3: is_user_logged_in() in template
    ↓
Layer 4: current_user_can('product_viewer') in template
    ↓
Display Content
```

### What Each Security Check Does:

| Check | Location | Purpose |
|-------|----------|---------|
| `!defined('ABSPATH')` | Template | Prevents direct URL access to PHP file |
| `is_user_logged_in()` | Frontend | First authentication barrier |
| `is_user_logged_in()` | Template | Secondary authentication barrier |
| `current_user_can('product_viewer')` | Template | Authorization check |

---

## 🎯 How to Test

### Test 1: Activate the Plugin
1. Go to: `WordPress Admin → Plugins`
2. Activate or Reactivate "SellSuite"
3. This flushes rewrite rules and registers the endpoint

### Test 2: Check Menu Appears
1. Login as a user with WooCommerce account
2. Go to: `My Account` page
3. Look in the left sidebar navigation
4. You should see **"Products Info"** menu item after "Orders"

### Test 3: Test with Product Viewer Role
1. Create a user with `product_viewer` role (or assign to existing user)
2. Login as that user
3. Go to: `My Account → Products Info`
4. You should see the table with 3 dummy products

### Test 4: Test without Permission
1. Create a regular customer account
2. Login as customer
3. Go to: `My Account → Products Info`
4. You should see: "You do not have permission to view product information."

### Test 5: Test as Administrator
1. Login as administrator
2. Go to: `My Account → Products Info`
3. You should see the table (admins have all capabilities)

---

## 📋 The Dummy Data Structure

Current dummy data in the code:

```php
$dummy_products = array(
    array(
        'id'       => 101,
        'name'     => 'Wireless Bluetooth Headphones',
        'sku'      => 'WBH-2024-001',
        'price'    => '$79.99',
        'stock'    => 'In Stock (45)',
        'category' => 'Electronics',
    ),
    array(
        'id'       => 102,
        'name'     => 'Organic Cotton T-Shirt',
        'sku'      => 'OCT-2024-155',
        'price'    => '$24.99',
        'stock'    => 'Low Stock (8)',
        'category' => 'Clothing',
    ),
    array(
        'id'       => 103,
        'name'     => 'Stainless Steel Water Bottle',
        'sku'      => 'SSWB-2024-089',
        'price'    => '$15.99',
        'stock'    => 'In Stock (120)',
        'category' => 'Home & Kitchen',
    ),
);
```

---

## 🔧 How to Customize

### Modify the Dummy Data
**File**: `templates/woocommerce/myaccount-products-info.php`

Find the `$dummy_products` array and modify:

```php
$dummy_products = array(
    array(
        'id'       => 104,
        'name'     => 'Your Product Name',
        'sku'      => 'YOUR-SKU',
        'price'    => '$29.99',
        'stock'    => 'In Stock',
        'category' => 'Your Category',
    ),
    // Add more products...
);
```

### Change the Table Layout
**File**: `templates/woocommerce/myaccount-products-info.php`

Modify the HTML table structure:
- Add/remove columns in `<thead>` and `<tbody>`
- Change CSS classes
- Add custom fields

### Replace with Real Product Data
**File**: `templates/woocommerce/myaccount-products-info.php`

Replace the `$dummy_products` array with actual WooCommerce products:

```php
// Get WooCommerce products
$args = array(
    'post_type' => 'product',
    'posts_per_page' => 10,
    'post_status' => 'publish',
);

$products_query = new WP_Query($args);
$dummy_products = array();

if ($products_query->have_posts()) {
    while ($products_query->have_posts()) {
        $products_query->the_post();
        $product = wc_get_product(get_the_ID());
        
        $dummy_products[] = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku() ?: 'N/A',
            'price' => wc_price($product->get_price()),
            'stock' => $product->is_in_stock() ? 'In Stock' : 'Out of Stock',
            'category' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'))[0] ?? 'N/A',
        );
    }
    wp_reset_postdata();
}
```

### Use Template Hooks
**File**: `templates/woocommerce/myaccount-products-info.php`

The template includes action hooks for extensibility:

```php
// Add content before the table
add_action('sellsuite_before_products_info_table', function() {
    echo '<div class="custom-message">Your custom content</div>';
});

// Add content after the table
add_action('sellsuite_after_products_info_table', function() {
    echo '<div class="custom-footer">Footer content</div>';
});
```

### Override Template in Theme (Future Feature)
You can copy the template to your theme to customize it:

```
your-theme/
└── sellsuite/
    └── woocommerce/
        └── myaccount-products-info.php
```

Then modify the Frontend class to check for theme override first.

---

## 🔒 Security & Permissions

### Authentication vs Authorization

**Authentication**: Who you are (logged in or not)
- Checked with: `is_user_logged_in()`

**Authorization**: What you can do (permissions)
- Checked with: `current_user_can('product_viewer')`

### Multi-Layer Security Model

```
┌─────────────────────────────────────┐
│  User accesses /my-account/products │
└─────────────────┬───────────────────┘
                  │
        ┌─────────▼──────────┐
        │ Is user logged in? │
        └─────────┬──────────┘
                  │ NO → Show login button
                  │ YES → Continue
                  │
        ┌─────────▼──────────┐
        │ Load template file  │
        └─────────┬──────────┘
                  │
        ┌─────────▼──────────┐
        │ Direct access check │
        │ (!defined ABSPATH)  │
        └─────────┬──────────┘
                  │
        ┌─────────▼──────────┐
        │ Is user logged in? │
        │   (double check)    │
        └─────────┬──────────┘
                  │
        ┌─────────▼──────────┐
        │ Has product_viewer  │
        │    capability?      │
        └─────────┬──────────┘
                  │ NO → Access denied
                  │ YES → Show table
                  │
        ┌─────────▼──────────┐
        │  Display Content    │
        └─────────────────────┘
```

### Who Can Access:
- ✅ Users with `product_viewer` role
- ✅ Administrators (have all capabilities)
- ✅ Shop Managers (given capability in activator)
- ❌ Regular customers
- ❌ Subscribers
- ❌ Logged out users

### To Allow All Logged-in Users:
**File**: `templates/woocommerce/myaccount-products-info.php`

Remove the capability check:

```php
// Remove or comment out these lines:
// if (!current_user_can('product_viewer')) {
//     return;
// }
```

Keep the `is_user_logged_in()` check for security.

### To Make Public (Not Recommended):
Remove all authentication/authorization checks (NOT RECOMMENDED for product data).

---

## 📱 Mobile Responsiveness

The table is fully responsive:
- **Desktop**: Standard table layout
- **Mobile**: Each row becomes a card with labels
- Breakpoint: 768px

The CSS handles this automatically via media queries.

---

## 🐛 Troubleshooting

### Problem: Menu item doesn't appear
**Solution**: 
1. Deactivate the plugin
2. Reactivate the plugin
3. Go to Settings → Permalinks → Save Changes

### Problem: 404 error when clicking menu item
**Solution**: Flush rewrite rules
- Go to: Settings → Permalinks → Save Changes
- Or deactivate/reactivate the plugin

### Problem: Access denied for all users
**Solution**: Check that the user has the `product_viewer` capability:
```php
// Check user capabilities
$user = wp_get_current_user();
print_r($user->allcaps);
```

### Problem: Menu appears but in wrong position
**Solution**: Modify the insertion point in `add_products_info_menu_item()` method

---

## ✅ Implementation Checklist

- [x] Endpoint registered in `class-sellsuite-frontend.php`
- [x] Menu item added via filter
- [x] Content display method created
- [x] Capability check implemented
- [x] Hooks registered in loader
- [x] Endpoint added to activator
- [x] CSS styling added
- [x] Dummy data table created
- [x] Mobile responsive design
- [x] Translation ready (using `__()` functions)

---

## 🎓 Key WordPress/WooCommerce Concepts

### Rewrite Endpoints
WordPress uses rewrite endpoints to create clean URLs. The endpoint `products-info` creates the URL pattern: `/my-account/products-info/`

### WooCommerce My Account Hooks
- `woocommerce_account_menu_items` - Filter to modify menu
- `woocommerce_account_{endpoint}_endpoint` - Action for endpoint content
- Must register endpoint with `add_rewrite_endpoint()`

### Capability vs Role
- **Role**: Collection of capabilities (e.g., `product_viewer`)
- **Capability**: Permission to do something (e.g., `product_viewer`)
- Always check capabilities, not roles

---

## 📚 Files Modified Summary

```
sellsuite/
├── includes/
│   ├── class-sellsuite-frontend.php     [MODIFIED] - Controller with security
│   ├── class-sellsuite-loader.php       [MODIFIED] - Hook registration
│   └── class-sellsuite-activator.php    [MODIFIED] - Endpoint registration
├── templates/
│   └── woocommerce/
│       └── myaccount-products-info.php  [NEW] - Template file with table
├── assets/
│   └── css/
│       └── frontend.css                  [MODIFIED] - Table styling
└── MY-ACCOUNT-ENDPOINT-README.md         [UPDATED] - This documentation
```

### File Responsibilities:

| File | Purpose | Contains |
|------|---------|----------|
| `class-sellsuite-frontend.php` | Controller | Endpoint registration, hooks, first auth check |
| `myaccount-products-info.php` | View/Template | HTML, table structure, security checks, data |
| `frontend.css` | Styling | Table CSS, responsive design |
| `class-sellsuite-loader.php` | Bootstrap | Hook registration |
| `class-sellsuite-activator.php` | Activation | Endpoint flush on activation |

---

## 🎉 Next Steps

1. **Test the Implementation**: Follow the testing steps above
2. **Customize as Needed**: Modify menu position, labels, or styling
3. **Replace Dummy Data**: When ready, fetch real product data
4. **Add Features**: Consider pagination, search, filtering
5. **Security**: Ensure proper capability checks remain in place

---

**Implementation Date**: November 24, 2025  
**Plugin**: SellSuite v1.0.0  
**Status**: ✅ Complete and Ready to Test
