<?php
/**
 * Plugin Name: WooCommerce Stock Manager by SITE GUARD 365
 * Plugin URI: https://siteguard365.com/
 * Description: Display and manage WooCommerce product stock (including variations) with advanced features like POS integration, export, reporting, analytics, and REST API support.
 * Version: 2.2.0
 * Author: SITE GUARD 365
 * Author URI: https://siteguard365.com/
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.2
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'SG365_WC_STOCK_MANAGER', __FILE__ );
define( 'SG365_WC_VERSION', '2.2.0' );
define( 'SG365_WC_PLUGIN_DIR', plugin_dir_path( SG365_WC_STOCK_MANAGER ) );
define( 'SG365_WC_PLUGIN_URL', plugin_dir_url( SG365_WC_STOCK_MANAGER ) );

class SG365_Stock_Manager {

    public function __construct() {
        // Admin menu and settings
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );

        // Enqueue scripts and styles
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Dashboard widgets
        add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widgets' ] );

        // REST API Support
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

        // Notifications
        add_action( 'woocommerce_low_stock', [ $this, 'notify_low_stock' ] );

        // Developer hooks and filters
        do_action( 'sg365_stock_manager_initialized' );
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            'Stock Manager',
            'Stock Manager',
            'manage_woocommerce',
            'sg365-stock-manager',
            [ $this, 'render_stock_page' ],
            'dashicons-products',
            56
        );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 'sg365-stock-manager-css', SG365_WC_PLUGIN_URL . 'assets/styles.css', [], SG365_WC_VERSION );
        wp_enqueue_script( 'sg365-stock-manager-js', SG365_WC_PLUGIN_URL . 'assets/scripts.js', [ 'jquery' ], SG365_WC_VERSION, true );
    }

    /**
     * Register plugin settings
     */
    public function register_plugin_settings() {
        register_setting( 'sg365_stock_manager_settings', 'sg365_low_stock_threshold' );
        register_setting( 'sg365_stock_manager_settings', 'sg365_full_stock_threshold' );
    }

    /**
     * Render stock management page
     */
    public function render_stock_page() {
        $low_stock_threshold  = get_option( 'sg365_low_stock_threshold', 5 );
        $full_stock_threshold = get_option( 'sg365_full_stock_threshold', 50 );

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'sg365' ) );
        }

        echo '<div class="wrap">
            <h1>Stock Manager</h1>

            <form method="post" action="options.php">
                <h2>Settings</h2>';
                settings_fields( 'sg365_stock_manager_settings' );
                echo '<table class="form-table">
                    <tr>
                        <th scope="row"><label for="sg365_low_stock_threshold">Low Stock Threshold</label></th>
                        <td><input name="sg365_low_stock_threshold" id="sg365_low_stock_threshold" type="number" value="' . esc_attr( $low_stock_threshold ) . '" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sg365_full_stock_threshold">Full Stock Threshold</label></th>
                        <td><input name="sg365_full_stock_threshold" id="sg365_full_stock_threshold" type="number" value="' . esc_attr( $full_stock_threshold ) . '" class="small-text"></td>
                    </tr>
                </table>
                ' . submit_button( 'Save Settings' ) . '
            </form>

            <h2>Analytics and Insights</h2>
            <div id="analytics-insights">
                ' . $this->get_analytics_data() . '
            </div>

            <h2>Manage Stock</h2>
            <div id="stock-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Live Stock Quantity</th>
                            <th>Edit Stock</th>
                            <th>Stock Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $this->get_stock_rows() . '
                    </tbody>
                </table>
            </div>
        </div>';
    }

    /**
     * Fetch stock rows for stock table
     */
    public function get_stock_rows() {
        $products = wc_get_products( [ 'limit' => -1, 'status' => 'publish' ] );
        $rows = '';

        foreach ( $products as $product ) {
            if ( $product->managing_stock() ) {
                $rows .= '<tr>
                    <td>' . esc_html( $product->get_name() ) . '</td>
                    <td>' . esc_html( $product->get_stock_quantity() ) . '</td>
                    <td><a href="' . esc_url( admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ) ) . '">Edit</a></td>
                    <td>' . wc_price( $product->get_stock_quantity() * $product->get_price() ) . '</td>
                </tr>';
            }
        }

        return $rows;
    }

    /**
     * REST API Routes
     */
    public function register_rest_routes() {
        register_rest_route( 'sg365/v1', '/stock', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_stock_data' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_woocommerce' );
            }
        ]);
    }

    public function get_stock_data() {
        $products = wc_get_products( [ 'limit' => -1, 'status' => 'publish' ] );
        $data = [];

        foreach ( $products as $product ) {
            if ( $product->managing_stock() ) {
                $data[] = [
                    'name' => $product->get_name(),
                    'stock' => $product->get_stock_quantity()
                ];
            }
        }

        return rest_ensure_response( $data );
    }

    /**
     * Notify on low stock
     */
    public function notify_low_stock( $product ) {
        $admin_email = get_option( 'admin_email' );
        wp_mail( $admin_email, 'Low Stock Alert', 'The product "' . $product->get_name() . '" is running low on stock.' );
    }

    /**
     * Developer Hook Examples
     */
    public function add_developer_hooks() {
        do_action( 'sg365_before_stock_update' );
        // Your logic here
        do_action( 'sg365_after_stock_update' );
    }

    /**
     * Analytics Data
     */
    public function get_analytics_data() {
        $products = wc_get_products( [ 'limit' => -1, 'status' => 'publish' ] );
        $total_stock = 0;
        $total_value = 0;

        foreach ( $products as $product ) {
            if ( $product->managing_stock() ) {
                $total_stock += $product->get_stock_quantity();
                $total_value += $product->get_stock_quantity() * $product->get_price();
            }
        }

        return '<p>Total Stock: ' . esc_html( $total_stock ) . '</p>
                <p>Total Stock Value: ' . wc_price( $total_value ) . '</p>';
    }

}

new SG365_Stock_Manager();
