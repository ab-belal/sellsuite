<?php
namespace SellSuite;

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API.
 */
class Loader {

    /**
     * The array of actions registered with WordPress.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     */
    protected $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_api_hooks();
        $this->define_woocommerce_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-admin.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-frontend.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-frontend-display.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-customers.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-woocommerce.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-points-manager.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-product-meta.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-order-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-refund-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-redeem-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-dashboard.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-dashboard-data.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-notification-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-email-templates.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-admin-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-expiry-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-currency-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/helpers.php';
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $admin = new Admin();

        $this->add_action('admin_menu', $admin, 'add_admin_menu');
        $this->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_frontend_hooks() {
        $frontend = new Frontend();

        $this->add_action('wp_enqueue_scripts', $frontend, 'enqueue_scripts');
        $this->add_action('init', $frontend, 'register_frontend_hooks');
        
        // Register the custom WooCommerce My Account endpoint
        $this->add_action('init', $frontend, 'add_products_info_endpoint');
        
        // Add the endpoint to the My Account menu
        $this->add_filter('woocommerce_account_menu_items', $frontend, 'add_products_info_menu_item');
        
        // Display content when the endpoint is accessed
        $this->add_action('woocommerce_account_products-info_endpoint', $frontend, 'products_info_endpoint_content');
        
        // Set the page title for the endpoint
        $this->add_filter('the_title', $frontend, 'products_info_endpoint_title');
    }

    /**
     * Register all REST API endpoints.
     */
    private function define_api_hooks() {
        $this->add_action('rest_api_init', $this, 'register_rest_routes');
    }

    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        // Settings endpoints
        register_rest_route('sellsuite/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_settings'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        // Dashboard endpoints
        register_rest_route('sellsuite/v1', '/dashboard/overview', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_overview'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/dashboard/user', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_dashboard'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        // Points redemption endpoint
        register_rest_route('sellsuite/v1', '/redeem', array(
            'methods' => 'POST',
            'callback' => array($this, 'redeem_points'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        // Redemption history endpoint
        register_rest_route('sellsuite/v1', '/redemptions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_redemptions'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        // Analytics endpoints
        register_rest_route('sellsuite/v1', '/analytics/timeline', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_timeline'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/analytics/top-earners', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_top_earners'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/analytics/segments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_segments'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        // Notification endpoints
        register_rest_route('sellsuite/v1', '/notifications/unread', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_unread_notifications'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/notifications/(?P<id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_notification_read'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/notifications/preferences', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_notification_preferences'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/notifications/preferences', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_notification_preferences'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        // Admin endpoints
        register_rest_route('sellsuite/v1', '/admin/points/assign', array(
            'methods' => 'POST',
            'callback' => array($this, 'admin_assign_points'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/admin/points/deduct', array(
            'methods' => 'POST',
            'callback' => array($this, 'admin_deduct_points'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/admin/points/reset', array(
            'methods' => 'POST',
            'callback' => array($this, 'admin_reset_points'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/admin/audit-log', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_audit_log'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/admin/action-summary', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_action_summary'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        // PHASE 7: Point Expiry endpoints
        register_rest_route('sellsuite/v1', '/expiry/rules', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_expiry_rules'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/expiry/rules/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_expiry_rule'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/expiry/process-user', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_user_expirations'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/expiry/forecast', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_expiry_forecast'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/expiry/summary', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_expired_summary'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/expiry/expire', array(
            'methods' => 'POST',
            'callback' => array($this, 'manually_expire_points'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        // PHASE 8: Multi-Currency endpoints
        register_rest_route('sellsuite/v1', '/currency/convert', array(
            'methods' => 'POST',
            'callback' => array($this, 'convert_currency'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/currency/rates', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_exchange_rates'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/currency/rates', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_exchange_rate'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/currency/supported', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_supported_currencies'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/currency/conversions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_conversions'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));

        register_rest_route('sellsuite/v1', '/currency/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_currency_analytics'),
            'permission_callback' => function() {
                return current_user_can('manage_woocommerce');
            }
        ));

        register_rest_route('sellsuite/v1', '/currency/balance', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_balance_in_currency'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ));
    }

    /**
     * Get plugin settings.
     */
    public function get_settings($request) {
        $settings = get_option('sellsuite_settings', array());
        return rest_ensure_response($settings);
    }

    /**
     * Update plugin settings.
     */
    public function update_settings($request) {
        $settings = $request->get_json_params();
        update_option('sellsuite_settings', $settings);
        return rest_ensure_response(array('success' => true, 'settings' => $settings));
    }

    /**
     * Get dashboard overview.
     */
    public function get_dashboard_overview($request) {
        $data = Dashboard::get_overview();
        return rest_ensure_response($data);
    }

    /**
     * Get user dashboard.
     */
    public function get_user_dashboard($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $data = Dashboard::get_user_dashboard($user_id);
        return rest_ensure_response($data);
    }

    /**
     * Redeem points.
     */
    public function redeem_points($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $params = $request->get_json_params();
        $points = isset($params['points']) ? intval($params['points']) : 0;
        $order_id = isset($params['order_id']) ? intval($params['order_id']) : 0;
        $options = isset($params['options']) ? $params['options'] : array();

        // Sanitize options
        if (is_array($options)) {
            $options = array_map('sanitize_text_field', $options);
        }

        $result = Redeem_Handler::redeem_points($user_id, $points, $order_id, $options);
        return rest_ensure_response($result);
    }

    /**
     * Get user redemptions.
     */
    public function get_user_redemptions($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $limit = intval($request->get_param('limit')) ?: 20;
        $page = intval($request->get_param('page')) ?: 1;
        $offset = ($page - 1) * $limit;

        $redemptions = Redeem_Handler::get_user_redemptions($user_id, $limit, $offset);
        return rest_ensure_response($redemptions);
    }

    /**
     * Get timeline data.
     */
    public function get_timeline($request) {
        $days = intval($request->get_param('days')) ?: 30;
        $timeline = Dashboard::get_points_timeline($days);
        return rest_ensure_response($timeline);
    }

    /**
     * Get top earners.
     */
    public function get_top_earners($request) {
        $limit = intval($request->get_param('limit')) ?: 10;
        $earners = Dashboard::get_top_earners($limit);
        return rest_ensure_response($earners);
    }

    /**
     * Get user segments.
     */
    public function get_user_segments($request) {
        $segments = Dashboard::get_user_segments();
        return rest_ensure_response($segments);
    }

    /**
     * PHASE 7: Get expiry rules.
     */
    public function get_expiry_rules($request) {
        $rules = Expiry_Handler::get_expiry_rules();
        return rest_ensure_response($rules);
    }

    /**
     * PHASE 7: Update expiry rule.
     */
    public function update_expiry_rule($request) {
        $rule_id = intval($request->get_param('id'));
        $data = $request->get_json_params();

        $result = Expiry_Handler::update_expiry_rule($rule_id, $data);
        return rest_ensure_response($result);
    }

    /**
     * PHASE 7: Process user expirations.
     */
    public function process_user_expirations($request) {
        $params = $request->get_json_params();
        $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;

        if ($user_id <= 0) {
            return new \WP_Error('invalid_user', 'Invalid user ID', array('status' => 400));
        }

        $result = Expiry_Handler::process_user_expirations($user_id);
        return rest_ensure_response($result);
    }

    /**
     * PHASE 7: Get expiry forecast for user.
     */
    public function get_expiry_forecast($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $days = intval($request->get_param('days')) ?: 30;
        $forecast = Expiry_Handler::get_expiry_forecast($user_id, $days);
        return rest_ensure_response($forecast);
    }

    /**
     * PHASE 7: Get expired points summary.
     */
    public function get_expired_summary($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $summary = Expiry_Handler::get_expired_summary($user_id);
        return rest_ensure_response($summary);
    }

    /**
     * PHASE 7: Manually expire points (admin only).
     */
    public function manually_expire_points($request) {
        $params = $request->get_json_params();
        $ledger_id = isset($params['ledger_id']) ? intval($params['ledger_id']) : 0;
        $user_id = isset($params['user_id']) ? intval($params['user_id']) : 0;

        if ($ledger_id <= 0 || $user_id <= 0) {
            return new \WP_Error('invalid_params', 'Invalid parameters', array('status' => 400));
        }

        $result = Expiry_Handler::manually_expire_points($ledger_id, $user_id);
        return rest_ensure_response($result);
    }

    /**
     * PHASE 8: Convert currency.
     */
    public function convert_currency($request) {
        $params = $request->get_json_params();
        $amount = isset($params['amount']) ? floatval($params['amount']) : 0;
        $from = isset($params['from_currency']) ? sanitize_text_field($params['from_currency']) : 'USD';
        $to = isset($params['to_currency']) ? sanitize_text_field($params['to_currency']) : 'USD';

        $result = Currency_Handler::convert_currency($amount, $from, $to);
        return rest_ensure_response($result);
    }

    /**
     * PHASE 8: Get exchange rates.
     */
    public function get_exchange_rates($request) {
        global $wpdb;
        $rates_table = $wpdb->prefix . 'sellsuite_exchange_rates';

        $rates = $wpdb->get_results(
            "SELECT * FROM {$rates_table} WHERE status = 'active' ORDER BY updated_at DESC"
        );

        return rest_ensure_response($rates ?: array());
    }

    /**
     * PHASE 8: Update exchange rate.
     */
    public function update_exchange_rate($request) {
        $params = $request->get_json_params();
        $from = isset($params['from_currency']) ? sanitize_text_field($params['from_currency']) : '';
        $to = isset($params['to_currency']) ? sanitize_text_field($params['to_currency']) : '';
        $rate = isset($params['rate']) ? floatval($params['rate']) : 0;

        if (empty($from) || empty($to) || $rate <= 0) {
            return new \WP_Error('invalid_params', 'Invalid parameters', array('status' => 400));
        }

        $result = Currency_Handler::update_exchange_rate($from, $to, $rate);
        return rest_ensure_response($result);
    }

    /**
     * PHASE 8: Get supported currencies.
     */
    public function get_supported_currencies($request) {
        $currencies = Currency_Handler::get_supported_currencies();
        return rest_ensure_response($currencies);
    }

    /**
     * PHASE 8: Get user conversion history.
     */
    public function get_user_conversions($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $limit = intval($request->get_param('limit')) ?: 50;
        $page = intval($request->get_param('page')) ?: 1;
        $offset = ($page - 1) * $limit;

        $conversions = Currency_Handler::get_user_conversions($user_id, $limit, $offset);
        return rest_ensure_response($conversions);
    }

    /**
     * PHASE 8: Get currency analytics.
     */
    public function get_currency_analytics($request) {
        $currency = $request->get_param('currency') ?: null;
        $analytics = Currency_Handler::get_currency_analytics($currency);
        return rest_ensure_response($analytics);
    }

    /**
     * PHASE 8: Get user balance in different currency.
     */
    public function get_balance_in_currency($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new \WP_Error('not_authenticated', 'User not authenticated', array('status' => 401));
        }

        $currency = $request->get_param('currency') ?: 'USD';
        $balance = Currency_Handler::get_balance_in_currency($user_id, sanitize_text_field($currency));
        return rest_ensure_response($balance);
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     */
    private function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     */
    private function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single collection.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }

    /**
     * Register all of the hooks related to WooCommerce integration.
     */
    private function define_woocommerce_hooks() {
        new WooCommerce_Integration();
    }
}
