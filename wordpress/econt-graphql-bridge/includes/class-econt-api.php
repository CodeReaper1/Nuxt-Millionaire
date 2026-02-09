<?php
/**
 * Econt API wrapper.
 *
 * Reads credentials from the Bulgarisation for WooCommerce plugin settings
 * so there is a single source of truth for API access.
 *
 * @package Econt_GraphQL_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Econt_Bridge_API {

    /** @var self|null */
    private static $instance = null;

    /** @var string Econt API base URL. */
    private string $api_url;

    /** @var string Econt username. */
    private string $username;

    /** @var string Econt password. */
    private string $password;

    /** @var bool Whether we are in test / demo mode. */
    private bool $is_test_mode;

    // ─── Singleton ───────────────────────────────────────────────────

    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── Constructor ─────────────────────────────────────────────────

    private function __construct() {
        $this->load_credentials();
    }

    // ─── Credential loading ──────────────────────────────────────────

    /**
     * Load Econt API credentials.
     *
     * The bridge plugin stores its own settings in `wp_options`:
     *
     *   econt_gql_bridge_test_mode — 'yes' (default) | 'no'
     *   econt_gql_bridge_username  — Econt API username
     *   econt_gql_bridge_password  — Econt API password
     *
     * When test mode is ON (the default), the demo API endpoint and
     * public demo credentials are used automatically. Switch test mode
     * to 'no' and enter your production e-econt.com credentials when
     * going live.
     *
     * Bulgarisation for WooCommerce handles the shipping-rate calculation
     * side separately — it uses its own credentials. This plugin only
     * needs credentials for the office/city/tracking GraphQL queries.
     */
    private function load_credentials(): void {
        $this->is_test_mode = get_option( 'econt_gql_bridge_test_mode', 'yes' ) === 'yes';

        if ( $this->is_test_mode ) {
            $this->api_url  = 'https://demo.econt.com/ee/services';
            $this->username = get_option( 'econt_gql_bridge_username', 'iasp-dev' );
            $this->password = get_option( 'econt_gql_bridge_password', '1Asp-dev' );
        } else {
            $this->api_url  = 'https://ee.econt.com/services';
            $this->username = get_option( 'econt_gql_bridge_username', '' );
            $this->password = get_option( 'econt_gql_bridge_password', '' );
        }
    }

    // ─── Low-level HTTP request ──────────────────────────────────────

    /**
     * Send a JSON POST request to the Econt API.
     *
     * @param string $endpoint Relative endpoint (e.g. Nomenclatures/NomenclaturesService.getOffices.json).
     * @param array  $data     Request body.
     * @return array|false Decoded response or false on failure.
     */
    private function request( string $endpoint, array $data = [] ) {
        $url = trailingslashit( $this->api_url ) . ltrim( $endpoint, '/' );

        $args = [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
            ],
        ];

        if ( ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'Econt GraphQL Bridge API Error: ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            error_log( "Econt GraphQL Bridge API HTTP {$code}: {$body}" );
            return false;
        }

        return json_decode( $body, true );
    }

    // ─── Public API methods ──────────────────────────────────────────

    /**
     * Retrieve Econt offices, optionally filtered by city.
     *
     * Results are cached for 6 hours via WP transients.
     *
     * @param string|null $city City name (Bulgarian) to filter by.
     * @return array List of office arrays.
     */
    public function get_offices( ?string $city = null ): array {
        $cache_key = 'econt_bridge_offices' . ( $city ? '_' . sanitize_title( $city ) : '' );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $data = [ 'countryCode' => 'BGR' ];

        if ( $city ) {
            $data['cityName'] = $city;
        }

        $response = $this->request( 'Nomenclatures/NomenclaturesService.getOffices.json', $data );

        if ( $response && isset( $response['offices'] ) ) {
            $offices = $response['offices'];
            set_transient( $cache_key, $offices, 6 * HOUR_IN_SECONDS );
            return $offices;
        }

        return [];
    }

    /**
     * Retrieve Econt-supported Bulgarian cities.
     *
     * Results are cached for 24 hours.
     *
     * @return array List of city arrays.
     */
    public function get_cities(): array {
        $cache_key = 'econt_bridge_cities';
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return $cached;
        }

        $data = [ 'countryCode' => 'BGR' ];

        $response = $this->request( 'Nomenclatures/NomenclaturesService.getCities.json', $data );

        if ( $response && isset( $response['cities'] ) ) {
            $cities = $response['cities'];
            set_transient( $cache_key, $cities, 24 * HOUR_IN_SECONDS );
            return $cities;
        }

        return [];
    }

    /**
     * Calculate Econt shipping price.
     *
     * @param array $params {
     *     @type float  $weight        Package weight in kg.
     *     @type string $sender_city   Sender city name.
     *     @type string $receiver_city Receiver city name.
     *     @type string $delivery_type Tariff sub-code (default OFFICE_OFFICE).
     *     @type float  $cod_amount    Cash-on-delivery amount (default 0).
     * }
     * @return array|false { price, currency, delivery_days } or false.
     */
    public function calculate_shipping( array $params ) {
        $data = [
            'shipmentType'   => 'PACK',
            'weight'         => (float) ( $params['weight'] ?? 1 ),
            'senderCity'     => $params['sender_city'] ?? '',
            'receiverCity'   => $params['receiver_city'] ?? '',
            'tariffSubCode'  => $params['delivery_type'] ?? 'OFFICE_OFFICE',
        ];

        if ( ! empty( $params['cod_amount'] ) && (float) $params['cod_amount'] > 0 ) {
            $data['cdAmount']   = (float) $params['cod_amount'];
            $data['cdCurrency'] = 'BGN';
        }

        $response = $this->request( 'Calculating/CalculatingService.calculatePrice.json', $data );

        if ( $response && isset( $response['price'] ) ) {
            return [
                'price'         => $response['price']['total'] ?? null,
                'currency'      => $response['price']['currency'] ?? 'BGN',
                'delivery_days' => $response['deliveryDays'] ?? null,
            ];
        }

        return false;
    }

    /**
     * Track an Econt shipment by its tracking number.
     *
     * @param string $tracking_number Econt shipment / AWB number.
     * @return array|false Shipment data or false.
     */
    public function track_shipment( string $tracking_number ) {
        $response = $this->request(
            'Shipments/ShipmentService.getShipmentStatuses.json',
            [ 'shipmentNumbers' => [ $tracking_number ] ]
        );

        if ( $response && ! empty( $response['shipmentStatuses'] ) ) {
            return $response['shipmentStatuses'][0] ?? false;
        }

        return false;
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Get the sender city (used as default in shipping calculations).
     */
    public function get_sender_city(): string {
        return get_option( 'econt_gql_bridge_sender_city', 'София' );
    }

    /**
     * Check whether valid credentials are available.
     */
    public function has_credentials(): bool {
        return ! empty( $this->username ) && ! empty( $this->password );
    }
}
