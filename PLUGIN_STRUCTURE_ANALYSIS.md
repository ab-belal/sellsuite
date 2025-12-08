# SellSuite Plugin - Complete Structure Analysis

**Date:** December 8, 2025  
**Plugin Version:** 1.0.0  
**Status:** Production Ready with React Admin Dashboard

---

## 📋 Table of Contents

1. [Folder Structure](#folder-structure)
2. [Database Tables](#database-tables)
3. [Settings Storage](#settings-storage)
4. [React Admin Dashboard](#react-admin-dashboard)
5. [PHP Core Classes](#php-core-classes)
6. [REST API Endpoints](#rest-api-endpoints)
7. [Configuration & Build](#configuration--build)

---

## 📁 Folder Structure

```
sellsuite/
├── admin/                                    # React-based admin dashboard
│   ├── src/
│   │   ├── index.jsx                        # React entry point
│   │   ├── App.jsx                          # Main React app component
│   │   └── pages/
│   │       ├── SellsuiteSettings.jsx        # Main settings container (tabs navigation)
│   │       └── settings/
│   │           ├── GeneralSettings.jsx      # General settings tab
│   │           ├── PointManagement.jsx      # Point management main container
│   │           └── PointManagement/
│   │               ├── General.jsx          # General point settings
│   │               ├── EarningPoints.jsx    # Points earning configuration
│   │               ├── RedeemPoints.jsx     # Points redemption configuration
│   │               └── PointExpiry.jsx      # Points expiry settings
│   ├── assets/
│   │   ├── css/                            # Compiled CSS
│   │   ├── scss/                           # SCSS source files
│   │   ├── images/                         # Admin images/icons
│   │   └── js/                             # JavaScript utilities
│   ├── build/                              # Webpack compiled output
│   │   └── app.js                          # Compiled React app
│   ├── webpack.config.js                   # Webpack configuration
│   ├── package.json                        # NPM dependencies
│   └── README.md                           # Admin app documentation
│
├── includes/                               # PHP core classes
│   ├── class-sellsuite-admin.php          # Admin menu & page rendering
│   ├── class-sellsuite-admin-handler.php  # Admin operations & auditing
│   ├── class-sellsuite-admin-points-table.php  # Points table display
│   ├── class-sellsuite-activator.php      # Plugin activation & DB setup
│   ├── class-sellsuite-dashboard.php      # Dashboard data retrieval
│   ├── class-sellsuite-dashboard-data.php # User_Dashboard_Data class
│   ├── class-sellsuite-deactivator.php    # Plugin deactivation
│   ├── class-sellsuite-frontend.php       # Frontend hooks & endpoints
│   ├── class-sellsuite-frontend-display.php # Frontend display
│   ├── class-sellsuite-loader.php         # Plugin loader & REST routes
│   ├── class-sellsuite-order-handler.php  # Order lifecycle management
│   ├── class-sellsuite-points-manager.php # Points_Manager class (named Points)
│   ├── class-sellsuite-product-meta.php   # Product meta handling
│   ├── class-sellsuite-product-renderer.php # Product renderer
│   ├── class-sellsuite-woocommerce.php    # WooCommerce integration
│   ├── class-sellsuite-customers.php      # Customer management
│   ├── class-sellsuite-currency-handler.php # Currency conversion
│   ├── class-sellsuite-refund-handler.php # Refund processing
│   ├── class-sellsuite-redeem-handler.php # Points redemption
│   ├── class-sellsuite-expiry-handler.php # Points expiry
│   ├── class-sellsuite-notification-handler.php # Email notifications
│   ├── class-sellsuite-email-templates.php # Email template management
│   └── helpers.php                        # Helper functions
│
├── public/                                # Public-facing functionality
│   └── [assets & templates]
│
├── templates/                             # Template files
│   └── woocommerce/myaccount/
│       └── dashboard.php                 # User dashboard template
│
├── sellsuite.php                         # Main plugin file
├── gulpfile.js                          # Build automation
├── webpack.config.js                    # Webpack build config
├── package.json                         # Project npm dependencies
└── [Documentation files]
```

---

## 🗄️ Database Tables

### SellSuite uses 10 custom database tables:

| Table Name | Purpose | Key Fields |
|------------|---------|-----------|
| **wp_sellsuite_points_ledger** | Main points transaction log | id, user_id, order_id, product_id, points_amount, status, action_type, created_at |
| **wp_sellsuite_redemptions** | Points redemption records | id, user_id, order_id, points_redeemed, discount_applied, status, created_at |
| **wp_sellsuite_old_points** | Legacy points data | id, user_id, points_balance, created_at |
| **wp_sellsuite_notifications** | Notification templates | id, event_type, subject, body, status, created_at |
| **wp_sellsuite_notification_logs** | Sent notifications history | id, user_id, notification_id, sent_at, status |
| **wp_sellsuite_audit_log** | Admin action audit trail | id, admin_id, user_id, action, points, reason, created_at |
| **wp_sellsuite_point_expirations** | Points expiry tracking | id, user_id, ledger_id, expiry_date, status |
| **wp_sellsuite_expiry_rules** | Expiry rule configuration | id, rule_type, days, status, created_at |
| **wp_sellsuite_exchange_rates** | Currency exchange rates | id, from_currency, to_currency, rate, updated_at |
| **wp_sellsuite_currencies** | Supported currencies | id, code, symbol, name, status, created_at |

---

## 💾 Settings Storage

### **Primary Settings Option: `sellsuite_settings`**

**Database:** `wp_options` table  
**Option Key:** `sellsuite_settings`  
**Storage Type:** Serialized PHP array

### Default Settings Structure:

```php
array(
    'points_enabled' => true,                      // Enable/disable points system
    'conversion_rate' => 1,                        // 1 point = X currency units
    'max_redeemable_percentage' => 20,             // Max % of order that can use points
    'enable_expiry' => false,                      // Enable points expiry
    'expiry_days' => 365,                          // Days until points expire
    'point_calculation_method' => 'fixed',         // 'fixed' or 'percentage'
    'points_per_dollar' => 1,                      // Points earned per dollar (fixed method)
    'points_percentage' => 0,                      // Points as % of product price (percentage method)
)
```

### Additional Settings Options:

| Option Key | Purpose |
|-----------|---------|
| `sellsuite_from_email` | Email sender address |
| `sellsuite_from_name` | Email sender name |
| `woocommerce_currency` | Current store currency |

### How Settings Are Accessed:

**Getting Settings:**
```php
$settings = get_option('sellsuite_settings', array());
```

**Updating Settings:**
```php
update_option('sellsuite_settings', $settings);
```

**Via REST API:**
```
GET  /wp-json/sellsuite/v1/settings
POST /wp-json/sellsuite/v1/settings
```

---

## ⚛️ React Admin Dashboard

### Architecture

**Location:** `/admin/`  
**Build Tool:** Webpack 5  
**Framework:** React 18.2.0

### Component Hierarchy

```
App.jsx (Root)
└── SellsuiteSettings.jsx (Main Container)
    ├── PointManagement.jsx
    │   ├── General.jsx
    │   ├── EarningPoints.jsx
    │   ├── RedeemPoints.jsx
    │   └── PointExpiry.jsx
    └── GeneralSettings.jsx
```

### Key Features:

1. **Tabbed Interface**
   - Left sidebar with tab navigation
   - Right content area with form controls
   - Icon indicators for each tab

2. **Settings Tabs:**
   - ⭐ **Point Management** - Point earning/redemption configuration
   - ⚙️ **General** - General store settings

3. **Sub-Tabs (Point Management):**
   - General - Enable/disable points system
   - Earning Points - Points earning rules
   - Redeeming Points - Redemption settings
   - Point Expiry - Expiry configuration

4. **Features:**
   - Save functionality with loader state
   - Success/error messages
   - Real-time settings loading from `window.sellsuiteData`
   - REST API integration via wp-json
   - Nonce-protected requests

### Build Process:

**Development:**
```bash
npm install
npm run dev      # Watch mode
npm start        # Watch mode (alias)
```

**Production:**
```bash
npm run build    # Minified build
```

**Output:** `/admin/build/app.js` (Webpack bundle)

### Enqueuing in WordPress:

The React app is enqueued in `class-sellsuite-admin.php`:

```php
wp_enqueue_script(
    'sellsuite-admin-js',
    SELLSUITE_PLUGIN_URL . 'admin/build/app.js',
    array('react', 'react-dom', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'),
    SELLSUITE_VERSION,
    true
);

wp_localize_script('sellsuite-admin-js', 'sellsuiteData', array(
    'apiUrl' => rest_url('sellsuite/v1'),
    'nonce' => wp_create_nonce('wp_rest'),
    'currentPage' => 'settings',
    'settings' => get_option('sellsuite_settings', array()),
));
```

---

## 🏗️ PHP Core Classes

### Class Overview:

| Class | File | Purpose |
|-------|------|---------|
| `Admin` | class-sellsuite-admin.php | Admin menu & page rendering |
| `Admin_Handler` | class-sellsuite-admin-handler.php | Manual point operations & audit logging |
| `Activator` | class-sellsuite-activator.php | Plugin activation, DB setup, defaults |
| `Dashboard` | class-sellsuite-dashboard.php | Dashboard data retrieval |
| `User_Dashboard_Data` | class-sellsuite-dashboard-data.php | User-specific dashboard data |
| `Frontend` | class-sellsuite-frontend.php | Frontend hooks & WooCommerce integration |
| `Loader` | class-sellsuite-loader.php | Plugin loader, REST routes registration |
| `Order_Handler` | class-sellsuite-order-handler.php | Order lifecycle & point awarding |
| `Points` | class-sellsuite-points-manager.php | Points ledger & calculation (core) |
| `Product_Meta` | class-sellsuite-product-meta.php | Product-level point settings |
| `Points_Manager` | Points class | Points calculation & management |
| `Currency_Handler` | class-sellsuite-currency-handler.php | Multi-currency conversion |
| `Refund_Handler` | class-sellsuite-refund-handler.php | Refund processing |
| `Redeem_Handler` | class-sellsuite-redeem-handler.php | Points redemption |
| `Expiry_Handler` | class-sellsuite-expiry-handler.php | Points expiry management |
| `Notification_Handler` | class-sellsuite-notification-handler.php | Email notifications |

### Key Methods by Class:

**Points Class (Core):**
- `get_earned_points($user_id)` - Get total earned points
- `get_available_balance($user_id)` - Get redeemable points
- `add_ledger_entry()` - Record point transaction
- `redeem_points()` - Redeem points for discount

**User_Dashboard_Data Class:**
- `get_points_summary($user_id)` - Earned, available, pending points
- `get_history_paginated()` - Paginated transaction history
- `format_history_entry()` - Format for display
- `get_status_display_info()` - Color/status information

**Order_Handler Class:**
- `award_points_for_order()` - Award on order placed
- `on_order_status_changed()` - Sync with order status
- `handle_order_refund()` - Handle refunds

---

## 🔌 REST API Endpoints

### Registered Routes (via `/sellsuite/v1/`):

| Method | Endpoint | Handler | Permission |
|--------|----------|---------|-----------|
| GET | `/settings` | `get_settings()` | manage_woocommerce |
| POST | `/settings` | `update_settings()` | manage_woocommerce |
| GET | `/dashboard` | `get_dashboard_overview()` | manage_woocommerce |
| GET | `/user-dashboard` | `get_user_dashboard()` | read (logged in) |
| POST | `/redeem-points` | `redeem_points()` | read (logged in) |
| POST | `/assign-points` | `assign_points()` | manage_woocommerce |
| POST | `/deduct-points` | `deduct_points()` | manage_woocommerce |

### Settings Endpoint:

**GET /wp-json/sellsuite/v1/settings**
- Returns current settings object
- Requires: manage_woocommerce capability

**POST /wp-json/sellsuite/v1/settings**
- Updates settings in `wp_options`
- Payload: JSON object with settings
- Requires: manage_woocommerce capability & nonce

---

## ⚙️ Configuration & Build

### NPM Scripts (admin folder):

```json
{
  "build": "webpack --mode production",      // Production build
  "dev": "webpack --mode development --watch",
  "start": "webpack --mode development --watch"
}
```

### Webpack Configuration:

**Input:** `/admin/src/index.jsx`  
**Output:** `/admin/build/app.js`  
**Loaders:** 
- Babel (React JSX)
- SCSS/CSS
- Mini CSS Extract Plugin

### Dependencies:

**Development:**
- @babel/core, @babel/preset-react
- Webpack 5, Webpack CLI
- SCSS/SASS loader
- CSS/Style loaders

**Production:**
- React 18.2.0
- React DOM 18.2.0

### WordPress Integration Points:

1. **Main Menu:** SellSuite (position 56)
2. **Submenus:**
   - Dashboard → `sellsuite`
   - Settings → `sellsuite-settings`
3. **Settings Page ID:** `sellsuite_page_sellsuite-settings`
4. **Dashboard Page ID:** `toplevel_page_sellsuite`

---

## 🔐 Security Measures

1. **Nonce Protection:** Settings updates protected by wp_rest nonce
2. **Capability Checks:** All admin operations require `manage_woocommerce`
3. **Prepared Statements:** All DB queries use `$wpdb->prepare()`
4. **Input Validation:** User inputs sanitized and validated
5. **Audit Logging:** Admin actions logged in `wp_sellsuite_audit_log`

---

## 📊 Data Flow: Settings Save

```
React Form (PointManagement.jsx)
    ↓
handleSave() function
    ↓
fetch(/wp-json/sellsuite/v1/settings) [POST]
    ↓
Loader::update_settings()
    ↓
update_option('sellsuite_settings', $data)
    ↓
wp_options table updated
    ↓
Success message displayed
```

---

## 🚀 Plugin Initialization Flow

```
sellsuite.php (Main Plugin File)
    ↓
Hook: plugins_loaded
    ↓
run_sellsuite() called
    ↓
Loader class instantiated
    ↓
load_dependencies() → All classes required
    ↓
register_rest_routes()
    ↓
register_activation_hook() → Activator::activate()
    ↓
Create DB tables
    ↓
Set default options (sellsuite_settings)
    ↓
Plugin ready
```

---

## 📝 Summary

### Current State:
✅ **10 Database Tables** - For comprehensive points management  
✅ **Settings in wp_options** - Key: `sellsuite_settings`  
✅ **React Admin UI** - Modern, tabbed settings interface  
✅ **REST API** - Full API for frontend/React communication  
✅ **Build Pipeline** - Webpack for React compilation  
✅ **Security** - Nonce & capability protection  
✅ **Production Ready** - All systems operational  

### Key Integration Points:
- Settings stored: `wp_options` table
- REST API base: `/wp-json/sellsuite/v1/`
- Admin page hook: `sellsuite_page_sellsuite-settings`
- React entry: `/admin/src/index.jsx`
- Build output: `/admin/build/app.js`

---

**Last Updated:** December 8, 2025  
**Plugin Status:** ✅ Production Ready
