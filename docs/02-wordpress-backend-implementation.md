# WordPress Backend Implementation - Econt Shipping Plugin

## What This Document Covers

This guide shows you how to build the WordPress/WooCommerce side of your Econt integration. This is where all the heavy lifting happens.

## Plugin Structure

Create this folder structure:

```
wp-content/plugins/econt-shipping/
├── econt-shipping.php              # Main plugin file
├── includes/
│   ├── class-econt-api.php         # Econt API wrapper
│   ├── class-econt-shipping-method.php  # WooCommerce shipping method
│   ├── class-econt-graphql.php     # GraphQL extensions
│   └── class-econt-admin.php       # Admin settings page
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
└── readme.txt
```

## Step 1: Main Plugin File

**File: `econt-shipping.php`**

```php
<?php
/**
 * Plugin Name: Econt Shipping for WooCommerce
 * Plugin URI: https://yoursite.com
 * Description: Integrates Econt shipping with WooCommerce and exposes data via GraphQL
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: econt-shipping
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ECONT_SHIPPING_VERSION', '1.0.0');
define('ECONT_SHIPPING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ECONT_SHIPPING_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class Econt_Shipping {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        // Load required files
        require_once ECONT_SHIPPING_PLUGIN_DIR . 'includes/class-econt-api.php';
        require_once ECONT_SHIPPING_PLUGIN_DIR . 'includes/class-econt-shipping-method.php';
        require_once ECONT_SHIPPING_PLUGIN_DIR . 'includes/class-econt-admin.php';
        
        // Load GraphQL extensions only if WPGraphQL is active
        if (class_exists('WPGraphQL')) {
            require_once ECONT_SHIPPING_PLUGIN_DIR . 'includes/class-econt-graphql.php';
        }
    }
    
    private function init_hooks() {
        // Initialize shipping method
        add_action('woocommerce_shipping_init', array($this, 'init_shipping_method'));
        add_filter('woocommerce_shipping_methods', array($this, 'add_shipping_method'));
        
        // Add settings link
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
        
        // Initialize admin
        if (is_admin()) {
            Econt_Admin::instance();
        }
        
        // Initialize GraphQL
        if (class_exists('WPGraphQL')) {
            Econt_GraphQL::instance();
        }
    }
    
    public function init_shipping_method() {
        // Shipping method is already loaded in includes
    }
    
    public function add_shipping_method($methods) {
        $methods['econt'] = 'WC_Econt_Shipping_Method';
        return $methods;
    }
    
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=econt') . '">' . __('Settings', 'econt-shipping') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize plugin
function econt_shipping() {
    return Econt_Shipping::instance();
}

// Kickoff
econt_shipping();
```

## Step 2: Econt API Wrapper

**File: `includes/class-econt-api.php`**

```php
<?php
/**
 * Econt API Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Econt_API {
    
    private $username;
    private $password;
    private $api_url;
    private $is_test_mode;
    
    public function __construct() {
        $this->is_test_mode = get_option('econt_test_mode', 'yes') === 'yes';
        
        if ($this->is_test_mode) {
            $this->api_url = 'https://demo.econt.com/ee/services';
            $this->username = get_option('econt_test_username', 'iasp-dev');
            $this->password = get_option('econt_test_password', 'iasp-dev');
        } else {
            $this->api_url = 'https://ee.econt.com/services';
            $this->username = get_option('econt_username', '');
            $this->password = get_option('econt_password', '');
        }
    }
    
    /**
     * Make API request
     */
    private function request($endpoint, $method = 'POST', $data = array()) {
        $url = $this->api_url . '/' . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
            ),
        );
        
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Econt API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Get all offices
     */
    public function get_offices($city = null) {
        // Check cache first
        $cache_key = 'econt_offices' . ($city ? '_' . sanitize_title($city) : '');
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Fetch from API
        $data = array(
            'countryCode' => 'BGR'
        );
        
        if ($city) {
            $data['cityName'] = $city;
        }
        
        $response = $this->request('Nomenclatures/NomenclaturesService.getOffices.json', 'POST', $data);
        
        if ($response && isset($response['offices'])) {
            $offices = $response['offices'];
            
            // Cache for 6 hours
            set_transient($cache_key, $offices, 6 * HOUR_IN_SECONDS);
            
            return $offices;
        }
        
        return array();
    }
    
    /**
     * Get cities
     */
    public function get_cities() {
        $cache_key = 'econt_cities';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $data = array(
            'countryCode' => 'BGR'
        );
        
        $response = $this->request('Nomenclatures/NomenclaturesService.getCities.json', 'POST', $data);
        
        if ($response && isset($response['cities'])) {
            $cities = $response['cities'];
            set_transient($cache_key, $cities, 24 * HOUR_IN_SECONDS);
            return $cities;
        }
        
        return array();
    }
    
    /**
     * Calculate shipping rate
     */
    public function calculate_shipping($params) {
        $data = array(
            'shipmentType' => isset($params['type']) ? $params['type'] : 'PACK',
            'weight' => $params['weight'],
            'senderCity' => $params['sender_city'],
            'receiverCity' => $params['receiver_city'],
            'tariffSubCode' => isset($params['delivery_type']) ? $params['delivery_type'] : 'OFFICE_OFFICE',
        );
        
        // Add COD if applicable
        if (isset($params['cod_amount']) && $params['cod_amount'] > 0) {
            $data['cdAmount'] = $params['cod_amount'];
            $data['cdCurrency'] = 'BGN';
        }
        
        $response = $this->request('Calculating/CalculatingService.calculatePrice.json', 'POST', $data);
        
        if ($response && isset($response['price'])) {
            return array(
                'price' => $response['price']['total'],
                'currency' => $response['price']['currency'],
                'delivery_days' => isset($response['deliveryDays']) ? $response['deliveryDays'] : null
            );
        }
        
        return false;
    }
    
    /**
     * Create shipping label
     */
    public function create_label($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        // Get sender info from settings
        $sender_data = array(
            'name' => get_option('econt_sender_name', ''),
            'city' => get_option('econt_sender_city', ''),
            'address' => get_option('econt_sender_address', ''),
            'phone' => get_option('econt_sender_phone', ''),
        );
        
        // Get receiver info from order
        $receiver_data = array(
            'name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'city' => $order->get_shipping_city(),
            'address' => $order->get_shipping_address_1(),
            'phone' => $order->get_billing_phone(),
        );
        
        // Get selected office
        $office_id = get_post_meta($order_id, '_econt_office_id', true);
        
        $data = array(
            'sender' => $sender_data,
            'receiver' => $receiver_data,
            'shipmentType' => 'PACK',
            'weight' => $this->calculate_order_weight($order),
            'tariffSubCode' => 'OFFICE_OFFICE',
            'instructions' => $order->get_customer_note(),
        );
        
        if ($office_id) {
            $data['receiverOfficeCode'] = $office_id;
        }
        
        // Add COD if payment is COD
        if ($order->get_payment_method() === 'cod') {
            $data['cdAmount'] = $order->get_total();
            $data['cdCurrency'] = 'BGN';
            $data['cdPaymentReceiver'] = 'SENDER';
        }
        
        $response = $this->request('Shipments/LabelService.createLabel.json', 'POST', $data);
        
        if ($response && isset($response['label'])) {
            // Save tracking info
            update_post_meta($order_id, '_econt_tracking_number', $response['label']['shipmentNumber']);
            update_post_meta($order_id, '_econt_label_url', $response['label']['pdfURL']);
            
            // Add order note
            $order->add_order_note(
                sprintf(__('Econt label created. Tracking number: %s', 'econt-shipping'), $response['label']['shipmentNumber'])
            );
            
            return $response['label'];
        }
        
        return false;
    }
    
    /**
     * Track shipment
     */
    public function track_shipment($tracking_number) {
        $data = array(
            'shipmentNumber' => $tracking_number
        );
        
        $response = $this->request('Shipments/ShipmentService.trackShipment.json', 'POST', $data);
        
        if ($response && isset($response['shipment'])) {
            return $response['shipment'];
        }
        
        return false;
    }
    
    /**
     * Calculate total weight of order
     */
    private function calculate_order_weight($order) {
        $weight = 0;
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_weight()) {
                $weight += (float) $product->get_weight() * $item->get_quantity();
            }
        }
        
        // Default to 1kg if no weight set
        return $weight > 0 ? $weight : 1;
    }
}
```

## Step 3: WooCommerce Shipping Method

**File: `includes/class-econt-shipping-method.php`**

```php
<?php
/**
 * WooCommerce Shipping Method for Econt
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Econt_Shipping_Method extends WC_Shipping_Method {
    
    private $api;
    
    public function __construct($instance_id = 0) {
        $this->id = 'econt';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Econt Shipping', 'econt-shipping');
        $this->method_description = __('Ship via Econt courier service', 'econt-shipping');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        
        $this->init();
        
        // Initialize API
        $this->api = new Econt_API();
    }
    
    private function init() {
        // Load settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        
        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }
    
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Method Title', 'econt-shipping'),
                'type' => 'text',
                'description' => __('This controls the title shown during checkout', 'econt-shipping'),
                'default' => __('Econt to Office', 'econt-shipping'),
                'desc_tip' => true
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'econt-shipping'),
                'type' => 'checkbox',
                'label' => __('Enable this shipping method', 'econt-shipping'),
                'default' => 'yes'
            ),
            'delivery_type' => array(
                'title' => __('Delivery Type', 'econt-shipping'),
                'type' => 'select',
                'default' => 'OFFICE_OFFICE',
                'options' => array(
                    'OFFICE_OFFICE' => __('Office to Office', 'econt-shipping'),
                    'OFFICE_DOOR' => __('Office to Door', 'econt-shipping'),
                    'DOOR_OFFICE' => __('Door to Office', 'econt-shipping'),
                    'DOOR_DOOR' => __('Door to Door', 'econt-shipping'),
                )
            ),
            'fixed_rate' => array(
                'title' => __('Fixed Rate', 'econt-shipping'),
                'type' => 'price',
                'description' => __('Leave empty to use API calculated rates', 'econt-shipping'),
                'default' => '',
                'desc_tip' => true
            ),
        );
    }
    
    public function calculate_shipping($package = array()) {
        $fixed_rate = $this->get_option('fixed_rate');
        
        if (!empty($fixed_rate)) {
            // Use fixed rate
            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $fixed_rate,
                'package' => $package
            );
        } else {
            // Calculate via API
            $sender_city = get_option('econt_sender_city', 'Sofia');
            $receiver_city = isset($package['destination']['city']) ? $package['destination']['city'] : '';
            
            // Calculate total weight
            $weight = 0;
            foreach ($package['contents'] as $item) {
                if ($item['data']->get_weight()) {
                    $weight += (float) $item['data']->get_weight() * $item['quantity'];
                }
            }
            $weight = $weight > 0 ? $weight : 1;
            
            // Get COD amount if payment method is COD
            $cod_amount = 0;
            if (WC()->session && WC()->session->get('chosen_payment_method') === 'cod') {
                $cod_amount = $package['contents_cost'];
            }
            
            $shipping_params = array(
                'weight' => $weight,
                'sender_city' => $sender_city,
                'receiver_city' => $receiver_city,
                'delivery_type' => $this->get_option('delivery_type'),
                'cod_amount' => $cod_amount
            );
            
            $result = $this->api->calculate_shipping($shipping_params);
            
            if ($result && isset($result['price'])) {
                $label = $this->title;
                if (isset($result['delivery_days'])) {
                    $label .= sprintf(' (%d %s)', $result['delivery_days'], __('business days', 'econt-shipping'));
                }
                
                $rate = array(
                    'id' => $this->get_rate_id(),
                    'label' => $label,
                    'cost' => $result['price'],
                    'package' => $package
                );
            } else {
                // Fallback to a default rate if API fails
                $rate = array(
                    'id' => $this->get_rate_id(),
                    'label' => $this->title . ' ' . __('(price on request)', 'econt-shipping'),
                    'cost' => 0,
                    'package' => $package
                );
            }
        }
        
        $this->add_rate($rate);
    }
}
```

---

## 🧠 ADHD-FRIENDLY SUMMARY

**What did we just build?**
The WordPress "engine" that makes Econt work.

**Three main parts:**
1. **Main plugin file** = The "on/off switch" - loads everything
2. **API wrapper** = The "translator" - talks to Econt in their language
3. **Shipping method** = The "price calculator" - shows costs at checkout

**How it works:**
- Customer adds product to cart
- Goes to checkout
- WooCommerce asks: "How much to ship this?"
- Your plugin asks Econt API: "How much to ship from Sofia to Plovdiv, 2kg?"
- Econt replies: "5.50 BGN"
- Customer sees: "Econt to Office - 5.50 BGN (2 business days)"

**Important files:**
- `econt-shipping.php` = Starts everything
- `class-econt-api.php` = Talks to Econt
- `class-econt-shipping-method.php` = Shows shipping option at checkout

**Next step:** Connect this to your Nuxt frontend using GraphQL (see next document)

**Pro tip:** Test everything with Econt's demo account first. The demo credentials are already in the code!
