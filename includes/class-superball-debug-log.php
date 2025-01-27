<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_Debug_Log {

    private $log_file;

    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/superball-api';
        $this->log_file = $log_dir . '/debug.log';

        // Ensure the log directory exists
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }

        // Ensure the log file exists
        if ( ! file_exists( $this->log_file ) ) {
            file_put_contents( $this->log_file, '' );
        }
    }

    /**
     * Log a message to the debug log file
     *
     * @param string $message
     */
    public function log( $message ) {
        $timestamp = current_time( 'mysql' );
        $formatted_message = '[' . $timestamp . '] ' . $message . PHP_EOL;
        file_put_contents( $this->log_file, $formatted_message, FILE_APPEND );
    }

    /**
     * Get log contents
     *
     * @return string
     */
    public function get_log_contents() {
        if ( file_exists( $this->log_file ) ) {
            return file_get_contents( $this->log_file );
        }
        return __( 'No logs available.', 'superball-api' );
    }
}
