<?php
/**
 * Plugin Name: WooCommerce Stock Manager by SITE GUARD 365
 * Plugin URI: https://siteguard365.com/
 * Description: Display and manage WooCommerce product stock (including variations) with advanced features like POS integration, export, and reporting.
 * Version: 2.0.0
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
define( 'SG365_WC_VERSION', '2.0.0' );
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

        // Handle file exports
        add_action( 'admin_post_sg365_export_stock', [ $this, 'handle_stock_export' ] );

        // Automatic plugin updates
        add_filter( 'upgrader_post_install', [ $this, 'handle_plugin_update' ], 10, 3 );
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
        $filter = isset( $_GET['stock_filter'] ) ? sanitize_text_field( $_GET['stock_filter'] ) : 'all';

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

            <h2>Filters</h2>
            <div class="stock-filters">
                <a href="?page=sg365-stock-manager&stock_filter=low" class="button">Low Stock</a>
                <a href="?page=sg365-stock-manager&stock_filter=full" class="button">Full Stock</a>
                <a href="?page=sg365-stock-manager&stock_filter=out" class="button">Out of Stock</a>
                <a href="?page=sg365-stock-manager&stock_filter=all" class="button">All Products</a>
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
                        ' . $this->get_stock_rows( $filter, $low_stock_threshold, $full_stock_threshold ) . '
                    </tbody>
                </table>
            </div>

            <form method="post" action="' . admin_url( 'admin-post.php' ) . '">
                <input type="hidden" name="action" value="sg365_export_stock">
                <button type="submit" class="button button-primary">Export Stock Data</button>
            </form>
        </div>';
    }

    /**
     * Handle stock export
     */
    public function handle_stock_export() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'Unauthorized', 'sg365' ) );
        }

        $products = wc_get_products( [ 'limit' => -1, 'status' => 'publish' ] );
        $filename = 'stock_data_' . date( 'Y-m-d' ) . '.csv';
        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment;filename=' . $filename );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'Product Name', 'Stock Quantity', 'Stock Value' ] );

        foreach ( $products as $product ) {
            if ( ! $product->managing_stock() ) {
                continue;
            }

            $stock_quantity = $product->get_stock_quantity();
            $stock_value    = $stock_quantity * $product->get_price();

            fputcsv( $output, [ $product->get_name(), $stock_quantity, $stock_value ] );
        }

        fclose( $output );
        exit;
    }

    /**
     * Add dashboard widgets
     */
    public function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'sg365_stock_summary',
            'Stock Summary',
            [ $this, 'render_dashboard_widget' ]
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $products = wc_get_products( [ 'limit' => -1, 'status' => 'publish' ] );
        $low_stock_count = 0;
        $out_of_stock_count = 0;
        $full_stock_count = 0;

        foreach ( $products as $product ) {
            if ( ! $product->managing_stock() ) {
                continue;
            }

            $stock_quantity = $product->get_stock_quantity();
            if ( $stock_quantity <= 0 ) {
                $out_of_stock_count++;
            } elseif ( $stock_quantity < get_option( 'sg365_low_stock_threshold', 5 ) ) {
                $low_stock_count++;
            } else {
                $full_stock_count++;
            }
        }

        echo '<ul>
            <li>Low Stock Products: ' . esc_html( $low_stock_count ) . '</li>
            <li>Out of Stock Products: ' . esc_html( $out_of_stock_count ) . '</li>
            <li>Full Stock Products: ' . esc_html( $full_stock_count ) . '</li>
        </ul>';
    }

    /**
     * Handle plugin updates
     */
    public function handle_plugin_update( $response, $hook_extra, $result ) {
        if ( isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === plugin_basename( SG365_WC_STOCK_MANAGER ) ) {
            // Handle migration tasks if needed
            update_option( 'sg365_wc_last_update', time() );
        }
        return $response;
    }
}

new SG365_Stock_Manager();
