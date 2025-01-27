<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_Order_Handler {

    private $api;
    private $debug_log;

    public function __construct( $api, $debug_log ) {
        $this->api = $api;
        $this->debug_log = $debug_log;

        // Hook into WooCommerce order status change
        add_action( 'woocommerce_order_status_processing', array( $this, 'process_order' ) );
    }

    /**
     * Process WooCommerce order when it moves to 'processing'
     *
     * @param int $order_id
     */
    public function process_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $superball_products = $this->get_superball_products( $order );

        if ( empty( $superball_products ) ) {
            return; // No Superball products in this order
        }

        // Prepare order data for API
        $order_data = $this->prepare_order_data( $order, $superball_products );

        // Send order via API
        $response = $this->api->send_order( $order_data );

        if ( ! is_wp_error( $response ) ) {
            // Mark order as sent
            update_post_meta( $order_id, '_superball_order_sent', true );
            update_post_meta( $order_id, '_superball_order_date_sent', current_time( 'mysql' ) );
            update_post_meta( $order_id, '_superball_order_id', sanitize_text_field( $response['data']['id_customer_order'] ) );

            // Optionally, add a note to the order
            $order->add_order_note( __( 'Order successfully sent to Superball.', 'superball-api' ) );
        } else {
            // Mark as not sent and log error
            update_post_meta( $order_id, '_superball_order_sent', false );
            $this->debug_log->log( 'Order ID ' . $order_id . ' failed to send: ' . $response->get_error_message() );

            // Optionally, add a note to the order
            $order->add_order_note( sprintf( __( 'Failed to send order to Superball: %s', 'superball-api' ), $response->get_error_message() ) );
        }
    }

    /**
     * Get Superball products from the order
     *
     * @param WC_Order $order
     * @return array
     */
    public function get_superball_products( $order ) {
        $superball_products = array();

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $furnizor = get_post_meta( $product->get_id(), 'wtd_furnizor_produs', true );
            if ( strpos( strtolower( $furnizor ), 'superball' ) !== false ) {
                $superball_products[] = array(
                    'name'     => $product->get_name() ? $product->get_name() : 'N/A',
                    'code'     => $product->get_sku() ? $product->get_sku() : 'N/A',
                    'quantity' => $item->get_quantity() ? $item->get_quantity() : 1,
                );
            }
        }

        return $superball_products;
    }

    /**
     * Prepare order data for API request
     *
     * @param WC_Order $order
     * @param array    $products
     * @return array
     */
    public function prepare_order_data( $order, $products ) {
        // Fetch API settings
        $settings = get_option( 'superball_api_settings', array() );

        // Company Data - Date reale ale companiei Mind Canvas SRL
        $company_data = array(
            'id_customer_order_external' => 'FURNIZO-' . $order->get_id(), // Prefix + ID-ul comenzii
            'id_language'               => 1, // Asigură-te că limba este setată corect
            'domain'                    => 'furnizo.ro', // Domeniul tău
            'shipping_type'             => 'carrier', // Tipul de livrare
            'billing_type'              => 'company', // Tipul de facturare
            'payment_type'              => 'bank_transfer', // Tipul de plată
            'observations'              => $order->get_customer_note() ? $order->get_customer_note() : 'N/A',
            'use_for_testing'           => $this->api->is_testing() ? 1 : 0,
            'currency'                  => 'RON', // Moneda
        );

        // Date reale ale companiei Mind Canvas SRL
        $billing_company = array(
            'company'             => 'Mind Canvas SRL',
            'reg_no'              => 'J40/24810/2023',
            'vat_no'              => '49337905',
            'country'             => 'RO',
            'county'              => 'București',
            'locality'            => 'Sector 3',
            'address'             => 'Calea Calarasilor, Nr. 319A, Ap. 4',
            'postal_code'         => '000000',
            'phone'               => '0722000000',
        );

        // Customer Information
        $customer_email = $order->get_billing_email();
        if ( empty( $customer_email ) ) {
            $customer_email = 'mindcanvas.srl@gmail.com'; // Adresa de email validă
        }

        $customer = array(
            'email' => $customer_email,
        );

        // Shipping Address
        $shipping = array(
            'firstname'    => $order->get_shipping_first_name() ? $order->get_shipping_first_name() : 'N/A',
            'lastname'     => $order->get_shipping_last_name() ? $order->get_shipping_last_name() : 'N/A',
            'country'      => $order->get_shipping_country() ? $order->get_shipping_country() : 'N/A',
            'county'       => $order->get_shipping_state() ? $order->get_shipping_state() : 'N/A',
            'locality'     => $order->get_shipping_city() ? $order->get_shipping_city() : 'N/A',
            'address'      => $order->get_shipping_address_1() ? $order->get_shipping_address_1() : 'N/A',
            'postal_code'  => $order->get_shipping_postcode() ? $order->get_shipping_postcode() : 'N/A',
            'phone'        => $order->get_billing_phone() ? $order->get_billing_phone() : 'N/A',
        );

        // Products
        $api_products = array();
        foreach ( $products as $product ) {
            $api_products[] = array(
                'name'                => isset( $product['name'] ) && ! empty( $product['name'] ) ? $product['name'] : 'N/A',
                'code'                => isset( $product['code'] ) && ! empty( $product['code'] ) ? $product['code'] : 'N/A',
                'quantity'            => isset( $product['quantity'] ) && ! empty( $product['quantity'] ) ? $product['quantity'] : 1,
                'date_delivery'       => '2025-02-23', // Poate fi setat conform logicii tale
                'date_delivery_from'  => '2025-02-23',
                'date_delivery_to'    => '2025-02-23',
            );
        }

        // Customer Order Delivery
        $customer_order_delivery = array(
            'id_customer_order_delivery' => 1,
            'dropshipping'               => 1,
        );

        // Assemble complete order data
        $order_data = array(
            'id_customer_order_external' => $company_data['id_customer_order_external'],
            'id_language'               => $company_data['id_language'],
            'domain'                    => $company_data['domain'],
            'shipping_type'             => $company_data['shipping_type'],
            'billing_type'              => $company_data['billing_type'],
            'payment_type'              => $company_data['payment_type'],
            'observations'              => $company_data['observations'],
            'use_for_testing'           => $company_data['use_for_testing'],
            'currency'                  => $company_data['currency'],
            'products'                  => $api_products,
            'customer'                  => $customer,
            'customer_address_shipping' => $shipping,
            'customer_address_billing_company' => $billing_company,
            'customer_order_delivery'   => $customer_order_delivery,
        );

        // Log order data before sending
        $this->debug_log->log( 'Prepared Order Data: ' . wp_json_encode( $order_data ) );

        // Ensure all fields are present and set to "N/A" or valid defaults if missing
        $required_fields = array(
            'id_customer_order_external',
            'id_language',
            'domain',
            'shipping_type',
            'billing_type',
            'payment_type',
            'observations',
            'use_for_testing',
            'currency',
            'products',
            'customer',
            'customer_address_shipping',
            'customer_address_billing_company',
            'customer_order_delivery',
        );

        foreach ( $required_fields as $field ) {
            if ( ! isset( $order_data[ $field ] ) ) {
                $order_data[ $field ] = 'N/A';
            }
        }

        // Iterate through products to ensure all fields are set
        foreach ( $order_data['products'] as &$product ) {
            $product_required_fields = array(
                'name',
                'code',
                'quantity',
                'date_delivery',
                'date_delivery_from',
                'date_delivery_to',
            );

            foreach ( $product_required_fields as $p_field ) {
                if ( ! isset( $product[ $p_field ] ) || empty( $product[ $p_field ] ) ) {
                    if ( $p_field == 'quantity' ) {
                        $product[ $p_field ] = 1; // Cantitate implicită
                    } else {
                        $product[ $p_field ] = 'N/A';
                    }
                }
            }
        }
        unset( $product ); // Break reference

        return $order_data;
    }
}
?>
