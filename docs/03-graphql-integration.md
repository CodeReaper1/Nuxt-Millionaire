# GraphQL Integration - Connecting WordPress to Nuxt

## What This Document Covers

This is THE BRIDGE between your WordPress backend and Nuxt frontend. GraphQL lets your Nuxt app ask WordPress questions like "What are the Econt offices?" or "What's the tracking number for this order?"

## Prerequisites

Make sure these are installed in WordPress:
- ✅ WPGraphQL plugin
- ✅ WooGraphQL (WooCommerce extension for GraphQL)
- ✅ Your Econt plugin (from previous document)

## The GraphQL Extension File

**File: `includes/class-econt-graphql.php`**

```php
<?php
/**
 * GraphQL Extensions for Econt Shipping
 * This exposes Econt data to your Nuxt frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class Econt_GraphQL {
    
    private static $instance = null;
    private $api;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api = new Econt_API();
        
        // Register all GraphQL types and fields
        add_action('graphql_register_types', array($this, 'register_types'));
    }
    
    /**
     * Register all GraphQL types, fields, queries, and mutations
     */
    public function register_types() {
        // Register custom object types
        $this->register_office_type();
        $this->register_city_type();
        $this->register_tracking_type();
        
        // Register fields on existing types
        $this->register_order_fields();
        
        // Register root queries
        $this->register_queries();
        
        // Register mutations
        $this->register_mutations();
    }
    
    /**
     * Register Econt Office type
     */
    private function register_office_type() {
        register_graphql_object_type('EcontOffice', array(
            'description' => __('Econt office/pickup point', 'econt-shipping'),
            'fields' => array(
                'id' => array(
                    'type' => 'String',
                    'description' => __('Office ID', 'econt-shipping'),
                ),
                'code' => array(
                    'type' => 'String',
                    'description' => __('Office code', 'econt-shipping'),
                ),
                'name' => array(
                    'type' => 'String',
                    'description' => __('Office name', 'econt-shipping'),
                ),
                'address' => array(
                    'type' => 'String',
                    'description' => __('Full address', 'econt-shipping'),
                ),
                'city' => array(
                    'type' => 'String',
                    'description' => __('City name', 'econt-shipping'),
                ),
                'postCode' => array(
                    'type' => 'String',
                    'description' => __('Postal code', 'econt-shipping'),
                ),
                'latitude' => array(
                    'type' => 'Float',
                    'description' => __('Latitude coordinate', 'econt-shipping'),
                ),
                'longitude' => array(
                    'type' => 'Float',
                    'description' => __('Longitude coordinate', 'econt-shipping'),
                ),
                'workingTimeFrom' => array(
                    'type' => 'String',
                    'description' => __('Opening time', 'econt-shipping'),
                ),
                'workingTimeTo' => array(
                    'type' => 'String',
                    'description' => __('Closing time', 'econt-shipping'),
                ),
                'workingTimeHalfFrom' => array(
                    'type' => 'String',
                    'description' => __('Saturday opening time', 'econt-shipping'),
                ),
                'workingTimeHalfTo' => array(
                    'type' => 'String',
                    'description' => __('Saturday closing time', 'econt-shipping'),
                ),
            )
        ));
    }
    
    /**
     * Register Econt City type
     */
    private function register_city_type() {
        register_graphql_object_type('EcontCity', array(
            'description' => __('Econt supported city', 'econt-shipping'),
            'fields' => array(
                'id' => array(
                    'type' => 'String',
                    'description' => __('City ID', 'econt-shipping'),
                ),
                'name' => array(
                    'type' => 'String',
                    'description' => __('City name', 'econt-shipping'),
                ),
                'postCode' => array(
                    'type' => 'String',
                    'description' => __('Postal code', 'econt-shipping'),
                ),
                'regionName' => array(
                    'type' => 'String',
                    'description' => __('Region/Oblast name', 'econt-shipping'),
                ),
            )
        ));
    }
    
    /**
     * Register Tracking Info type
     */
    private function register_tracking_type() {
        register_graphql_object_type('EcontTracking', array(
            'description' => __('Econt shipment tracking information', 'econt-shipping'),
            'fields' => array(
                'trackingNumber' => array(
                    'type' => 'String',
                    'description' => __('Tracking/shipment number', 'econt-shipping'),
                ),
                'status' => array(
                    'type' => 'String',
                    'description' => __('Current status', 'econt-shipping'),
                ),
                'statusDescription' => array(
                    'type' => 'String',
                    'description' => __('Status description', 'econt-shipping'),
                ),
                'location' => array(
                    'type' => 'String',
                    'description' => __('Current location', 'econt-shipping'),
                ),
                'estimatedDelivery' => array(
                    'type' => 'String',
                    'description' => __('Estimated delivery date', 'econt-shipping'),
                ),
                'labelUrl' => array(
                    'type' => 'String',
                    'description' => __('PDF label URL', 'econt-shipping'),
                ),
            )
        ));
    }
    
    /**
     * Add Econt fields to Order type
     */
    private function register_order_fields() {
        // Add tracking number field
        register_graphql_field('Order', 'econTrackingNumber', array(
            'type' => 'String',
            'description' => __('Econt tracking/shipment number', 'econt-shipping'),
            'resolve' => function($order) {
                return get_post_meta($order->databaseId, '_econt_tracking_number', true);
            }
        ));
        
        // Add label URL field
        register_graphql_field('Order', 'econLabelUrl', array(
            'type' => 'String',
            'description' => __('Econt shipping label PDF URL', 'econt-shipping'),
            'resolve' => function($order) {
                return get_post_meta($order->databaseId, '_econt_label_url', true);
            }
        ));
        
        // Add selected office field
        register_graphql_field('Order', 'econOffice', array(
            'type' => 'EcontOffice',
            'description' => __('Selected Econt office for this order', 'econt-shipping'),
            'resolve' => function($order) {
                $office_data = get_post_meta($order->databaseId, '_econt_office', true);
                
                if (!$office_data || empty($office_data)) {
                    return null;
                }
                
                // If it's a JSON string, decode it
                if (is_string($office_data)) {
                    $office_data = json_decode($office_data, true);
                }
                
                return $office_data;
            }
        ));
        
        // Add full tracking info field
        register_graphql_field('Order', 'econTracking', array(
            'type' => 'EcontTracking',
            'description' => __('Full Econt tracking information', 'econt-shipping'),
            'resolve' => function($order) {
                $tracking_number = get_post_meta($order->databaseId, '_econt_tracking_number', true);
                
                if (!$tracking_number) {
                    return null;
                }
                
                // Get cached tracking info
                $cache_key = 'econt_tracking_' . $tracking_number;
                $tracking_info = get_transient($cache_key);
                
                if (false === $tracking_info) {
                    // Fetch from API
                    $api = new Econt_API();
                    $tracking_info = $api->track_shipment($tracking_number);
                    
                    if ($tracking_info) {
                        // Cache for 30 minutes
                        set_transient($cache_key, $tracking_info, 30 * MINUTE_IN_SECONDS);
                    }
                }
                
                if (!$tracking_info) {
                    return null;
                }
                
                // Format for GraphQL response
                return array(
                    'trackingNumber' => $tracking_number,
                    'status' => $tracking_info['status'] ?? '',
                    'statusDescription' => $tracking_info['statusDescription'] ?? '',
                    'location' => $tracking_info['currentLocation'] ?? '',
                    'estimatedDelivery' => $tracking_info['estimatedDeliveryDate'] ?? '',
                    'labelUrl' => get_post_meta($order->databaseId, '_econt_label_url', true),
                );
            }
        ));
    }
    
    /**
     * Register root queries
     */
    private function register_queries() {
        // Query: Get all offices
        register_graphql_field('RootQuery', 'econOffices', array(
            'type' => array('list_of' => 'EcontOffice'),
            'description' => __('Get Econt offices/pickup points', 'econt-shipping'),
            'args' => array(
                'city' => array(
                    'type' => 'String',
                    'description' => __('Filter by city name', 'econt-shipping'),
                ),
                'search' => array(
                    'type' => 'String',
                    'description' => __('Search by name or address', 'econt-shipping'),
                ),
            ),
            'resolve' => function($root, $args) {
                $city = isset($args['city']) ? $args['city'] : null;
                $search = isset($args['search']) ? strtolower($args['search']) : null;
                
                $offices = $this->api->get_offices($city);
                
                // Apply search filter if provided
                if ($search && !empty($offices)) {
                    $offices = array_filter($offices, function($office) use ($search) {
                        $searchable = strtolower($office['name'] . ' ' . $office['address']);
                        return strpos($searchable, $search) !== false;
                    });
                }
                
                return array_values($offices); // Re-index array
            }
        ));
        
        // Query: Get all cities
        register_graphql_field('RootQuery', 'econCities', array(
            'type' => array('list_of' => 'EcontCity'),
            'description' => __('Get all Econt supported cities', 'econt-shipping'),
            'args' => array(
                'search' => array(
                    'type' => 'String',
                    'description' => __('Search by city name', 'econt-shipping'),
                ),
            ),
            'resolve' => function($root, $args) {
                $search = isset($args['search']) ? strtolower($args['search']) : null;
                
                $cities = $this->api->get_cities();
                
                // Apply search filter if provided
                if ($search && !empty($cities)) {
                    $cities = array_filter($cities, function($city) use ($search) {
                        return strpos(strtolower($city['name']), $search) !== false;
                    });
                }
                
                return array_values($cities);
            }
        ));
        
        // Query: Calculate shipping price
        register_graphql_field('RootQuery', 'econCalculateShipping', array(
            'type' => 'Float',
            'description' => __('Calculate Econt shipping price', 'econt-shipping'),
            'args' => array(
                'weight' => array(
                    'type' => array('non_null' => 'Float'),
                    'description' => __('Package weight in kg', 'econt-shipping'),
                ),
                'senderCity' => array(
                    'type' => 'String',
                    'description' => __('Sender city', 'econt-shipping'),
                ),
                'receiverCity' => array(
                    'type' => array('non_null' => 'String'),
                    'description' => __('Receiver city', 'econt-shipping'),
                ),
                'codAmount' => array(
                    'type' => 'Float',
                    'description' => __('Cash on delivery amount', 'econt-shipping'),
                ),
            ),
            'resolve' => function($root, $args) {
                $params = array(
                    'weight' => $args['weight'],
                    'sender_city' => isset($args['senderCity']) ? $args['senderCity'] : get_option('econt_sender_city', 'Sofia'),
                    'receiver_city' => $args['receiverCity'],
                    'delivery_type' => 'OFFICE_OFFICE',
                    'cod_amount' => isset($args['codAmount']) ? $args['codAmount'] : 0,
                );
                
                $result = $this->api->calculate_shipping($params);
                
                if ($result && isset($result['price'])) {
                    return (float) $result['price'];
                }
                
                return null;
            }
        ));
    }
    
    /**
     * Register mutations
     */
    private function register_mutations() {
        // Mutation: Select office for order
        register_graphql_mutation('selectEcontOffice', array(
            'inputFields' => array(
                'orderId' => array(
                    'type' => array('non_null' => 'ID'),
                    'description' => __('Order ID', 'econt-shipping'),
                ),
                'officeId' => array(
                    'type' => array('non_null' => 'String'),
                    'description' => __('Econt office ID/code', 'econt-shipping'),
                ),
                'officeName' => array(
                    'type' => 'String',
                    'description' => __('Office name', 'econt-shipping'),
                ),
                'officeAddress' => array(
                    'type' => 'String',
                    'description' => __('Office address', 'econt-shipping'),
                ),
                'officeCity' => array(
                    'type' => 'String',
                    'description' => __('Office city', 'econt-shipping'),
                ),
            ),
            'outputFields' => array(
                'success' => array(
                    'type' => 'Boolean',
                    'description' => __('Whether the operation was successful', 'econt-shipping'),
                ),
                'message' => array(
                    'type' => 'String',
                    'description' => __('Result message', 'econt-shipping'),
                ),
                'order' => array(
                    'type' => 'Order',
                    'description' => __('The updated order', 'econt-shipping'),
                ),
            ),
            'mutateAndGetPayload' => function($input, $context) {
                $order_id = absint($input['orderId']);
                
                // Verify order exists
                $order = wc_get_order($order_id);
                if (!$order) {
                    return array(
                        'success' => false,
                        'message' => __('Order not found', 'econt-shipping'),
                        'order' => null,
                    );
                }
                
                // Check user permissions (optional - depends on your needs)
                // if (!current_user_can('edit_shop_order', $order_id)) {
                //     return array('success' => false, 'message' => 'Unauthorized');
                // }
                
                // Prepare office data
                $office_data = array(
                    'id' => $input['officeId'],
                    'code' => $input['officeId'],
                    'name' => isset($input['officeName']) ? $input['officeName'] : '',
                    'address' => isset($input['officeAddress']) ? $input['officeAddress'] : '',
                    'city' => isset($input['officeCity']) ? $input['officeCity'] : '',
                );
                
                // Save to order meta
                update_post_meta($order_id, '_econt_office', $office_data);
                update_post_meta($order_id, '_econt_office_id', $input['officeId']);
                
                // Add order note
                $order->add_order_note(
                    sprintf(
                        __('Econt office selected: %s - %s', 'econt-shipping'),
                        $office_data['name'],
                        $office_data['address']
                    )
                );
                
                return array(
                    'success' => true,
                    'message' => __('Office saved successfully', 'econt-shipping'),
                    'order' => $order,
                );
            }
        ));
        
        // Mutation: Create shipping label
        register_graphql_mutation('createEcontLabel', array(
            'inputFields' => array(
                'orderId' => array(
                    'type' => array('non_null' => 'ID'),
                    'description' => __('Order ID', 'econt-shipping'),
                ),
            ),
            'outputFields' => array(
                'success' => array(
                    'type' => 'Boolean',
                    'description' => __('Whether label was created', 'econt-shipping'),
                ),
                'trackingNumber' => array(
                    'type' => 'String',
                    'description' => __('Generated tracking number', 'econt-shipping'),
                ),
                'labelUrl' => array(
                    'type' => 'String',
                    'description' => __('PDF label URL', 'econt-shipping'),
                ),
                'message' => array(
                    'type' => 'String',
                    'description' => __('Result message', 'econt-shipping'),
                ),
            ),
            'mutateAndGetPayload' => function($input, $context) {
                $order_id = absint($input['orderId']);
                
                // Check permissions (admin only)
                if (!current_user_can('edit_shop_orders')) {
                    return array(
                        'success' => false,
                        'message' => __('Unauthorized', 'econt-shipping'),
                    );
                }
                
                $label = $this->api->create_label($order_id);
                
                if ($label) {
                    return array(
                        'success' => true,
                        'trackingNumber' => $label['shipmentNumber'],
                        'labelUrl' => $label['pdfURL'],
                        'message' => __('Label created successfully', 'econt-shipping'),
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => __('Failed to create label', 'econt-shipping'),
                    );
                }
            }
        ));
    }
}
```

## Testing Your GraphQL Endpoint

### Access GraphiQL IDE

1. Go to WordPress admin
2. Navigate to **GraphQL → GraphiQL IDE**
3. Or visit: `https://yoursite.com/graphql`

### Test Query Examples

**Example 1: Get all offices in Sofia**

```graphql
query GetSofiaOffices {
  econOffices(city: "София") {
    id
    code
    name
    address
    city
    workingTimeFrom
    workingTimeTo
    latitude
    longitude
  }
}
```

**Example 2: Search for offices**

```graphql
query SearchOffices {
  econOffices(search: "Младост") {
    id
    name
    address
    city
  }
}
```

**Example 3: Get all cities**

```graphql
query GetCities {
  econCities {
    id
    name
    postCode
    regionName
  }
}
```

**Example 4: Calculate shipping**

```graphql
query CalculateShipping {
  econCalculateShipping(
    weight: 2.5
    receiverCity: "Пловдив"
    senderCity: "София"
    codAmount: 0
  )
}
```

**Example 5: Get order with Econt data**

```graphql
query GetOrderWithEcont {
  order(id: "123", idType: DATABASE_ID) {
    id
    orderNumber
    status
    total
    econTrackingNumber
    econLabelUrl
    econOffice {
      name
      address
      city
    }
    econTracking {
      status
      statusDescription
      location
      estimatedDelivery
    }
  }
}
```

**Example 6: Select office (mutation)**

```graphql
mutation SelectOffice {
  selectEcontOffice(input: {
    orderId: "123"
    officeId: "1234"
    officeName: "Офис София - Младост 1"
    officeAddress: "бул. Александър Малинов 51"
    officeCity: "София"
  }) {
    success
    message
    order {
      id
      econOffice {
        name
        address
      }
    }
  }
}
```

## Common GraphQL Errors and Solutions

### Error: "Field not found on type"
**Cause:** Type not registered or typo in field name  
**Fix:** Check spelling, ensure `register_graphql_field` is called

### Error: "Cannot return null for non-nullable field"
**Cause:** Field marked as required but returning null  
**Fix:** Either provide a value or remove `'non_null'` wrapper

### Error: "Access denied"
**Cause:** User doesn't have permissions  
**Fix:** Add permission checks in resolve function or adjust WPGraphQL settings

### Error: "Invalid ID"
**Cause:** Wrong ID type or non-existent resource  
**Fix:** Check if using `DATABASE_ID` vs `ID` (global ID)

## Security Considerations

```php
// Example: Add authentication check
'resolve' => function($order, $args, $context) {
    // Check if user owns this order
    $current_user_id = get_current_user_id();
    $order_user_id = $order->get_customer_id();
    
    if ($current_user_id !== $order_user_id && !current_user_can('edit_shop_orders')) {
        throw new \GraphQL\Error\UserError('Unauthorized access');
    }
    
    return get_post_meta($order->databaseId, '_econt_tracking', true);
}
```

---

## 🧠 ADHD-FRIENDLY SUMMARY

**What is GraphQL?**
It's like a menu at a restaurant. Your Nuxt app (customer) looks at the menu and orders exactly what it wants. WordPress (kitchen) prepares it and sends it back.

**What did we build?**
We added Econt items to the menu! Now your Nuxt app can order:
- "Give me all Econt offices in Sofia"
- "What's the tracking number for order #123?"
- "Calculate shipping for 2kg to Plovdiv"

**Three main things:**
1. **Types** = The dishes on the menu (EcontOffice, EcontCity, EcontTracking)
2. **Queries** = Reading the menu / asking questions ("What offices exist?")
3. **Mutations** = Placing an order / making changes ("Save this office to my order")

**How to test:**
Go to `yoursite.com/graphql` and type queries like "show me offices". It's like texting WordPress and getting instant replies.

**Key insight:**
WordPress plugins save data in the database (like storing ingredients in the fridge). GraphQL is the waiter who knows where everything is and brings it to your Nuxt app when asked.

**Next step:** Use these queries in your Nuxt frontend (see next document)

**Brain-friendly tip:** Bookmark the GraphiQL page - it's your testing playground where you can experiment without breaking anything!
