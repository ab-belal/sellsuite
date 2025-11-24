# SellSuite Development Guide

## Development Environment Setup

### Prerequisites

- Node.js 14+ and npm
- PHP 7.4+
- WordPress development environment
- WooCommerce installed

### Initial Setup

```bash
# Clone or download the plugin
cd wp-content/plugins/sellsuite

# If you are developing the admin React app, install admin dependencies
cd admin
npm install
cd ..
```

## Development Workflow

### Frontend Assets (SCSS & JS)

 Compile SCSS and frontend assets manually or with your preferred toolchain. SCSS source files are in `assets/scss/` and should be compiled to `assets/css/` (the plugin expects `assets/css/frontend.css`).

#### SCSS Structure

- `assets/scss/admin.scss` - Admin area styles
- `assets/scss/frontend.scss` - Frontend customer-facing styles

Compiled to:
- `assets/css/admin.css` (and .min.css)
- `assets/css/frontend.css` (and .min.css)

#### JavaScript Structure

- `assets/js/src/frontend.js` - Frontend JavaScript

Compiled to:
- `assets/js/frontend.js` (and .min.js)

### React Admin Dashboard

The admin dashboard is built with React and WordPress components:

```bash
cd admin

# Development mode with watch
npm run dev

# Production build
npm run build
```

#### React Structure

```
admin/src/
├── index.js              # Entry point
├── App.js                # Main app component
├── styles.css            # Admin styles
└── components/
    ├── PointsTab.js      # Points system settings
    ├── ProductsTab.js    # Product management
    ├── CustomersTab.js   # Customer management
    └── SettingsSaveButton.js
```

#### Adding New Components

1. Create component in `admin/src/components/`
2. Import in `App.js`
3. Add to TabPanel if needed
4. Build: `npm run build`

### PHP Development

#### Class Structure

All PHP classes use the `SellSuite` namespace:

```php
<?php
namespace SellSuite;

class Your_Class {
    // Your code
}
```

#### Adding New Features

1. Create class in `includes/class-sellsuite-yourfeature.php`
2. Load in `class-sellsuite-loader.php`:
   ```php
   require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-yourfeature.php';
   ```
3. Register hooks in loader
4. Test thoroughly

#### REST API Endpoints

Add new endpoints in `class-sellsuite-loader.php`:

```php
public function register_rest_routes() {
    register_rest_route('sellsuite/v1', '/your-endpoint', array(
        'methods' => 'GET',
        'callback' => array($this, 'your_callback'),
        'permission_callback' => function() {
            return current_user_can('manage_woocommerce');
        }
    ));
}
```

### Database Operations

#### Querying Points

```php
// Get user points
$points = Points::get_user_total_points($user_id);

// Add points
Points::add_points($user_id, 100, 'bonus', 'Birthday bonus');

// Deduct points
Points::deduct_points($user_id, 50, 'redemption', 'Redeemed for discount');

// Get history
$history = Points::get_user_points_history($user_id, 20);
```

#### Direct Database Access

```php
global $wpdb;
$table_name = $wpdb->prefix . 'sellsuite_points';

$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name WHERE user_id = %d",
    $user_id
));
```

## Testing

### Manual Testing Checklist

- [ ] Plugin activates without errors
- [ ] WooCommerce dependency check works
- [ ] Database table created on activation
- [ ] Admin page loads correctly
- [ ] React dashboard renders
- [ ] Settings save successfully
- [ ] Points awarded on order completion
- [ ] Points display on account page
- [ ] Frontend styles load correctly

### Testing Points System

1. Create test order as logged-in customer
2. Complete the order
3. Check points awarded in database
4. Verify points display on account page
5. Check admin order page for points info

## Code Standards

### PHP

- Follow WordPress Coding Standards
- Use namespaces: `namespace SellSuite;`
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize input: `sanitize_text_field()`, etc.
- Use prepared statements for database queries

### JavaScript

- Use ES6+ syntax
- Use jQuery for DOM manipulation
- Prefix global variables: `window.SellSuite`
- Add comments for complex logic

### React

- Use functional components
- Use WordPress components from `@wordpress/components`
- Use hooks for state management
- Keep components small and focused

### CSS/SCSS

- Use system fonts
- Follow BEM naming: `.sellsuite-component__element--modifier`
- Mobile-first responsive design
- Use variables for colors and spacing

## Debugging

### Enable WordPress Debug Mode

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### PHP Debugging

```php
error_log('Debug message: ' . print_r($variable, true));
```

### React Debugging

```javascript
console.log('[SellSuite]', data)
```

### Database Debugging

```php
global $wpdb;
error_log($wpdb->last_query);
error_log($wpdb->last_error);
```

## Building for Production

```bash
# Build all assets
npm run build

# Build React admin
cd admin && npm run build && cd ..

# Test in production mode
# - Deactivate and reactivate plugin
# - Test all features
# - Check for console errors
# - Verify minified assets load
```

## Version Control

### Git Ignore

Recommended `.gitignore`:

```
node_modules/
admin/node_modules/
admin/build/
assets/css/*.css
assets/css/*.min.css
assets/js/frontend.js
assets/js/frontend.min.js
.DS_Store
*.log
```

### Commit Messages

- Use clear, descriptive messages
- Prefix with type: `feat:`, `fix:`, `docs:`, `style:`, `refactor:`
- Example: `feat: add points expiry functionality`

## Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/your-feature`
3. Make changes and test thoroughly
4. Commit with clear messages
5. Push and create pull request

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Documentation](https://woocommerce.com/documentation/)
- [WordPress Components](https://developer.wordpress.org/block-editor/reference-guides/components/)
* Build tools removed from repository; use your preferred SCSS compiler or toolchain.
- [React Documentation](https://react.dev/)
