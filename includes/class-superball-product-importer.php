<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Superball_Product_Importer {

    private $debug_log;

    public function __construct( $debug_log ) {
        $this->debug_log = $debug_log;
    }

    /**
     * Importă produsele din feed
     *
     * @return array
     */
    public function import_products() {
        $this->debug_log->log( 'Încep importul produselor din feed-ul Superball.' );

        // URL-ul hardcodat al feed-ului de produse
        $feed_url = 'https://b2b.green-future.ro/api/export-products?key=5271352173ad85fa64301b3c7186855a&data=all';

        $settings = get_option( 'superball_api_settings', array() );
        $access_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $password = isset( $settings['password'] ) ? $settings['password'] : '';
        $price_markup = isset( $settings['price_markup'] ) ? floatval( $settings['price_markup'] ) : 0;

        if ( empty( $feed_url ) ) {
            $this->debug_log->log( 'URL-ul feed-ului de produse nu este setat. Importul produselor a fost oprit.' );
            return array( 'success' => false );
        }

        if ( empty( $access_key ) || empty( $password ) ) {
            $this->debug_log->log( 'Cheia API sau Parola lipsesc. Importul produselor a fost oprit.' );
            return array( 'success' => false );
        }

        // Logare URL-ul utilizat
        $this->debug_log->log( "Preluarea feed-ului de produse de la URL: $feed_url" );

        // Setează argumentele pentru cerere
        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $access_key . ':' . $password ),
            ),
            'timeout' => 120,
        );

        // Trimite cererea API
        $response = wp_remote_get( $feed_url, $args );

        // Verifică dacă cererea a eșuat
        if ( is_wp_error( $response ) ) {
            $this->debug_log->log( 'Eroare la preluarea feed-ului de produse: ' . $response->get_error_message() );
            return array( 'success' => false );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Logare cod de răspuns
        $this->debug_log->log( "Cod de răspuns HTTP: $response_code" );

        if ( $response_code != 200 ) {
            $this->debug_log->log( "Eșec la preluarea feed-ului de produse. Cod de răspuns HTTP: $response_code." );
            return array( 'success' => false );
        }

        // Logare dimensiunea răspunsului
        $this->debug_log->log( "Dimensiunea răspunsului feed-ului de produse: " . strlen( $response_body ) . " bytes." );

        // Verifică dacă răspunsul este gol
        if ( empty( $response_body ) ) {
            $this->debug_log->log( 'Răspunsul feed-ului de produse este gol. Importul produselor a fost oprit.' );
            return array( 'success' => false );
        }

        // Parsează feed-ul (presupunem format CSV)
        $rows = array_map( 'str_getcsv', explode( "\n", $response_body ) );
        $header = array_shift( $rows );

        // Logare header-ul
        $this->debug_log->log( "Header CSV: " . implode( ', ', $header ) );

        if ( empty( $header ) ) {
            $this->debug_log->log( 'Feed-ul de produse este gol sau invalid.' );
            return array( 'success' => false );
        }

        // Maparea automată a câmpurilor din feed în WooCommerce
        $mapped_fields = array();
        foreach ( $header as $index => $column ) {
            $column = strtolower( trim( $column ) );
            switch ( $column ) {
                case 'sku':
                    $mapped_fields['sku'] = $index;
                    break;
                case 'name':
                    $mapped_fields['name'] = $index;
                    break;
                case 'price':
                    $mapped_fields['price'] = $index;
                    break;
                case 'description':
                    $mapped_fields['description'] = $index;
                    break;
                case 'images':
                    $mapped_fields['images'] = $index;
                    break;
                // Poți adăuga mai multe câmpuri după necesitate
                default:
                    // Ignorăm câmpurile nemapate
                    break;
            }
        }

        // Verifică dacă câmpurile obligatorii sunt prezente
        if ( ! isset( $mapped_fields['sku'] ) || ! isset( $mapped_fields['name'] ) || ! isset( $mapped_fields['price'] ) ) {
            $this->debug_log->log( 'Câmpurile obligatorii (SKU, Name, Price) lipsesc în feed-ul de produse.' );
            return array( 'success' => false );
        }

        // Procesarea fiecărui rând din feed
        $imported_count = 0;
        $error_count = 0;

        foreach ( $rows as $index => $row ) {
            // Ignoră rândurile incomplete
            if ( count( $row ) < count( $header ) ) {
                $this->debug_log->log( "Rând incomplet la linia " . ($index + 2) . ". Se ignoră." );
                $error_count++;
                continue;
            }

            // Extrage datele necesare
            $sku = sanitize_text_field( trim( $row[ $mapped_fields['sku'] ] ) );
            $name = sanitize_text_field( trim( $row[ $mapped_fields['name'] ] ) );
            $price = floatval( trim( $row[ $mapped_fields['price'] ] ) );
            $description = isset( $mapped_fields['description'] ) ? sanitize_textarea_field( trim( $row[ $mapped_fields['description'] ] ) ) : '';

            // Aplică adaosul la preț
            $price_with_markup = $price * ( 1 + ( $price_markup / 100 ) );

            // Specificarea că prețurile din feed nu includ TVA
            // Poți adăuga o descriere sau alt câmp personalizat dacă este necesar

            // Verifică dacă produsul există deja
            $existing_product_id = wc_get_product_id_by_sku( $sku );

            if ( $existing_product_id ) {
                // Produsul există, dar conform cerințelor nu trebuie modificat
                $this->debug_log->log( "Produsul cu SKU '$sku' deja există (ID: $existing_product_id). Se ignoră importul acestuia." );
                continue;
            }

            // Creează un nou produs
            $new_product = new WC_Product_Simple();
            $new_product->set_sku( $sku );
            $new_product->set_name( $name );
            $new_product->set_price( $price_with_markup );
            $new_product->set_regular_price( $price_with_markup );
            $new_product->set_description( $description );
            $new_product->set_manage_stock( true );
            $new_product->set_stock_quantity( 0 ); // Poate fi setat ulterior din actualizarea stocurilor
            $new_product->set_status( 'draft' ); // Setează produsul ca ciornă

            $new_product_id = $new_product->save();

            if ( $new_product_id ) {
                // Maparea prețului în câmpul personalizat
                update_post_meta( $new_product_id, 'wtd_cost_achizitie', $price );

                $this->debug_log->log( "Produsul nou a fost importat cu SKU '$sku' (ID: $new_product_id) și preț $price_with_markup (ADAOS: $price_markup%)." );
                $imported_count++;

                // Importă imaginile
                $this->import_product_images( $new_product_id, $row );
            } else {
                $this->debug_log->log( "Eșec la importarea produsului cu SKU '$sku'." );
                $error_count++;
                continue;
            }
        }

        $this->debug_log->log( "Importul produselor din feed-ul Superball a fost finalizat. Importate: $imported_count, Erori: $error_count." );

        return array( 'success' => true );
    }

    /**
     * Importă imaginile pentru un produs
     *
     * @param int $product_id
     * @param array $row
     */
    private function import_product_images( $product_id, $row ) {
        // Verifică dacă există câmpul 'images'
        $header = array_map( 'strtolower', $this->get_feed_header() );
        $images_index = array_search( 'images', $header );

        if ( $images_index === false ) {
            $this->debug_log->log( "Nu a fost găsit câmpul 'images' în CSV. Se ignoră importul imaginilor pentru produsul ID $product_id." );
            return;
        }

        $images_field = sanitize_text_field( trim( $row[ $images_index ] ) );

        if ( empty( $images_field ) ) {
            $this->debug_log->log( "Nu au fost găsite imagini pentru produsul ID $product_id. Se ignoră." );
            return;
        }

        // Împarte URL-urile imaginilor prin virgulă
        $image_urls = array_map( 'trim', explode( ',', $images_field ) );

        if ( empty( $image_urls ) ) {
            $this->debug_log->log( "Nu au fost găsite URL-uri valide pentru imagini la produsul ID $product_id. Se ignoră." );
            return;
        }

        // Prima imagine ca Featured Image
        $featured_image_url = array_shift( $image_urls );

        // Importă Featured Image
        $featured_image_id = $this->download_and_attach_image( $featured_image_url, $product_id );

        if ( $featured_image_id ) {
            set_post_thumbnail( $product_id, $featured_image_id );
            $this->debug_log->log( "Imaginea principală pentru produsul ID $product_id a fost setată din URL: $featured_image_url" );
        } else {
            $this->debug_log->log( "Eșec la setarea imaginii principale pentru produsul ID $product_id din URL: $featured_image_url" );
        }

        // Importă restul imaginilor în galerie
        foreach ( $image_urls as $image_url ) {
            $gallery_image_id = $this->download_and_attach_image( $image_url, $product_id );
            if ( $gallery_image_id ) {
                $gallery = get_post_meta( $product_id, '_product_image_gallery', true );
                $gallery = $gallery ? explode( ',', $gallery ) : array();
                if ( ! in_array( $gallery_image_id, $gallery ) ) {
                    $gallery[] = $gallery_image_id;
                    update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery ) );
                    $this->debug_log->log( "Imagine de galerie adăugată pentru produsul ID $product_id din URL: $image_url" );
                }
            } else {
                $this->debug_log->log( "Eșec la adăugarea imaginii de galerie pentru produsul ID $product_id din URL: $image_url" );
            }
        }
    }

    /**
     * Descărcă și atașează o imagine la un produs
     *
     * @param string $image_url
     * @param int $product_id
     * @return int|false Attachment ID sau false în caz de eșec
     */
    private function download_and_attach_image( $image_url, $product_id ) {
        // Verifică dacă URL-ul este valid
        if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
            $this->debug_log->log( "URL invalid pentru imagine: $image_url" );
            return false;
        }

        // Preia imaginea
        $image = media_sideload_image( $image_url, $product_id, null, 'id' );

        if ( is_wp_error( $image ) ) {
            $this->debug_log->log( "Eșec la descărcarea imaginii din URL: $image_url. Eroare: " . $image->get_error_message() );
            return false;
        }

        return $image;
    }

    /**
     * Obține header-ul din feed
     *
     * @return array
     */
    private function get_feed_header() {
        // URL-ul hardcodat al feed-ului de produse
        $feed_url = 'https://b2b.green-future.ro/api/export-products?key=5271352173ad85fa64301b3c7186855a&data=all';

        $settings = get_option( 'superball_api_settings', array() );
        $access_key = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
        $password = isset( $settings['password'] ) ? $settings['password'] : '';

        if ( empty( $feed_url ) ) {
            $this->debug_log->log( 'URL-ul feed-ului de produse nu este setat. Nu se poate obține header-ul.' );
            return array();
        }

        if ( empty( $access_key ) || empty( $password ) ) {
            $this->debug_log->log( 'Cheia API sau Parola lipsesc. Nu se poate obține header-ul.' );
            return array();
        }

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
            $this->debug_log->log( 'Eroare la preluarea header-ului feed-ului de produse: ' . $response->get_error_message() );
            return array();
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( $response_code != 200 ) {
            $this->debug_log->log( "Eșec la preluarea header-ului feed-ului de produse. Cod de răspuns HTTP: $response_code." );
            return array();
        }

        // Parsează feed-ul (presupunem format CSV)
        $rows = array_map( 'str_getcsv', explode( "\n", $response_body ) );
        $header = array_shift( $rows );

        return $header;
    }
}
?>
