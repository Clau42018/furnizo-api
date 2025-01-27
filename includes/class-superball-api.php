<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_API {

    private $debug_log;
    public $api_base_url = 'https://b2b.green-future.ro/api-v1'; // Endpoint-ul API-ului Superball

    public function __construct( $debug_log ) {
        $this->debug_log = $debug_log;
    }

    /**
     * Send order data to Superball API
     *
     * @param array $order_data
     * @return array|WP_Error
     */
    public function send_order( $order_data ) {
        $this->debug_log->log( 'Sending order ID: ' . $order_data['id_customer_order_external'] );
        $this->debug_log->log( 'API Endpoint: ' . $this->api_base_url . '/customer-order/create' );

        // Fetch API settings
        $settings = get_option( 'superball_api_settings', array() );
        $access_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $password = isset( $settings['password'] ) ? $settings['password'] : '';

        // Log access_key și password
        $this->debug_log->log( 'Access Key: ' . $access_key );
        $this->debug_log->log( 'Password: ' . $password );

        // Verifică dacă access_key și password sunt setate
        if ( empty( $access_key ) || empty( $password ) ) {
            $this->debug_log->log( 'Access key or password is missing in settings.' );
            return new WP_Error( 'missing_credentials', __( 'Access key or password is missing in settings.', 'superball-api' ) );
        }

        // Construiește header-ul Authorization pentru Basic Auth
        $auth = base64_encode( $access_key . ':' . $password );
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Basic ' . $auth,
            'X-Access-Key'  => $access_key,
        );

        // Log headers pentru verificare
        $this->debug_log->log( 'Request Headers: ' . wp_json_encode( $headers ) );

        // Asigură-te că access_key și password nu sunt în corpul cererii
        // Eliminăm orice eventuale câmpuri din $order_data care conțin access_key și password
        unset( $order_data['access_key'], $order_data['password'] );

        // Log order data înainte de trimitere
        $this->debug_log->log( 'Request Body: ' . wp_json_encode( $order_data ) );

        // Trimite cererea
        $response = wp_remote_post( $this->api_base_url . '/customer-order/create', array(
            'method'      => 'POST',
            'headers'     => $headers,
            'body'        => wp_json_encode( $order_data ),
            'data_format' => 'body',
        ) );

        if ( is_wp_error( $response ) ) {
            $this->debug_log->log( 'API Request Failed: ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        $this->debug_log->log( 'API Response Code: ' . $response_code );
        $this->debug_log->log( 'API Response Body: ' . $response_body );

        $decoded_response = json_decode( $response_body, true );

        if ( $response_code == 200 && isset( $decoded_response['is_success'] ) && $decoded_response['is_success'] == 1 ) {
            return $decoded_response;
        } else {
            return new WP_Error( 'api_error', isset( $decoded_response['message'] ) ? $decoded_response['message'] : __( 'API responded with an error.', 'superball-api' ) );
        }
    }

    /**
     * Check if plugin is in testing mode
     *
     * @return bool
     */
    public function is_testing() {
        $settings = get_option( 'superball_api_settings', array() );
        return isset( $settings['is_testing'] ) && $settings['is_testing'] ? true : false;
    }

    /**
     * Activation hook
     */
    public static function activate() {
        // Inițializare opțiuni sau alte acțiuni la activarea pluginului
    }

    /**
     * Deactivation hook
     */
    public static function deactivate() {
        // Curățare opțiuni sau alte acțiuni la dezactivarea pluginului
    }
}


?>
