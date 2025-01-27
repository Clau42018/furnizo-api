<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Asigură-te că $this->order_handler este definit
if ( ! isset( $this->order_handler ) || ! $this->order_handler instanceof Superball_Order_Handler ) {
    echo '<p>' . esc_html__( 'Order handler is not available.', 'superball-api' ) . '</p>';
    return;
}

// Fetch orders with Superball products
$args = array(
    'post_type'      => 'shop_order',
    'post_status'    => array_keys( wc_get_order_statuses() ),
    'posts_per_page' => -1,
);

$all_orders = get_posts( $args );

$superball_orders = array();

foreach ( $all_orders as $order_post ) {
    $order = wc_get_order( $order_post->ID );
    if ( ! $order ) {
        continue;
    }

    // Utilizează metoda din order handler pentru a verifica dacă comanda conține produse Superball
    $superball_products = $this->order_handler->get_superball_products( $order );

    if ( ! empty( $superball_products ) ) {
        $superball_orders[] = $order;
    }
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Superball Orders List', 'superball-api' ); ?></h1>
   
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Order ID', 'superball-api' ); ?></th>
                <th><?php esc_html_e( 'Client Name', 'superball-api' ); ?></th>
                <th><?php esc_html_e( 'Superball Products', 'superball-api' ); ?></th>
                <th><?php esc_html_e( 'Order Number', 'superball-api' ); ?></th>
                <th><?php esc_html_e( 'Sent to Superball', 'superball-api' ); ?></th>
                <th><?php esc_html_e( 'Date Sent', 'superball-api' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'superball-api' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $superball_orders ) ) : ?>
                <?php foreach ( $superball_orders as $order ) : 
                    $order_id = $order->get_id();
                    $client_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                    $order_number = $order->get_order_number();
                    $sent = get_post_meta( $order_id, '_superball_order_sent', true ) ? __( 'Yes', 'superball-api' ) : __( 'No', 'superball-api' );
                    $date_sent = get_post_meta( $order_id, '_superball_order_date_sent', true ) ? esc_html( $order->get_meta( '_superball_order_date_sent' ) ) : '-';

                    // Fetch Superball products
                    $superball_products = $this->order_handler->get_superball_products( $order );
                    $products_list = '';
                    if ( ! empty( $superball_products ) ) {
                        foreach ( $superball_products as $product ) {
                            $products_list .= esc_html( $product['name'] ) . ' (Qty: ' . esc_html( $product['quantity'] ) . '), ';
                        }
                        $products_list = rtrim( $products_list, ', ' );
                    } else {
                        $products_list = '-';
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $order_id ); ?></td>
                        <td><?php echo esc_html( $client_name ); ?></td>
                        <td><?php echo esc_html( $products_list ); ?></td>
                        <td><?php echo esc_html( $order_number ); ?></td>
                        <td><?php echo esc_html( $sent ); ?></td>
                        <td><?php echo esc_html( $date_sent ); ?></td>
                        <td>
                            <?php if ( ! get_post_meta( $order_id, '_superball_order_sent', true ) ) : ?>
                                <button class="button send-superball-order" data-order-id="<?php echo esc_attr( $order_id ); ?>"><?php esc_html_e( 'Send Order', 'superball-api' ); ?></button>
                            <?php else : ?>
                                <?php
                                    $superball_order_id = get_post_meta( $order_id, '_superball_order_id', true );
                                    if ( $superball_order_id ) :
                                ?>
                                    <a href="<?php echo esc_url( $this->api->api_base_url . '/customer-order/read?id_customer_order=' . $superball_order_id ); ?>" target="_blank" class="button"><?php esc_html_e( 'View in Superball', 'superball-api' ); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e( 'No Superball orders found.', 'superball-api' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
