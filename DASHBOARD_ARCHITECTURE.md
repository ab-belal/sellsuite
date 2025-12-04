# Dashboard Architecture - Code Organization

## Overview

The dashboard has been refactored to separate concerns and improve maintainability. All data processing logic has been moved to a dedicated `User_Dashboard_Data` class, while the template (`dashboard.php`) now only handles presentation.

## File Structure

### 1. **class-sellsuite-dashboard-data.php** (New)
**Location:** `includes/class-sellsuite-dashboard-data.php`

This is the centralized data layer for all dashboard functionality. It contains all:
- Database queries
- Data calculations
- Data formatting
- Business logic

#### Key Methods:

```php
// Get all points summary data (earned, available, pending)
$summary = \SellSuite\User_Dashboard_Data::get_points_summary( $user_id );

// Get paginated history with all necessary data
$history_data = \SellSuite\User_Dashboard_Data::get_history_paginated( $user_id, $page = 1, $per_page = 5 );

// Format a single history entry with all display information
$formatted = \SellSuite\User_Dashboard_Data::format_history_entry( $entry );

// Get status display information (colors, text)
$status_info = \SellSuite\User_Dashboard_Data::get_status_display_info( $status );

// Generate pagination HTML
$pagination_html = \SellSuite\User_Dashboard_Data::get_pagination_html( $current_page, $total_pages );
```

### 2. **dashboard.php** (Refactored Template)
**Location:** `templates/woocommerce/myaccount/dashboard.php`

This template is now clean and focused on presentation only. It:
- Calls data methods from `User_Dashboard_Data` class
- Displays the processed data
- Contains no queries or calculations
- Uses simple conditional statements for display logic

## Benefits

✅ **Separation of Concerns** - Data layer is separate from presentation layer
✅ **Maintainability** - All logic is organized in dedicated methods
✅ **Reusability** - Methods can be used in APIs, AJAX, or other contexts
✅ **Testability** - Business logic can be unit tested independently
✅ **Developer Friendly** - Clear method names and structure
✅ **Performance** - Data caching becomes easier to implement

## Usage Examples

### Example 1: Get Points Summary
```php
$summary = \SellSuite\User_Dashboard_Data::get_points_summary( $user_id );
echo $summary['earned'];      // Total earned points
echo $summary['available'];   // Available for redemption
echo $summary['pending'];     // Pending points (from processing orders)
```

### Example 2: Get Paginated History
```php
$history_data = \SellSuite\User_Dashboard_Data::get_history_paginated( $user_id, 1, 5 );

foreach ( $history_data['entries'] as $entry ) {
    $formatted = \SellSuite\User_Dashboard_Data::format_history_entry( $entry );
    echo $formatted['product_name'];
    echo $formatted['status_text'];
}

// Pagination info
echo $history_data['total_pages'];
echo $history_data['current_page'];
```

### Example 3: Use in AJAX or REST API
```php
add_action( 'wp_ajax_get_points_summary', function() {
    $user_id = get_current_user_id();
    $summary = \SellSuite\User_Dashboard_Data::get_points_summary( $user_id );
    wp_send_json_success( $summary );
} );
```

## Data Flow

```
Template (dashboard.php)
    ↓
    Calls: Dashboard_Data::get_points_summary()
    ↓
Database Query (wpdb)
    ↓
Returns: Processed Array
    ↓
Template Displays Data
```

## Status Display Information

The `get_status_display_info()` method returns:
- `text` - Display text for the status
- `color` - Text color (hex)
- `bg` - Background color (hex)

Supported statuses:
- `earned` - Green (#28a745)
- `pending` - Yellow (#ffc107)
- `redeemed` - Gray (#6c757d)
- `expired` - Red (#dc3545)
- `refunded` - Orange (#fd7e14)
- `cancelled` - Red (#dc3545)

## Database Queries Handled

All queries are prepared with `$wpdb->prepare()` for security:

1. **Points Summary**
   - Get earned points (all points with status='earned')
   - Get available balance (earned - redeemed - expired)
   - Get pending points (status='pending' from active orders only)

2. **History Pagination**
   - Count total entries
   - Get paginated entries ordered by date DESC
   - Filter by user_id and action_type

3. **Entry Formatting**
   - Fetch order object
   - Fetch product object
   - Calculate quantity from order items
   - Get status display information

## Future Enhancements

Possible improvements that are now easier to implement:

- Cache results using WordPress transients
- Add filtering/sorting options
- Implement export functionality
- Create REST API endpoints
- Add analytics/statistics
- Implement batch operations

---

**Last Updated:** December 4, 2025
