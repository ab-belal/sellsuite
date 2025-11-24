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
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-points.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-customers.php';
        require_once SELLSUITE_PLUGIN_DIR . 'includes/class-sellsuite-woocommerce.php';
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
        $this->add_action('woocommerce_before_my_account', $frontend, 'display_customer_points');
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

        register_rest_route('sellsuite/v1', '/points/(?P<user_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_points'),
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
     * Get user points.
     */
    public function get_user_points($request) {
        $user_id = $request->get_param('user_id');
        $points = Points::get_user_total_points($user_id);
        return rest_ensure_response(array('user_id' => $user_id, 'points' => $points));
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
