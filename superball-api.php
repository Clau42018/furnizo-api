<?php
/**
 * Plugin Name: Superball API Integration
 * Description: Automates the order flow with Superball supplier by sending orders via API with accurate company data for invoicing. Includes product import functionality.
 * Version: 2.4
 * Author: Your Name
 * Text Domain: superball-api
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'SUPERBALL_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUPERBALL_API_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SUPERBALL_API_VERSION', '2.4' );

// Include necessary files
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-api.php';
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-debug-log.php';
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-admin.php';
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-order-handler.php';
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-ajax-handler.php';
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-stock-updater.php';
require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-product-importer.php'; // Nou

// Initialize the plugin
function superball_api_init() {
    // Load text domain for translations
    load_plugin_textdomain( 'superball-api', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Initialize classes
    $superball_debug  = new Superball_Debug_Log();
    $superball_api    = new Superball_API( $superball_debug );
    $superball_order  = new Superball_Order_Handler( $superball_api, $superball_debug );
    $superball_admin  = new Superball_Admin( $superball_api, $superball_debug, $superball_order );
    $superball_ajax   = new Superball_AJAX_Handler( $superball_api, $superball_order, $superball_debug );
    $superball_stock  = new Superball_Stock_Updater( $superball_api, $superball_debug );
    $superball_product_importer = new Superball_Product_Importer( $superball_debug ); // Nou
}
add_action( 'plugins_loaded', 'superball_api_init' );

// Activation and deactivation hooks
register_activation_hook( __FILE__, array( 'Superball_API', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Superball_API', 'deactivate' ) );
// În partea de jos a fișierului superball-api.php, înainte de închiderea tagului PHP
register_deactivation_hook( __FILE__, 'superball_api_deactivate' );

function superball_api_deactivate() {
    // Obține toate evenimentele cron
    $crons = _get_cron_array();
    if ( ! empty( $crons ) ) {
        foreach ( $crons as $timestamp => $cron ) {
            foreach ( $cron as $hook => $args ) {
                // Verifică dacă hook-ul conține 'superball'
                if ( strpos( $hook, 'superball' ) !== false ) {
                    // Șterge evenimentul cron
                    wp_unschedule_event( $timestamp, $hook, $args['args'] );
                }
            }
        }
    }
}
?>
