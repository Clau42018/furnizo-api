<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_Admin {

    private $api;
    private $debug_log;
    private $order_handler;
    private $product_importer;

    public function __construct( $api, $debug_log, $order_handler ) {
        $this->api = $api;
        $this->debug_log = $debug_log;
        $this->order_handler = $order_handler;

        // Inițializarea importatorului de produse
        require_once SUPERBALL_API_PLUGIN_DIR . 'includes/class-superball-product-importer.php';
        $this->product_importer = new Superball_Product_Importer( $this->debug_log );

        // Add admin menus
        add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Register AJAX handler for updating stocks manually
        add_action( 'wp_ajax_update_stock_now', array( $this, 'ajax_update_stock_now' ) );

        // Register AJAX handler for importing products manually
        add_action( 'wp_ajax_import_products_now', array( $this, 'ajax_import_products_now' ) );
    }

    /**
     * Add admin menus and submenus
     */
    public function add_admin_menus() {
        add_menu_page(
            __( 'Superball API', 'superball-api' ),
            __( 'Superball API', 'superball-api' ),
            'manage_options',
            'superball-api',
            array( $this, 'render_orders_list' ),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'superball-api',
            __( 'Orders List', 'superball-api' ),
            __( 'Orders List', 'superball-api' ),
            'manage_options',
            'superball-api',
            array( $this, 'render_orders_list' )
        );

        add_submenu_page(
            'superball-api',
            __( 'API Settings', 'superball-api' ),
            __( 'API Settings', 'superball-api' ),
            'manage_options',
            'superball-api-settings',
            array( $this, 'render_api_settings' )
        );
/*
        add_submenu_page(
            'superball-api',
            __( 'Import Products', 'superball-api' ),
            __( 'Import Products', 'superball-api' ),
            'manage_options',
            'superball-api-import-products',
            array( $this, 'render_import_products' )
        );
 */

        add_submenu_page(
            'superball-api',
            __( 'Debug Log', 'superball-api' ),
            __( 'Debug Log', 'superball-api' ),
            'manage_options',
            'superball-api-debug-log',
            array( $this, 'render_debug_log' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'superball_api_settings_group', 'superball_api_settings', array( $this, 'sanitize_settings' ) );

        // API Configuration Section
        add_settings_section(
            'superball_api_main_section',
            __( 'API Configuration', 'superball-api' ),
            array( $this, 'settings_section_callback' ),
            'superball_api_settings_page'
        );

        add_settings_field(
            'api_key',
            __( 'API Key', 'superball-api' ),
            array( $this, 'api_key_callback' ),
            'superball_api_settings_page',
            'superball_api_main_section'
        );

        add_settings_field(
            'password',
            __( 'Password', 'superball-api' ),
            array( $this, 'password_callback' ),
            'superball_api_settings_page',
            'superball_api_main_section'
        );

        add_settings_field(
            'is_testing',
            __( 'Testing Mode', 'superball-api' ),
            array( $this, 'is_testing_callback' ),
            'superball_api_settings_page',
            'superball_api_main_section'
        );

        // Stock Update Section
        add_settings_section(
            'superball_stock_update_section',
            __( 'Stock Update Configuration', 'superball-api' ),
            array( $this, 'stock_update_section_callback' ),
            'superball_api_settings_page'
        );

        add_settings_field(
            'enable_stock_update',
            __( 'Enable Stock Update', 'superball-api' ),
            array( $this, 'enable_stock_update_callback' ),
            'superball_api_settings_page',
            'superball_stock_update_section'
        );

        add_settings_field(
            'stock_update_frequency',
            __( 'Stock Update Frequency', 'superball-api' ),
            array( $this, 'stock_update_frequency_callback' ),
            'superball_api_settings_page',
            'superball_stock_update_section'
        );

        // Product Import Section
        add_settings_section(
            'superball_product_import_section',
            __( 'Product Import Configuration', 'superball-api' ),
            array( $this, 'product_import_section_callback' ),
            'superball_api_settings_page'
        );

        add_settings_field(
            'price_markup',
            __( 'Adaos la preț (%)', 'superball-api' ),
            array( $this, 'price_markup_callback' ),
            'superball_api_settings_page',
            'superball_product_import_section'
        );

        add_settings_field(
            'price_preview',
            __( 'Previzualizare Calcul Preț', 'superball-api' ),
            array( $this, 'price_preview_callback' ),
            'superball_api_settings_page',
            'superball_product_import_section'
        );
    }

    /**
     * Sanitize settings input
     *
     * @param array $input
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        if ( isset( $input['api_key'] ) ) {
            $sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
        }

        if ( isset( $input['password'] ) ) {
            $sanitized['password'] = sanitize_text_field( $input['password'] );
        }

        $sanitized['is_testing'] = isset( $input['is_testing'] ) ? (bool) $input['is_testing'] : false;

        // Stock Update Settings
        $sanitized['enable_stock_update'] = isset( $input['enable_stock_update'] ) ? (bool) $input['enable_stock_update'] : false;

        if ( isset( $input['stock_update_frequency'] ) ) {
            $allowed_frequencies = array( 'hourly', 'twicedaily', 'daily' );
            if ( in_array( $input['stock_update_frequency'], $allowed_frequencies ) ) {
                $sanitized['stock_update_frequency'] = $input['stock_update_frequency'];
            } else {
                $sanitized['stock_update_frequency'] = 'daily';
            }
        } else {
            $sanitized['stock_update_frequency'] = 'daily';
        }

        // Product Import Settings
        if ( isset( $input['price_markup'] ) ) {
            $markup = floatval( $input['price_markup'] );
            $sanitized['price_markup'] = ( $markup >= 0 ) ? $markup : 0;
        } else {
            $sanitized['price_markup'] = 0;
        }

        return $sanitized;
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html__( 'Introduceți acreditările API Superball mai jos.', 'superball-api' ) . '</p>';
    }

    /**
     * Stock Update section callback
     */
    public function stock_update_section_callback() {
        echo '<p>' . esc_html__( 'Configurați actualizările automate ale stocurilor din feed-ul Superball.', 'superball-api' ) . '</p>';
    }

    /**
     * Product Import section callback
     */
    public function product_import_section_callback() {
        echo '<p>' . esc_html__( 'Configurați setările de import al produselor din feed-ul Superball.', 'superball-api' ) . '</p>';
    }

    /**
     * API Key field callback
     */
    public function api_key_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $api_key = isset( $settings['api_key'] ) ? esc_attr( $settings['api_key'] ) : '';
        echo '<input type="text" name="superball_api_settings[api_key]" value="' . $api_key . '" class="regular-text" />';
    }

    /**
     * Password field callback
     */
    public function password_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $password = isset( $settings['password'] ) ? esc_attr( $settings['password'] ) : '';
        echo '<input type="password" name="superball_api_settings[password]" value="' . $password . '" class="regular-text" />';
    }

    /**
     * Testing mode field callback
     */
    public function is_testing_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $is_testing = isset( $settings['is_testing'] ) ? $settings['is_testing'] : false;
        echo '<input type="checkbox" name="superball_api_settings[is_testing]" value="1" ' . checked( 1, $is_testing, false ) . ' />';
        echo '<label for="is_testing"> ' . esc_html__( 'Activează modul de testare', 'superball-api' ) . '</label>';
    }

    /**
     * Enable Stock Update field callback
     */
    public function enable_stock_update_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $enable_stock_update = isset( $settings['enable_stock_update'] ) ? $settings['enable_stock_update'] : false;
        echo '<input type="checkbox" name="superball_api_settings[enable_stock_update]" value="1" ' . checked( 1, $enable_stock_update, false ) . ' />';
        echo '<label for="enable_stock_update"> ' . esc_html__( 'Activează actualizările automate ale stocurilor din feed-ul Superball.', 'superball-api' ) . '</label>';
    }

    /**
     * Stock Update Frequency field callback
     */
    public function stock_update_frequency_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $frequency = isset( $settings['stock_update_frequency'] ) ? $settings['stock_update_frequency'] : 'daily';
        ?>
        <select name="superball_api_settings[stock_update_frequency]">
            <option value="hourly" <?php selected( $frequency, 'hourly' ); ?>><?php esc_html_e( 'Orar', 'superball-api' ); ?></option>
            <option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>><?php esc_html_e( 'De două ori pe zi', 'superball-api' ); ?></option>
            <option value="daily" <?php selected( $frequency, 'daily' ); ?>><?php esc_html_e( 'Zilnic', 'superball-api' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'Selectează frecvența cu care să se actualizeze nivelurile de stoc din feed.', 'superball-api' ); ?></p>
        <?php
    }

    /**
     * Price Markup field callback
     */
    public function price_markup_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $markup = isset( $settings['price_markup'] ) ? floatval( $settings['price_markup'] ) : 0;
        echo '<input type="number" step="0.01" min="0" name="superball_api_settings[price_markup]" value="' . esc_attr( $markup ) . '" class="small-text" /> %';
    }

    /**
     * Price Preview field callback
     */
    public function price_preview_callback() {
        $settings = get_option( 'superball_api_settings', array() );
        $markup = isset( $settings['price_markup'] ) ? floatval( $settings['price_markup'] ) : 0;
        $example_price = 100; // Exemplu de preț fără TVA
        $calculated_price = $example_price * (1 + ( $markup / 100 ));
        ?>
        <p><?php printf( __( 'Exemplu de calcul: %s x (1 + (%s / 100)) = %s', 'superball-api' ), 
            '<strong>' . esc_html( $example_price ) . '</strong>', 
            '<strong>' . esc_html( $markup ) . '</strong>', 
            '<strong>' . esc_html( number_format( $calculated_price, 2 ) ) . '</strong>' ); ?></p>
        <p><?php esc_html_e( 'Notă: Prețurile din feed nu includ TVA.', 'superball-api' ); ?></p>
        <?php
    }

    /**
     * Render API Settings Page
     */
public function render_api_settings() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Setări Superball API', 'superball-api' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'superball_api_settings_group' );
            do_settings_sections( 'superball_api_settings_page' );
            submit_button();
            ?>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Importă Produse', 'superball-api' ); ?></h2>
        <p><?php esc_html_e( 'Apasă butonul de mai jos pentru a importa produsele din feed-ul Superball.', 'superball-api' ); ?></p>
        <button type="button" class="button button-primary" id="import-products-now"><?php esc_html_e( 'Importă Produse Acum', 'superball-api' ); ?></button>
        <span id="import-products-now-status" style="margin-left:10px;"></span>
    </div>
    <?php
}


    /**
     * Render Import Products Page
     */
    public function render_import_products() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Importă Produse', 'superball-api' ); ?></h1>
            <p><?php esc_html_e( 'Apasă butonul de mai jos pentru a importa produsele din feed-ul Superball.', 'superball-api' ); ?></p>
            <button type="button" class="button button-primary" id="import-products-now"><?php esc_html_e( 'Importă Produse Acum', 'superball-api' ); ?></button>
            <span id="import-products-now-status" style="margin-left:10px;"></span>
        </div>
        <?php
    }

    /**
     * Render Orders List Page
     */
    public function render_orders_list() {
        include SUPERBALL_API_PLUGIN_DIR . 'templates/admin/orders-list.php';
    }

    /**
     * Render Debug Log Page
     */
    public function render_debug_log() {
        $debug_log = $this->debug_log->get_log_contents();
        include SUPERBALL_API_PLUGIN_DIR . 'templates/admin/debug-log.php';
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        if ( strpos( $hook_suffix, 'superball-api' ) === false ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style( 'superball-admin-css', SUPERBALL_API_PLUGIN_URL . 'assets/css/admin.css', array(), SUPERBALL_API_VERSION );

        // Enqueue JS
        wp_enqueue_script( 'superball-admin-js', SUPERBALL_API_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SUPERBALL_API_VERSION, true );

        // Localize script for AJAX
        wp_localize_script( 'superball-admin-js', 'superball_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'superball_ajax_nonce' ),
            'strings'  => array(
                'sending'            => __( 'Se trimit...', 'superball-api' ),
                'send_order'         => __( 'Trimite Comandă', 'superball-api' ),
                'send_all_orders'    => __( 'Trimite Toate Comenzile', 'superball-api' ),
                'order_sent'         => __( 'Da', 'superball-api' ),
                'order_not_sent'     => __( 'Nu', 'superball-api' ),
                'error_occurred'     => __( 'A apărut o eroare.', 'superball-api' ),
                'confirm_send_order' => __( 'Ești sigur că dorești să trimiți Comanda #', 'superball-api' ) . ' {order_id} ' . __( 'la Superball?', 'superball-api' ),
                'confirm_send_all'   => __( 'Ești sigur că dorești să trimiți toate comenzile nesemnate la Superball?', 'superball-api' ),
                'success_sent'       => __( 'Au fost trimise cu succes {count} comenzi.', 'superball-api' ),
                'failed_sent'        => __( 'Nu au putut fi trimise {count} comenzi.', 'superball-api' ),
                'updating_stocks'    => __( 'Se actualizează stocurile...', 'superball-api' ),
                'update_success'     => __( 'Actualizarea stocurilor a fost inițiată. Verifică log-urile pentru detalii.', 'superball-api' ),
                'update_failure'     => __( 'Nu s-a putut iniția actualizarea stocurilor.', 'superball-api' ),
                'importing_products' => __( 'Se importă produsele...', 'superball-api' ),
                'import_success'     => __( 'Importul produselor a fost inițiat. Verifică log-urile pentru detalii.', 'superball-api' ),
                'import_failure'     => __( 'Nu s-a putut iniția importul produselor.', 'superball-api' ),
            ),
        ) );
    }

    /**
     * AJAX handler to update stocks manually
     */
    public function ajax_update_stock_now() {
        // Verify nonce
        check_ajax_referer( 'superball_ajax_nonce', 'nonce' );

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Acces neautorizat.', 'superball-api' ) ) );
        }

        // Trigger the stock update event
        do_action( 'superball_stock_update_event' );

        // Send success response
        wp_send_json_success( array( 'message' => __( 'Actualizarea stocurilor a fost inițiată. Verifică log-urile pentru detalii.', 'superball-api' ) ) );
    }

    /**
     * AJAX handler to import products manually
     */
    public function ajax_import_products_now() {
        // Verify nonce
        check_ajax_referer( 'superball_ajax_nonce', 'nonce' );

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Acces neautorizat.', 'superball-api' ) ) );
        }

        // Trigger the product import process
        $result = $this->product_importer->import_products();

        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => __( 'Importul produselor a fost inițiat. Verifică log-urile pentru detalii.', 'superball-api' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Nu s-a putut iniția importul produselor.', 'superball-api' ) ) );
        }
    }
}
?>
