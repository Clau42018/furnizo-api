<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_AJAX_Handler {

    private $api;
    private $order_handler;
    private $debug_log;

    public function __construct( $api, $order_handler, $debug_log ) {
        $this->api = $api;
        $this->order_handler = $order_handler;
        $this->debug_log = $debug_log;

        // Register AJAX actions
        add_action( 'wp_ajax_send_superball_order', array( $this, 'ajax_send_superball_order' ) );
        add_action( 'wp_ajax_send_all_superball_orders', array( $this, 'ajax_send_all_superball_orders' ) );
        add_action( 'wp_ajax_view_superball_order', array( $this, 'ajax_view_superball_order' ) ); // Nou
    }

    /**
     * Handle AJAX request to send a single order
     */
    public function ajax_send_superball_order() {
        check_ajax_referer( 'superball_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'superball-api' ) ) );
        }

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid Order ID', 'superball-api' ) ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( array( 'message' => __( 'Order not found', 'superball-api' ) ) );
        }

        // Check if already sent
        if ( get_post_meta( $order_id, '_superball_order_sent', true ) ) {
            wp_send_json_error( array( 'message' => __( 'Order already sent', 'superball-api' ) ) );
        }

        // Get Superball products
        $superball_products = $this->order_handler->get_superball_products( $order );
        if ( empty( $superball_products ) ) {
            wp_send_json_error( array( 'message' => __( 'No Superball products in this order', 'superball-api' ) ) );
        }

        // Prepare order data
        $order_data = $this->order_handler->prepare_order_data( $order, $superball_products );

        // Send order via API
        $response = $this->api->send_order( $order_data );

        if ( ! is_wp_error( $response ) ) {
            // Mark order as sent
            update_post_meta( $order_id, '_superball_order_sent', true );
            update_post_meta( $order_id, '_superball_order_date_sent', current_time( 'mysql' ) );
            update_post_meta( $order_id, '_superball_order_id', sanitize_text_field( $response['data']['id_customer_order'] ) );

            wp_send_json_success( array( 'date_sent' => current_time( 'mysql' ) ) );
        } else {
            $this->debug_log->log( 'Order ID ' . $order_id . ' failed to send: ' . $response->get_error_message() );
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }
    }

    /**
     * Handle AJAX request to send all unsent orders
     */
    public function ajax_send_all_superball_orders() {
        check_ajax_referer( 'superball_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'superball-api' ) ) );
        }

        global $wpdb;

        // Fetch unsent orders with Superball products
        $orders = $wpdb->get_results( "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ( 'wc-processing', 'wc-on-hold' )
            AND pm.meta_key = '_superball_order_sent'
            AND pm.meta_value != '1'
        " );

        if ( empty( $orders ) ) {
            wp_send_json_error( array( 'message' => __( 'No unsent orders found', 'superball-api' ) ) );
        }

        $sent_count = 0;
        $failed_orders = array();

        foreach ( $orders as $order ) {
            $order_id = $order->ID;
            $order_obj = wc_get_order( $order_id );

            // Get Superball products
            $superball_products = $this->order_handler->get_superball_products( $order_obj );
            if ( empty( $superball_products ) ) {
                continue; // No Superball products in this order
            }

            // Prepare order data
            $order_data = $this->order_handler->prepare_order_data( $order_obj, $superball_products );

            // Send order via API
            $response = $this->api->send_order( $order_data );

            if ( ! is_wp_error( $response ) ) {
                // Mark order as sent
                update_post_meta( $order_id, '_superball_order_sent', true );
                update_post_meta( $order_id, '_superball_order_date_sent', current_time( 'mysql' ) );
                update_post_meta( $order_id, '_superball_order_id', sanitize_text_field( $response['data']['id_customer_order'] ) );
                $sent_count++;
            } else {
                $failed_orders[] = $order_id;
                $this->debug_log->log( 'Order ID ' . $order_id . ' failed to send: ' . $response->get_error_message() );
            }
        }

        if ( $sent_count > 0 ) {
            $message = sprintf( __( 'Successfully sent %d orders.', 'superball-api' ), $sent_count );
            if ( ! empty( $failed_orders ) ) {
                $message .= ' ' . sprintf( __( 'Failed to send %d orders.', 'superball-api' ), count( $failed_orders ) );
            }
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'No orders were sent.', 'superball-api' ) ) );
        }
    }

    /**
     * Handle AJAX request to view a Superball order
     */
    public function ajax_view_superball_order() {
        check_ajax_referer( 'superball_ajax_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'superball-api' ) ) );
        }

        $superball_order_id = isset( $_POST['superball_order_id'] ) ? sanitize_text_field( $_POST['superball_order_id'] ) : '';

        if ( empty( $superball_order_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Superball Order ID is missing.', 'superball-api' ) ) );
        }

        // Fetch API settings
        $settings = get_option( 'superball_api_settings', array() );
        $access_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $password    = isset( $settings['password'] ) ? $settings['password'] : '';

        if ( empty( $access_key ) || empty( $password ) ) {
            wp_send_json_error( array( 'message' => __( 'API Key or Password is missing.', 'superball-api' ) ) );
        }

        // Construiește URL-ul API pentru a citi comanda
        $api_url = 'https://b2b.green-future.ro/api-v1/customer-order/read?id_customer_order=' . urlencode( $superball_order_id );

        // Setează argumentele pentru cerere
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $access_key . ':' . $password ),
            ),
            'timeout' => 60,
        );

        // Trimite cererea API
        $response = wp_remote_get( $api_url, $args );

        // Verifică dacă cererea a eșuat
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => sprintf( __( 'Failed to retrieve Superball Order: %s', 'superball-api' ), esc_html( $response->get_error_message() ) ) ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code != 200 ) {
            $this->debug_log->log( "Failed to retrieve Superball Order ID $superball_order_id. HTTP Status Code: $response_code." );
            wp_send_json_error( array( 'message' => sprintf( __( 'Failed to retrieve Superball Order. HTTP Status Code: %d', 'superball-api' ), $response_code ) ) );
        }

        $decoded_response = json_decode( $response_body, true );

        if ( isset( $decoded_response['is_success'] ) && $decoded_response['is_success'] == 1 ) {
            wp_send_json_success( array( 'data' => $decoded_response['data'] ) );
        } else {
            $message = isset( $decoded_response['message'] ) ? $decoded_response['message'] : __( 'Unknown error.', 'superball-api' );
            $this->debug_log->log( "Failed to retrieve Superball Order ID $superball_order_id: $message" );
            wp_send_json_error( array( 'message' => esc_html( $message ) ) );
        }
    }
}
?>
