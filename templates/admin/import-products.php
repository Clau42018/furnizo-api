<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
?>

<div class="wrap">
    <h1><?php esc_html_e( 'Import Products', 'superball-api' ); ?></h1>
    <p><?php esc_html_e( 'Click the button below to import products from the Superball feed.', 'superball-api' ); ?></p>
    <button type="button" class="button button-primary" id="import-products-now"><?php esc_html_e( 'Import Products Now', 'superball-api' ); ?></button>
    <span id="import-products-now-status" style="margin-left:10px;"></span>
</div>
