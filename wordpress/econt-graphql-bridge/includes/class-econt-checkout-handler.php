<?php
/**
 * Checkout handler — persists Econt office meta that arrives via the
 * WooGraphQL checkout mutation's `metaData` array.
 *
 * When the Nuxt front-end submits the checkout, it includes entries like:
 *
 *   { key: '_econt_office_code',    value: '1234' }
 *   { key: '_econt_office_name',    value: 'Офис София - Младост 1' }
 *   { key: '_econt_office_address', value: 'бул. Ал. Малинов 51' }
 *   { key: '_econt_office_city',    value: 'София' }
 *
 * WooCommerce (via WooGraphQL) stores these in the order's meta table
 * automatically, but Bulgarisation may look for them in a specific format.
 * This handler ensures the data is persisted correctly and any extra
 * post-processing is done (e.g. order notes).
 *
 * @package Econt_GraphQL_Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Econt_Bridge_Checkout_Handler {

    /** @var self|null */
    private static $instance = null;

    /** Meta keys the front-end may send. */
    private const ECONT_META_KEYS = [
        '_econt_office_code',
        '_econt_office_name',
        '_econt_office_address',
        '_econt_office_city',
    ];

    // ─── Singleton ───────────────────────────────────────────────────

    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── Constructor ─────────────────────────────────────────────────

    private function __construct() {
        // Fires after WooCommerce creates the order but before payment.
        // Priority 20 so it runs after WooGraphQL has stored metaData.
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'on_order_processed' ], 20, 3 );

        // Also hook into the newer HPOS-compatible action (WC 7.1+).
        add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'on_store_api_order' ], 20, 1 );

        // WooGraphQL fires its own action after checkout mutation resolves.
        add_action( 'graphql_woocommerce_after_checkout', [ $this, 'on_graphql_checkout' ], 20, 2 );
    }

    // ─── Hooks ───────────────────────────────────────────────────────

    /**
     * Classic WooCommerce checkout hook.
     *
     * @param int      $order_id   The new order ID.
     * @param array    $posted     Posted checkout data.
     * @param \WC_Order $order     The order object.
     */
    public function on_order_processed( int $order_id, $posted, $order ): void {
        if ( ! $order instanceof \WC_Order ) {
            $order = wc_get_order( $order_id );
        }
        if ( $order ) {
            $this->maybe_persist_econt_meta( $order );
        }
    }

    /**
     * Store API / Block-based checkout hook.
     *
     * @param \WC_Order $order The order object.
     */
    public function on_store_api_order( $order ): void {
        if ( $order instanceof \WC_Order ) {
            $this->maybe_persist_econt_meta( $order );
        }
    }

    /**
     * WooGraphQL-specific hook after checkout mutation.
     *
     * @param mixed $checkout Checkout result.
     * @param mixed $input    GraphQL mutation input.
     */
    public function on_graphql_checkout( $checkout, $input ): void {
        $order_id = $checkout['order']->databaseId ?? null;
        if ( ! $order_id ) {
            return;
        }
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $this->maybe_persist_econt_meta( $order );
        }
    }

    // ─── Core logic ──────────────────────────────────────────────────

    /**
     * Check if the order already has Econt meta saved (by WooGraphQL's
     * automatic metaData handling) and add a human-readable order note.
     *
     * If the meta is present but not yet formatted for Bulgarisation,
     * re-save it in the expected format.
     */
    private function maybe_persist_econt_meta( \WC_Order $order ): void {
        $office_code = $order->get_meta( '_econt_office_code', true );

        // Nothing to do if no Econt office was chosen.
        if ( empty( $office_code ) ) {
            return;
        }

        $office_name    = $order->get_meta( '_econt_office_name', true );
        $office_address = $order->get_meta( '_econt_office_address', true );
        $office_city    = $order->get_meta( '_econt_office_city', true );

        // ── Ensure Bulgarisation-compatible meta keys are present ─────
        // Some versions of Bulgarisation may store shipping info under
        // a different key structure. We write both formats to be safe.
        $order->update_meta_data( '_shipping_econt_office_code', $office_code );
        if ( $office_name ) {
            $order->update_meta_data( '_shipping_econt_office_name', $office_name );
        }
        if ( $office_address ) {
            $order->update_meta_data( '_shipping_econt_office_address', $office_address );
        }
        if ( $office_city ) {
            $order->update_meta_data( '_shipping_econt_office_city', $office_city );
        }

        $order->save();

        // ── Add an admin-visible order note (once) ───────────────────
        $note_added = $order->get_meta( '_econt_bridge_note_added', true );
        if ( ! $note_added ) {
            $order->add_order_note(
                sprintf(
                    /* translators: 1: office name, 2: office address, 3: office city */
                    __( '[Econt] Избран офис: %1$s — %2$s, %3$s', 'econt-graphql-bridge' ),
                    $office_name ?: $office_code,
                    $office_address ?: '—',
                    $office_city ?: '—'
                )
            );
            $order->update_meta_data( '_econt_bridge_note_added', '1' );
            $order->save();
        }
    }
}
