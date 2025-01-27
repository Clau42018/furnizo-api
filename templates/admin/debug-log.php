<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Debug Log', 'superball-api' ); ?></h1>
    <textarea readonly style="width:100%; height:500px;"><?php echo esc_textarea( isset( $debug_log ) ? $debug_log : __( 'No logs available.', 'superball-api' ) ); ?></textarea>
</div>
