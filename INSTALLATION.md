# SellSuite Installation Guide

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Node.js 14+ and npm (for development)

## Installation Steps

### 1. Install the Plugin

**Option A: Manual Installation**
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

**Option B: Direct Upload**
1. Extract the plugin files
2. Upload the `sellsuite` folder to `/wp-content/plugins/`
3. Go to WordPress Admin → Plugins
4. Find "SellSuite" and click "Activate"

### 2. Verify WooCommerce

Make sure WooCommerce is installed and activated before activating SellSuite. If WooCommerce is not active, SellSuite will automatically deactivate and show an error message.

### 3. Configure Settings

   - Set points redemption rate
   - Set points expiry period

### 4. Development Setup (Optional)

If you want to modify the plugin or contribute to development:

#### Install Dependencies
### 4. Development Setup (Optional)

If you want to modify the plugin or contribute to development:

#### Install Dependencies (admin)

If you are developing the React admin dashboard, install dependencies in the `admin` folder:

```bash
cd admin
npm install
```

#### Build Assets

Gulp has been removed from this repository. SCSS and frontend assets must be compiled manually or with your preferred build tool. The SCSS source for frontend styles lives in `assets/scss/` and should be compiled to `assets/css/` (the plugin expects `assets/css/frontend.css`).

To build the React admin dashboard:

```bash
cd admin
npm run build
```
├── sellsuite.php              # Main plugin file
├── includes/                  # PHP classes
│   ├── class-sellsuite-loader.php
│   ├── class-sellsuite-admin.php
│   ├── class-sellsuite-frontend.php
│   ├── class-sellsuite-points.php
│   ├── class-sellsuite-customers.php
│   ├── class-sellsuite-woocommerce.php
│   ├── class-sellsuite-activator.php
│   ├── class-sellsuite-deactivator.php
│   └── helpers.php
├── admin/                     # React admin dashboard
│   ├── src/                   # React source files
│   ├── build/                 # Compiled React app
│   ├── package.json
│   └── webpack.config.js
├── assets/                    # Frontend assets
│   ├── css/                   # Compiled CSS
│   ├── js/                    # Compiled JS
│   ├── scss/                  # SCSS source files
│   └── images/                # Images
├── (build tools not included) # Styles must be compiled manually
├── package.json              # Node dependencies
└── readme.txt                # WordPress plugin readme
\`\`\`

## Database Tables

SellSuite creates the following database table on activation:

- `wp_sellsuite_points` - Stores customer points transactions

The table is created automatically using WordPress's `dbDelta()` function.

## Configuration

### Points System

Configure in **WooCommerce → SellSuite → Points System**:

- **Points Per Dollar**: How many points customers earn per dollar spent (default: 1)
- **Points Redemption Rate**: How many points equal $1 in redemption value (default: 100)
- **Points Expiry**: Number of days before points expire (0 = no expiry)

### WooCommerce Integration

The plugin automatically integrates with WooCommerce:

- Awards points on order completion
- Displays points on customer account page
- Shows potential points in cart/checkout
- Displays points earned on thank you page
- Shows points info on product pages

## Troubleshooting

### Plugin Won't Activate

- Ensure WooCommerce is installed and activated
- Check PHP version (7.4+ required)
- Check WordPress version (5.8+ required)

### React Dashboard Not Loading

1. Build the React app:
   \`\`\`bash
   cd admin
   npm install
   npm run build
   \`\`\`

2. Check browser console for errors
3. Verify `admin/build/index.js` exists

### Styles Not Applying

1. Compile SCSS to CSS (manually or with your tool of choice) to produce `assets/css/frontend.css`
2. Clear WordPress cache
3. Hard refresh browser (Ctrl+Shift+R)

### Points Not Being Awarded

1. Check if points system is enabled in settings
2. Verify order status is "Completed"
3. Check if customer is logged in
4. Review database table `wp_sellsuite_points`

## Support

For issues and questions:
- Check the documentation
- Review the code comments
- Contact support at your-email@example.com

## License

GPL-2.0+ - See LICENSE file for details
