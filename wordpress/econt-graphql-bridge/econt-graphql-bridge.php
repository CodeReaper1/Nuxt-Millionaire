<?php
/**
 * Plugin Name: Econt GraphQL Bridge
 * Plugin URI: https://github.com/your-repo/econt-graphql-bridge
 * Description: Bridges Bulgarisation for WooCommerce Econt data into WPGraphQL for headless (WooNuxt) storefronts.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: econt-graphql-bridge
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ECONT_GQL_BRIDGE_VERSION', '1.0.0' );
define( 'ECONT_GQL_BRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECONT_GQL_BRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class — singleton.
 */
final class Econt_GraphQL_Bridge {

    /** @var self|null */
    private static $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function instance(): self {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — hooks everything.
     */
    private function __construct() {
        // Wait until all plugins are loaded before checking dependencies.
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    /**
     * Initialise the plugin after dependency checks.
     */
    public function init(): void {
        // ── Dependency checks ───────────────────────────────────────────
        if ( ! $this->check_dependencies() ) {
            return;
        }

        // ── Load includes ───────────────────────────────────────────────
        $this->includes();

        // ── Initialise components ───────────────────────────────────────
        Econt_Bridge_API::instance();
        Econt_Bridge_Checkout_Handler::instance();

        // GraphQL types are registered inside the graphql_register_types hook,
        // which fires only when WPGraphQL is active.
        if ( class_exists( 'WPGraphQL' ) ) {
            Econt_Bridge_GraphQL::instance();
        }
    }

    /**
     * Verify that required plugins are active.
     * Shows admin notices when a dependency is missing.
     */
    private function check_dependencies(): bool {
        $missing = [];

        // WPGraphQL
        if ( ! class_exists( 'WPGraphQL' ) ) {
            $missing[] = 'WPGraphQL';
        }

        // WooCommerce
        if ( ! class_exists( 'WooCommerce' ) ) {
            $missing[] = 'WooCommerce';
        }

        // Bulgarisation for WooCommerce — the main class shipped by the plugin.
        if ( ! $this->is_bulgarisation_active() ) {
            $missing[] = 'Bulgarisation for WooCommerce';
        }

        if ( ! empty( $missing ) ) {
            add_action( 'admin_notices', function () use ( $missing ) {
                $list = implode( ', ', $missing );
                printf(
                    '<div class="notice notice-error"><p><strong>Econt GraphQL Bridge:</strong> The following required plugins are not active: %s. Please install and activate them.</p></div>',
                    esc_html( $list )
                );
            } );
            return false;
        }

        return true;
    }

    /**
     * Detect whether "Bulgarisation for WooCommerce" (or a compatible variant) is active.
     */
    private function is_bulgarisation_active(): bool {
        // Check for the main class shipped by known Bulgarisation builds.
        if ( class_exists( 'Woo_BG' ) || class_exists( 'WooBG' ) || class_exists( 'Woo_Bg_Bulgaria' ) ) {
            return true;
        }

        // Fallback: look for any active plugin whose folder starts with "woo-bg" or "bulgarisation".
        if ( ! function_exists( 'get_option' ) ) {
            return false;
        }
        $active = (array) get_option( 'active_plugins', [] );
        foreach ( $active as $plugin ) {
            if ( preg_match( '#^(woo-bg|bulgarisation)#i', $plugin ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load required PHP files.
     */
    private function includes(): void {
        require_once ECONT_GQL_BRIDGE_PLUGIN_DIR . 'includes/class-econt-api.php';
        require_once ECONT_GQL_BRIDGE_PLUGIN_DIR . 'includes/class-econt-graphql.php';
        require_once ECONT_GQL_BRIDGE_PLUGIN_DIR . 'includes/class-econt-checkout-handler.php';
    }
}

/**
 * Returns the main plugin instance.
 */
function econt_gql_bridge(): Econt_GraphQL_Bridge {
    return Econt_GraphQL_Bridge::instance();
}

// Kick-off.
econt_gql_bridge();
