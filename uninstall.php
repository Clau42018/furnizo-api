<?php
/**
 * Uninstall Superball API Integration
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit; // Exit if accessed directly
}

// Delete plugin options
delete_option( 'superball_api_settings' );

// Delete debug log if exists
$upload_dir = wp_upload_dir();
$log_file = $upload_dir['basedir'] . '/superball-api/debug.log';
if ( file_exists( $log_file ) ) {
    unlink( $log_file );
}
