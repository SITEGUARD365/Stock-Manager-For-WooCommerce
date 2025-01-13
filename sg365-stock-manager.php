<?php
/**
 * Plugin Name: WooCommerce Stock Manager by SITE GUARD 365
 * Plugin URI: https://siteguard365.com/
 * Description: Display and manage WooCommerce product stock (including variations) in one place with advanced filtering options.
 * Version: 1.1.0
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

// Define plugin constants
define( 'SG365_WC_STOCK_MANAGER', __FILE__ );

define( 'SG365_WC_VERSION', '1.1.0' );

class SG365_Stock_Manager {

    public function __construct() {
        // Admin menu and settings
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );

        // Display stock management page
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
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
     * Enqueue required scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 'sg365-stock-manager-css', plugins_url( '/assets/styles.css', SG365_WC_STOCK_MANAGER ), [], SG365_WC_VERSION );
        wp_enqueue_script( 'sg365-stock-manager-js', plugins_url( '/assets/scripts.js', SG365_WC_STOCK_MANAGER ), [ 'jquery' ], SG365_WC_VERSION, true );
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
                        </tr>
                    </thead>
                    <tbody>
                        ' . $this->get_stock_rows( $filter, $low_stock_threshold, $full_stock_threshold ) . '
                    </tbody>
                </table>
            </div>
        </div>';
    }

    /**
     * Get stock rows for all products and variations with filters
     *
     * @param string $filter
     * @param int $low_stock_threshold
     * @param int $full_stock_threshold
     * @return string
     */
    private function get_stock_rows( $filter, $low_stock_threshold, $full_stock_threshold ) {
        $products = wc_get_products( [ 'limit' => -1, 'status' => 'publish' ] );
        $rows     = '';

        foreach ( $products as $product ) {
            if ( ! $product->managing_stock() ) {
                continue;
            }

            $stock_quantity = $product->get_stock_quantity();

            if ( $filter === 'low' && ( $stock_quantity >= $low_stock_threshold || $stock_quantity <= 0 ) ) {
                continue;
            }

            if ( $filter === 'full' && $stock_quantity < $full_stock_threshold ) {
                continue;
            }

            if ( $filter === 'out' && $stock_quantity > 0 ) {
                continue;
            }

            $rows .= $this->render_stock_row( $product, $low_stock_threshold, $full_stock_threshold );
        }

        return $rows;
    }

    /**
     * Render a single stock row with color coding
     *
     * @param WC_Product $product
     * @param int $low_stock_threshold
     * @param int $full_stock_threshold
     * @return string
     */
    private function render_stock_row( $product, $low_stock_threshold, $full_stock_threshold ) {
        $edit_url       = admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' );
        $stock_quantity = $product->get_stock_quantity();
        $stock_class    = '';

        if ( $stock_quantity <= 0 ) {
            $stock_class = 'stock-out';
        } elseif ( $stock_quantity < $low_stock_threshold ) {
            $stock_class = 'stock-low';
        } elseif ( $stock_quantity >= $full_stock_threshold ) {
            $stock_class = 'stock-full';
        }

        return '<tr class="' . esc_attr( $stock_class ) . '">
            <td>' . esc_html( $product->get_name() ) . '</td>
            <td>' . esc_html( $stock_quantity ) . '</td>
            <td><a href="' . esc_url( $edit_url ) . '" class="button">Edit Stock</a></td>
        </tr>';
    }
}

new SG365_Stock_Manager();
