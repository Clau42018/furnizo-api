<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_Stock_Updater {

    private $api;
    private $debug_log;

    public function __construct( $api, $debug_log ) {
        $this->api = $api;
        $this->debug_log = $debug_log;

        // Hook pentru evenimentul cron
        add_action( 'superball_stock_update_event', array( $this, 'update_stock_from_feed' ) );

        // Hook pentru înregistrarea cron-ului la activare/dezactivare
        register_activation_hook( SUPERBALL_API_PLUGIN_DIR . 'superball-api.php', array( $this, 'schedule_stock_update' ) );
        register_deactivation_hook( SUPERBALL_API_PLUGIN_DIR . 'superball-api.php', array( $this, 'unschedule_stock_update' ) );

        // Hook pentru salvarea setărilor actualizate
        add_action( 'update_option_superball_api_settings', array( $this, 'schedule_or_unschedule_stock_update' ), 10, 2 );
    }

    /**
     * Programează evenimentul cron pentru actualizarea stocurilor
     */
    public function schedule_stock_update() {
        $settings = get_option( 'superball_api_settings', array() );
        $enable_stock_update = isset( $settings['enable_stock_update'] ) ? $settings['enable_stock_update'] : false;

        if ( $enable_stock_update ) {
            $frequency = isset( $settings['stock_update_frequency'] ) ? $settings['stock_update_frequency'] : 'daily';
            if ( ! wp_next_scheduled( 'superball_stock_update_event' ) ) {
                wp_schedule_event( time(), $frequency, 'superball_stock_update_event' );
                $this->debug_log->log( "Scheduled Superball stock update event with frequency: $frequency." );
            }
        } else {
            $this->unschedule_stock_update();
        }
    }

    /**
     * Dezprogramează evenimentul cron pentru actualizarea stocurilor
     */
    public function unschedule_stock_update() {
        $timestamp = wp_next_scheduled( 'superball_stock_update_event' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'superball_stock_update_event' );
            $this->debug_log->log( "Unscheduled Superball stock update event." );
        }
    }

    /**
     * Schedule or unschedule stock update based on settings change
     *
     * @param string $old_value
     * @param string $new_value
     */
    public function schedule_or_unschedule_stock_update( $old_value, $new_value ) {
        $enable_stock_update_old = isset( $old_value['enable_stock_update'] ) ? $old_value['enable_stock_update'] : false;
        $enable_stock_update_new = isset( $new_value['enable_stock_update'] ) ? $new_value['enable_stock_update'] : false;

        if ( !$enable_stock_update_old && $enable_stock_update_new ) {
            // Enable stock update
            $frequency = isset( $new_value['stock_update_frequency'] ) ? $new_value['stock_update_frequency'] : 'daily';
            if ( ! wp_next_scheduled( 'superball_stock_update_event' ) ) {
                wp_schedule_event( time(), $frequency, 'superball_stock_update_event' );
                $this->debug_log->log( "Scheduled Superball stock update event with frequency: $frequency." );
            }
        } elseif ( $enable_stock_update_old && !$enable_stock_update_new ) {
            // Disable stock update
            $this->unschedule_stock_update();
        } else {
            // If frequency changed, reschedule
            $frequency_old = isset( $old_value['stock_update_frequency'] ) ? $old_value['stock_update_frequency'] : 'daily';
            $frequency_new = isset( $new_value['stock_update_frequency'] ) ? $new_value['stock_update_frequency'] : 'daily';

            if ( $frequency_old !== $frequency_new ) {
                $this->unschedule_stock_update();
                if ( $enable_stock_update_new ) {
                    wp_schedule_event( time(), $frequency_new, 'superball_stock_update_event' );
                    $this->debug_log->log( "Rescheduled Superball stock update event with new frequency: $frequency_new." );
                }
            }
        }
    }

    /**
     * Actualizează stocurile din feed
     */
    public function update_stock_from_feed() {
        $this->debug_log->log( 'Starting automatic stock update from Superball feed.' );

        // Hardcodat URL-ul feed-ului de produse
        $feed_url = 'https://b2b.green-future.ro/api/export-products?key=5271352173ad85fa64301b3c7186855a&data=all';

        $settings = get_option( 'superball_api_settings', array() );
        $access_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $password = isset( $settings['password'] ) ? $settings['password'] : '';

        if ( empty( $feed_url ) ) {
            $this->debug_log->log( 'Stock feed URL is not set. Aborting stock update.' );
            return;
        }

        if ( empty( $access_key ) || empty( $password ) ) {
            $this->debug_log->log( 'API Key or Password is missing. Aborting stock update.' );
            return;
        }

        // Logare URL-ul utilizat
        $this->debug_log->log( "Fetching stock feed from URL: $feed_url" );

        // Setează argumentele pentru cerere
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $access_key . ':' . $password ),
            ),
            'timeout' => 60,
        );

        // Trimite cererea API
        $response = wp_remote_get( $feed_url, $args );

        // Verifică dacă cererea a eșuat
        if ( is_wp_error( $response ) ) {
            $this->debug_log->log( 'Failed to fetch stock feed: ' . $response->get_error_message() );
            return;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Logare cod de răspuns
        $this->debug_log->log( "Received HTTP Status Code: $response_code" );

        if ( $response_code != 200 ) {
            $this->debug_log->log( "Failed to fetch stock feed. HTTP Status Code: $response_code." );
            return;
        }

        // Logare dimensiunea răspunsului
        $this->debug_log->log( "Stock feed response size: " . strlen( $response_body ) . " bytes." );

        // Verifică dacă răspunsul este gol
        if ( empty( $response_body ) ) {
            $this->debug_log->log( 'Stock feed response is empty. Aborting stock update.' );
            return;
        }

        // Parsează feed-ul (presupunând format CSV)
        $rows = array_map( 'str_getcsv', explode( "\n", $response_body ) );
        $header = array_shift( $rows );

        // Logare header-ul
        $this->debug_log->log( "CSV Header: " . implode( ', ', $header ) );

        if ( empty( $header ) ) {
            $this->debug_log->log( 'Stock feed is empty or invalid.' );
            return;
        }

        // Verifică dacă 'SKU' și 'Stock' există în header
        $header_lower = array_map( 'strtolower', $header );
        if ( ! in_array( 'sku', $header_lower ) || ! in_array( 'stock', $header_lower ) ) {
            $this->debug_log->log( "CSV does not contain required 'SKU' and 'Stock' columns." );
            return;
        }

        // Obține indexurile pentru 'SKU' și 'Stock'
        $sku_index = array_search( 'sku', $header_lower );
        $stock_index = array_search( 'stock', $header_lower );

        if ( $sku_index === false || $stock_index === false ) {
            $this->debug_log->log( "Unable to locate 'SKU' or 'Stock' columns in CSV." );
            return;
        }

        $updated_count = 0;
        $error_count = 0;

        foreach ( $rows as $index => $row ) {
            // Ignoră rândurile incomplete
            if ( count( $row ) <= max( $sku_index, $stock_index ) ) {
                $this->debug_log->log( "Incomplete row at line " . ($index + 2) . ". Skipping." );
                $error_count++;
                continue;
            }

            $sku   = sanitize_text_field( trim( $row[ $sku_index ] ) );
            $stock = intval( trim( $row[ $stock_index ] ) );

            // Ignoră rândurile fără SKU
            if ( empty( $sku ) ) {
                $this->debug_log->log( "Empty SKU found at line " . ($index + 2) . ". Skipping row." );
                $error_count++;
                continue;
            }

            // Găsește produsul după SKU
            $product_id = wc_get_product_id_by_sku( $sku );

            if ( ! $product_id ) {
                $this->debug_log->log( "Product with SKU '$sku' not found. Skipping." );
                $error_count++;
                continue;
            }

            $product = wc_get_product( $product_id );

            if ( ! $product->get_manage_stock() ) {
                $this->debug_log->log( "Product ID $product_id (SKU: $sku) does not have stock management enabled. Skipping." );
                $error_count++;
                continue;
            }

            // Actualizează stocul
            wc_update_product_stock( $product_id, $stock, 'set' );
            $this->debug_log->log( "Updated stock for Product ID $product_id (SKU: $sku) to $stock." );
            $updated_count++;
        }

        $this->debug_log->log( "Completed stock update from Superball feed. Updated: $updated_count, Errors: $error_count." );
    }
}
?>
