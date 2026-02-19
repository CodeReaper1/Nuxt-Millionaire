<?php
/**
 * WPGraphQL extensions for the Econt GraphQL Bridge.
 *
 * Registers custom GraphQL types, root queries, mutations and extra fields
 * on the WooGraphQL Order type so that the Nuxt front-end can:
 *
 *  1. Fetch Econt offices / cities.
 *  2. Calculate shipping prices.
 *  3. Persist the chosen office to an order.
 *  4. Read Econt meta-data back from orders.
 *
 * @package Econt_GraphQL_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Econt_Bridge_GraphQL {

    /** @var self|null */
    private static $instance = null;

    /** @var Econt_Bridge_API */
    private Econt_Bridge_API $api;

    // ─── Singleton ───────────────────────────────────────────────────

    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── Constructor ─────────────────────────────────────────────────

    private function __construct() {
        $this->api = Econt_Bridge_API::instance();

        add_action( 'graphql_register_types', [ $this, 'register_types' ] );
    }

    // ─── Registration entry point ────────────────────────────────────

    public function register_types(): void {
        $this->register_office_type();
        $this->register_city_type();
        $this->register_shipping_rate_type();

        $this->register_queries();
        $this->register_mutations();
        $this->register_order_fields();
    }

    // =====================================================================
    //  Custom Types
    // =====================================================================

    private function register_office_type(): void {
        register_graphql_object_type( 'EcontOffice', [
            'description' => __( 'An Econt office / pickup point.', 'econt-graphql-bridge' ),
            'fields'      => [
                'id'                  => [ 'type' => 'String', 'description' => 'Office ID' ],
                'code'                => [ 'type' => 'String', 'description' => 'Office code' ],
                'name'                => [ 'type' => 'String', 'description' => 'Office name' ],
                'nameEn'              => [ 'type' => 'String', 'description' => 'Office name (English)' ],
                'address'             => [ 'type' => 'String', 'description' => 'Full address' ],
                'city'                => [ 'type' => 'String', 'description' => 'City name' ],
                'postCode'            => [ 'type' => 'String', 'description' => 'Postal code' ],
                'latitude'            => [ 'type' => 'Float',  'description' => 'Latitude coordinate' ],
                'longitude'           => [ 'type' => 'Float',  'description' => 'Longitude coordinate' ],
                'workingTimeFrom'     => [ 'type' => 'String', 'description' => 'Weekday opening time' ],
                'workingTimeTo'       => [ 'type' => 'String', 'description' => 'Weekday closing time' ],
                'workingTimeHalfFrom' => [ 'type' => 'String', 'description' => 'Saturday opening time' ],
                'workingTimeHalfTo'   => [ 'type' => 'String', 'description' => 'Saturday closing time' ],
            ],
        ] );
    }

    private function register_city_type(): void {
        register_graphql_object_type( 'EcontCity', [
            'description' => __( 'A Bulgarian city supported by Econt.', 'econt-graphql-bridge' ),
            'fields'      => [
                'id'         => [ 'type' => 'String', 'description' => 'City ID' ],
                'name'       => [ 'type' => 'String', 'description' => 'City name (Bulgarian)' ],
                'nameEn'     => [ 'type' => 'String', 'description' => 'City name (English)' ],
                'postCode'   => [ 'type' => 'String', 'description' => 'Default postal code' ],
                'regionName' => [ 'type' => 'String', 'description' => 'Region / Oblast name' ],
            ],
        ] );
    }

    private function register_shipping_rate_type(): void {
        register_graphql_object_type( 'EcontShippingRate', [
            'description' => __( 'A calculated Econt shipping rate.', 'econt-graphql-bridge' ),
            'fields'      => [
                'price'        => [ 'type' => 'Float',  'description' => 'Total shipping price in BGN' ],
                'currency'     => [ 'type' => 'String', 'description' => 'Currency code' ],
                'deliveryDays' => [ 'type' => 'Int',    'description' => 'Estimated business days' ],
            ],
        ] );
    }

    // =====================================================================
    //  Root Queries
    // =====================================================================

    private function register_queries(): void {
        // ── econtOffices ─────────────────────────────────────────────
        register_graphql_field( 'RootQuery', 'econtOffices', [
            'type'        => [ 'list_of' => 'EcontOffice' ],
            'description' => __( 'List Econt offices, optionally filtered by city or search term.', 'econt-graphql-bridge' ),
            'args'        => [
                'city'   => [ 'type' => 'String', 'description' => 'Filter by city name (Bulgarian)' ],
                'search' => [ 'type' => 'String', 'description' => 'Free-text search on name or address' ],
            ],
            'resolve'     => function ( $root, array $args ) {
                $city   = $args['city'] ?? null;
                $search = isset( $args['search'] ) ? mb_strtolower( $args['search'] ) : null;

                $offices = $this->api->get_offices( $city );

                // Normalise keys coming from the Econt JSON response.
                $offices = array_map( [ $this, 'normalise_office' ], $offices );

                // Free-text filtering.
                if ( $search && ! empty( $offices ) ) {
                    $offices = array_filter( $offices, function ( $o ) use ( $search ) {
                        $haystack = mb_strtolower( ( $o['name'] ?? '' ) . ' ' . ( $o['address'] ?? '' ) );
                        return mb_strpos( $haystack, $search ) !== false;
                    } );
                }

                return array_values( $offices );
            },
        ] );

        // ── econtCities ─────────────────────────────────────────────
        register_graphql_field( 'RootQuery', 'econtCities', [
            'type'        => [ 'list_of' => 'EcontCity' ],
            'description' => __( 'List Econt-supported Bulgarian cities.', 'econt-graphql-bridge' ),
            'args'        => [
                'search' => [ 'type' => 'String', 'description' => 'Search by city name' ],
            ],
            'resolve'     => function ( $root, array $args ) {
                $search = isset( $args['search'] ) ? mb_strtolower( $args['search'] ) : null;

                $cities = $this->api->get_cities();

                // Normalise keys.
                $cities = array_map( [ $this, 'normalise_city' ], $cities );

                if ( $search && ! empty( $cities ) ) {
                    $cities = array_filter( $cities, function ( $c ) use ( $search ) {
                        return mb_strpos( mb_strtolower( $c['name'] ?? '' ), $search ) !== false;
                    } );
                }

                return array_values( $cities );
            },
        ] );

        // ── econtCalculateShipping ──────────────────────────────────
        register_graphql_field( 'RootQuery', 'econtCalculateShipping', [
            'type'        => 'EcontShippingRate',
            'description' => __( 'Calculate Econt shipping price via LabelService (mode=calculate).', 'econt-graphql-bridge' ),
            'args'        => [
                'weight'             => [ 'type' => [ 'non_null' => 'Float' ], 'description' => 'Weight in kg' ],
                'receiverCity'       => [ 'type' => 'String', 'description' => 'Receiver city name (Bulgarian). Required if receiverOfficeCode is not set.' ],
                'senderCity'         => [ 'type' => 'String', 'description' => 'Sender city (defaults to store city)' ],
                'senderOfficeCode'   => [ 'type' => 'String', 'description' => 'Sender office code (for office-to-office)' ],
                'receiverOfficeCode' => [ 'type' => 'String', 'description' => 'Receiver office code (for office-to-office)' ],
                'codAmount'          => [ 'type' => 'Float',  'description' => 'Cash-on-delivery amount in EUR' ],
            ],
            'resolve'     => function ( $root, array $args ) {
                $params = [
                    'weight'              => $args['weight'],
                    'sender_city'         => $args['senderCity'] ?? $this->api->get_sender_city(),
                    'receiver_city'       => $args['receiverCity'] ?? '',
                    'sender_office_code'  => $args['senderOfficeCode'] ?? null,
                    'receiver_office_code'=> $args['receiverOfficeCode'] ?? null,
                    'cod_amount'          => $args['codAmount'] ?? 0,
                ];

                $result = $this->api->calculate_shipping( $params );

                if ( ! $result ) {
                    return null;
                }

                return [
                    'price'        => isset( $result['price'] ) ? (float) $result['price'] : 0,
                    'currency'     => $result['currency'] ?? 'EUR',
                    'deliveryDays' => isset( $result['delivery_days'] ) ? (int) $result['delivery_days'] : null,
                ];
            },
        ] );
    }

    // =====================================================================
    //  Mutations
    // =====================================================================

    private function register_mutations(): void {
        // ── saveEcontOfficeToOrder ───────────────────────────────────
        register_graphql_mutation( 'saveEcontOfficeToOrder', [
            'inputFields'  => [
                'orderId'       => [ 'type' => [ 'non_null' => 'Int' ],    'description' => 'WooCommerce order database ID' ],
                'officeCode'    => [ 'type' => [ 'non_null' => 'String' ], 'description' => 'Econt office code' ],
                'officeName'    => [ 'type' => 'String', 'description' => 'Office display name' ],
                'officeAddress' => [ 'type' => 'String', 'description' => 'Office full address' ],
                'officeCity'    => [ 'type' => 'String', 'description' => 'Office city' ],
            ],
            'outputFields' => [
                'success' => [ 'type' => 'Boolean', 'description' => 'Whether the operation succeeded' ],
                'message' => [ 'type' => 'String',  'description' => 'Result message' ],
            ],
            'mutateAndGetPayload' => function ( array $input ) {
                $order_id = absint( $input['orderId'] );
                $order    = wc_get_order( $order_id );

                if ( ! $order ) {
                    return [
                        'success' => false,
                        'message' => __( 'Order not found.', 'econt-graphql-bridge' ),
                    ];
                }

                // Save using Bulgarisation-compatible meta keys.
                $order->update_meta_data( '_econt_office_code',    sanitize_text_field( $input['officeCode'] ) );
                $order->update_meta_data( '_econt_office_name',    sanitize_text_field( $input['officeName'] ?? '' ) );
                $order->update_meta_data( '_econt_office_address', sanitize_text_field( $input['officeAddress'] ?? '' ) );
                $order->update_meta_data( '_econt_office_city',    sanitize_text_field( $input['officeCity'] ?? '' ) );
                $order->save();

                $order->add_order_note(
                    sprintf(
                        /* translators: 1: office name, 2: office address */
                        __( 'Econt office selected: %1$s — %2$s', 'econt-graphql-bridge' ),
                        $input['officeName'] ?? $input['officeCode'],
                        $input['officeAddress'] ?? ''
                    )
                );

                return [
                    'success' => true,
                    'message' => __( 'Office saved successfully.', 'econt-graphql-bridge' ),
                ];
            },
        ] );
    }

    // =====================================================================
    //  Extra fields on WooGraphQL Order type
    // =====================================================================

    private function register_order_fields(): void {
        $meta_fields = [
            'econtOfficeCode'    => '_econt_office_code',
            'econtOfficeName'    => '_econt_office_name',
            'econtOfficeAddress' => '_econt_office_address',
            'econtOfficeCity'    => '_econt_office_city',
            'econtTrackingNumber'=> '_econt_tracking_number',
            'econtLabelUrl'      => '_econt_label_url',
        ];

        foreach ( $meta_fields as $field_name => $meta_key ) {
            register_graphql_field( 'Order', $field_name, [
                'type'        => 'String',
                'description' => sprintf( 'Econt meta: %s', $meta_key ),
                'resolve'     => function ( $order_model ) use ( $meta_key ) {
                    $order_id = $order_model->databaseId ?? null;
                    if ( ! $order_id ) {
                        return null;
                    }
                    $order = wc_get_order( $order_id );
                    if ( ! $order ) {
                        return null;
                    }
                    $value = $order->get_meta( $meta_key, true );
                    return $value ?: null;
                },
            ] );
        }
    }

    // =====================================================================
    //  Normalisers  — map Econt JSON keys to our GraphQL field names
    // =====================================================================

    /**
     * Normalise an office array coming from the Econt API.
     */
    private function normalise_office( array $raw ): array {
        // The Econt API may use nested structures; flatten what we need.
        $address_obj = $raw['address'] ?? [];
        $city_obj    = $address_obj['city'] ?? [];

        return [
            'id'                  => (string) ( $raw['id'] ?? $raw['code'] ?? '' ),
            'code'                => (string) ( $raw['code'] ?? '' ),
            'name'                => $raw['name'] ?? '',
            'nameEn'              => $raw['nameEn'] ?? '',
            'address'             => is_string( $address_obj )
                                        ? $address_obj
                                        : ( $address_obj['fullAddress'] ?? $address_obj['street'] ?? '' ),
            'city'                => is_string( $city_obj )
                                        ? $city_obj
                                        : ( $city_obj['name'] ?? '' ),
            'postCode'            => (string) ( $city_obj['postCode'] ?? $address_obj['zip'] ?? '' ),
            'latitude'            => isset( $raw['address']['location']['latitude'] )
                                        ? (float) $raw['address']['location']['latitude']
                                        : null,
            'longitude'           => isset( $raw['address']['location']['longitude'] )
                                        ? (float) $raw['address']['location']['longitude']
                                        : null,
            'workingTimeFrom'     => $raw['normalBusinessHoursFrom'] ?? $raw['workingTimeFrom'] ?? null,
            'workingTimeTo'       => $raw['normalBusinessHoursTo']   ?? $raw['workingTimeTo']   ?? null,
            'workingTimeHalfFrom' => $raw['halfDayBusinessHoursFrom'] ?? $raw['workingTimeHalfFrom'] ?? null,
            'workingTimeHalfTo'   => $raw['halfDayBusinessHoursTo']   ?? $raw['workingTimeHalfTo']   ?? null,
        ];
    }

    /**
     * Normalise a city array coming from the Econt API.
     */
    private function normalise_city( array $raw ): array {
        return [
            'id'         => (string) ( $raw['id'] ?? '' ),
            'name'       => $raw['name'] ?? '',
            'nameEn'     => $raw['nameEn'] ?? '',
            'postCode'   => (string) ( $raw['postCode'] ?? '' ),
            'regionName' => $raw['regionName'] ?? '',
        ];
    }
}
