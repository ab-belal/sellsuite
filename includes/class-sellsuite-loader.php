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
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-customers.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-woocommerce.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-points-manager.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-product-meta.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-order-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-refund-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-redeem-handler.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-dashboard.php';
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
