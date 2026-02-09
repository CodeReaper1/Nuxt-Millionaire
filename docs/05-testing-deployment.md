# Testing & Deployment Guide

## What This Document Covers

How to test your Econt integration thoroughly and deploy it to production safely.

## Testing Checklist

### Phase 1: Backend Testing (WordPress)

#### 1. Plugin Activation
```bash
✓ Activate plugin without errors
✓ Check WordPress admin for error notices
✓ Verify settings page appears
✓ Test deactivation/reactivation
```

**How to test:**
1. Go to Plugins → Installed Plugins
2. Activate "Econt Shipping for WooCommerce"
3. Check for any PHP errors
4. Go to WooCommerce → Settings → Shipping
5. Verify "Econt" tab appears

#### 2. API Connection Test
```bash
✓ Test API credentials
✓ Fetch offices successfully
✓ Fetch cities successfully
✓ Handle API errors gracefully
```

**Create test page:** `wp-content/plugins/econt-shipping/test-api.php`

```php
<?php
// ONLY FOR TESTING - DELETE BEFORE PRODUCTION
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

$api = new Econt_API();

echo "<h1>Econt API Test</h1>";

// Test 1: Get offices
echo "<h2>Test 1: Get Offices (Sofia)</h2>";
$offices = $api->get_offices('София');
echo "<pre>";
print_r($offices);
echo "</pre>";

// Test 2: Get cities
echo "<h2>Test 2: Get Cities</h2>";
$cities = $api->get_cities();
echo "<pre>";
print_r(array_slice($cities, 0, 10)); // Show first 10
echo "</pre>";

// Test 3: Calculate shipping
echo "<h2>Test 3: Calculate Shipping</h2>";
$rate = $api->calculate_shipping([
    'weight' => 2,
    'sender_city' => 'София',
    'receiver_city' => 'Пловдив',
    'delivery_type' => 'OFFICE_OFFICE',
    'cod_amount' => 0
]);
echo "<pre>";
print_r($rate);
echo "</pre>";
?>
```

Access: `https://yoursite.com/wp-content/plugins/econt-shipping/test-api.php`

#### 3. GraphQL Testing

**Using GraphiQL IDE:**

Go to: `https://yoursite.com/graphql`

**Test Query 1: Get Offices**
```graphql
query TestOffices {
  econOffices(city: "София") {
    id
    name
    address
  }
}
```
Expected: List of Sofia offices

**Test Query 2: Get Cities**
```graphql
query TestCities {
  econCities(search: "Со") {
    id
    name
  }
}
```
Expected: Cities starting with "Со"

**Test Query 3: Calculate Shipping**
```graphql
query TestCalculate {
  econCalculateShipping(
    weight: 2.0
    receiverCity: "Пловдив"
  )
}
```
Expected: A number (price in BGN)

**Test Mutation: Select Office**
```graphql
mutation TestSelect {
  selectEcontOffice(input: {
    orderId: "123"  # Use real order ID
    officeId: "1234"
    officeName: "Test Office"
    officeAddress: "Test Address"
    officeCity: "София"
  }) {
    success
    message
  }
}
```
Expected: `{ success: true, message: "..." }`

### Phase 2: Frontend Testing (Nuxt)

#### 1. Component Testing

**Test Office Selector in isolation:**

Create test page: `pages/test-econt.vue`

```vue
<template>
  <div class="p-8">
    <h1 class="text-2xl mb-4">Econt Office Selector Test</h1>
    
    <EcontOfficeSelector
      v-model="selectedOffice"
      @office-selected="onOfficeSelected"
    />
    
    <div v-if="selectedOffice" class="mt-4 p-4 bg-gray-100 rounded">
      <h3>Selected Office (Debug):</h3>
      <pre>{{ JSON.stringify(selectedOffice, null, 2) }}</pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const selectedOffice = ref(null)

const onOfficeSelected = (office) => {
  console.log('Office selected:', office)
  alert(`Selected: ${office.name}`)
}
</script>
```

**Test checklist:**
```bash
✓ Cities load in dropdown
✓ Selecting city loads offices
✓ Search filters offices
✓ Clicking office selects it
✓ Selected office shows in summary
✓ "Change" button clears selection
✓ Loading states show correctly
✓ Errors display properly
```

#### 2. Integration Testing

**Test full checkout flow:**

1. Add product to cart
2. Go to checkout
3. Fill shipping details
4. Select "Econt" shipping method
5. Verify office selector appears
6. Select city
7. Select office
8. Verify office shows as selected
9. Complete order
10. Check order in WordPress admin
11. Verify office saved in order meta

**Where to check in WordPress:**
- Orders → [Your Order] → Order Meta (bottom of page)
- Look for: `_econt_office`, `_econt_office_id`

#### 3. End-to-End Testing Scenarios

**Scenario 1: Happy Path**
```
1. Customer adds product
2. Selects Econt shipping
3. Picks Sofia office
4. Completes order with COD payment
5. Admin generates label
6. Customer tracks shipment
```

**Scenario 2: Error Handling**
```
1. Network failure during office fetch
2. Invalid city selected
3. API timeout
4. Missing office selection
5. Order ID not found
```

**Scenario 3: Edge Cases**
```
1. Very long office names
2. Special characters in address
3. Switching between shipping methods
4. Changing office selection
5. Browser back button
```

### Phase 3: Performance Testing

#### 1. Load Time Testing

```bash
✓ Office list loads in < 2 seconds
✓ Search responds in < 500ms
✓ GraphQL queries cached properly
✓ No unnecessary API calls
✓ Images/assets optimized
```

**Tools:**
- Chrome DevTools Network tab
- Lighthouse performance audit
- Vue DevTools for component renders

#### 2. Cache Testing

**Verify caching works:**

```php
// In WordPress, check transients
function econt_test_cache() {
    // Should be cached
    $offices = get_transient('econt_offices_София');
    var_dump($offices !== false); // Should be true after first fetch
    
    // Force fresh fetch
    delete_transient('econt_offices_София');
    $api = new Econt_API();
    $offices = $api->get_offices('София'); // Fetches from API
    
    // Now should be cached again
    $offices = get_transient('econt_offices_София');
    var_dump($offices !== false); // Should be true
}
```

#### 3. Database Performance

Check order meta queries:

```sql
-- Should have indexes on meta_key
EXPLAIN SELECT * FROM wp_postmeta 
WHERE meta_key = '_econt_office_id' 
AND post_id = 123;
```

### Phase 4: Browser Testing

Test in multiple browsers:

```bash
✓ Chrome (latest)
✓ Firefox (latest)
✓ Safari (latest)
✓ Edge (latest)
✓ Mobile Chrome
✓ Mobile Safari
```

**Responsive design checklist:**
- Office selector on mobile (320px width)
- Office selector on tablet (768px width)
- Office selector on desktop (1920px width)
- Touch-friendly click targets (min 44px)
- Readable text sizes

### Phase 5: Security Testing

```bash
✓ GraphQL authentication works
✓ Users can't modify other's orders
✓ SQL injection prevention
✓ XSS prevention in office names/addresses
✓ CSRF protection on mutations
✓ API credentials not exposed to frontend
```

**Test unauthorized access:**

```graphql
# Try to select office for someone else's order
mutation UnauthorizedTest {
  selectEcontOffice(input: {
    orderId: "999"  # Someone else's order
    officeId: "123"
  }) {
    success
  }
}
```
Expected: Error or success: false

## Deployment Guide

### Pre-Deployment Checklist

```bash
□ All tests passing
□ No console errors
□ No PHP warnings
□ Test with real Econt account
□ Backup database
□ Backup files
□ Document all settings
□ Create rollback plan
```

### Step 1: Prepare Production Environment

**WordPress/WooCommerce:**

1. Update to latest stable versions
2. Ensure WPGraphQL is installed
3. Ensure WooGraphQL is installed
4. PHP 7.4+ required
5. MySQL 5.7+ required

**Econt Account:**

1. Register production account: https://login.econt.com/register/
2. Get API credentials
3. Configure company details
4. Set up billing

### Step 2: Deploy Backend (WordPress Plugin)

**Via FTP/SFTP:**

```bash
# 1. Upload plugin folder
/wp-content/plugins/econt-shipping/

# 2. Set correct permissions
chmod -R 755 /wp-content/plugins/econt-shipping/
chmod 644 /wp-content/plugins/econt-shipping/*.php
```

**Via WP-CLI:**

```bash
# If you have WP-CLI installed
wp plugin install /path/to/econt-shipping.zip
wp plugin activate econt-shipping
```

**Activation steps:**

1. Go to Plugins → Installed Plugins
2. Activate "Econt Shipping for WooCommerce"
3. Go to WooCommerce → Settings → Shipping → Econt
4. Enter production API credentials:
   - Test Mode: OFF
   - Username: [your production username]
   - Password: [your production password]
   - Sender City: [your city]
   - Sender Address: [your address]
   - Sender Phone: [your phone]
5. Save settings

### Step 3: Deploy Frontend (Nuxt)

**Build for production:**

```bash
# In your Nuxt project
npm run build

# Or with yarn
yarn build
```

**Deploy to hosting:**

```bash
# Example: Deploy to Vercel
vercel --prod

# Example: Deploy to Netlify
netlify deploy --prod

# Example: Manual deployment
rsync -avz .output/ user@server:/var/www/yoursite/
```

**Environment variables:**

Create `.env.production`:

```env
GRAPHQL_ENDPOINT=https://yoursite.com/graphql
NUXT_PUBLIC_SITE_URL=https://yoursite.com
```

### Step 4: Configure Shipping Zones

**In WordPress Admin:**

1. Go to WooCommerce → Settings → Shipping
2. Click on your shipping zone (e.g., "Bulgaria")
3. Add shipping method → Select "Econt"
4. Configure:
   - Method Title: "Доставка до офис на Еконт"
   - Delivery Type: "Office to Office"
   - Fixed Rate: (leave empty for API rates)
5. Save

### Step 5: Post-Deployment Testing

**Smoke tests (do these IMMEDIATELY after deploy):**

1. Place test order with Econt shipping
2. Select office
3. Complete checkout
4. Check order in admin
5. Generate shipping label
6. Verify tracking works
7. Test on mobile device

**Monitor for 24 hours:**

```bash
□ Check error logs
□ Monitor API responses
□ Watch for customer issues
□ Check order completion rate
□ Verify label generation
□ Test email notifications
```

### Step 6: Monitoring Setup

**WordPress error logging:**

Add to `wp-config.php`:

```php
define('WP_DEBUG', false); // Must be false in production
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs: `wp-content/debug.log`

**Econt API monitoring:**

Create custom logging:

```php
// In class-econt-api.php
private function log_api_call($endpoint, $data, $response, $error = null) {
    if (defined('ECONT_DEBUG') && ECONT_DEBUG) {
        error_log(sprintf(
            'Econt API: %s | Data: %s | Response: %s | Error: %s',
            $endpoint,
            json_encode($data),
            json_encode($response),
            $error
        ));
    }
}
```

Enable only when debugging:
```php
// wp-config.php
define('ECONT_DEBUG', false); // true only for debugging
```

### Rollback Plan

**If something goes wrong:**

1. **Deactivate plugin:**
   - Plugins → Econt Shipping → Deactivate
   - Customers can still checkout with other methods

2. **Restore from backup:**
   ```bash
   # Restore database
   mysql -u user -p database < backup.sql
   
   # Restore files
   cp -r backup/wp-content/plugins/econt-shipping/ wp-content/plugins/
   ```

3. **Switch to manual processing:**
   - Temporarily use fixed shipping rates
   - Process Econt orders manually via their website

## Common Issues & Solutions

### Issue 1: "No offices found"

**Possible causes:**
- API credentials wrong
- Test mode enabled with wrong endpoint
- Cache not cleared
- Network/firewall blocking API

**Solution:**
```php
// Clear cache
delete_transient('econt_offices');
delete_transient('econt_cities');

// Test API directly
$api = new Econt_API();
$result = $api->get_offices();
var_dump($result); // Should show array of offices
```

### Issue 2: Office selector doesn't appear

**Possible causes:**
- Shipping method not selected
- GraphQL query failing
- Component not imported
- JavaScript error

**Solution:**
```javascript
// Check console for errors
// Verify shipping method ID includes 'econt'
console.log('Selected method:', selectedShippingMethod.value)

// Test GraphQL query directly
const { data } = await $apollo.query({
  query: gql`query { econOffices { id name } }`
})
console.log('Offices:', data.econOffices)
```

### Issue 3: Label generation fails

**Possible causes:**
- Missing order data
- Invalid office ID
- API credentials expired
- Weight not set on products

**Solution:**
```php
// Check order data
$order = wc_get_order($order_id);
$office_id = get_post_meta($order_id, '_econt_office_id', true);
$weight = calculate_order_weight($order);

// Verify all required fields
var_dump([
    'office_id' => $office_id,
    'weight' => $weight,
    'sender_city' => get_option('econt_sender_city'),
    'receiver_city' => $order->get_shipping_city(),
]);
```

### Issue 4: High API call volume

**Possible causes:**
- No caching
- Cache expiring too fast
- Office selector fetching on every render

**Solution:**
```php
// Increase cache time
set_transient('econt_offices', $offices, 24 * HOUR_IN_SECONDS);

// Check cache hit rate
$cache_hit = get_transient('econt_offices') !== false;
error_log('Cache hit: ' . ($cache_hit ? 'yes' : 'no'));
```

---

## 🧠 ADHD-FRIENDLY SUMMARY

**What's this about?**
Making sure everything works before you go live, and fixing it if something breaks.

**Testing in 3 steps:**
1. **Backend** - Does WordPress talk to Econt? (Test API calls)
2. **Frontend** - Does the office selector work? (Test in browser)
3. **Together** - Can customers complete an order? (Test full flow)

**Deployment = 3 uploads:**
1. Upload WordPress plugin
2. Upload Nuxt build
3. Turn on production API credentials

**Critical things to test:**
- ✅ Add to cart → Checkout → Pick office → Complete order → See tracking
- ✅ Try on your phone
- ✅ Try with slow internet
- ✅ Try selecting different cities

**If something breaks:**
Just deactivate the plugin. Customers can still checkout with other shipping methods.

**Pro tip:**
Test with real money (small order to yourself). That's the only way to know it really works.

**The rollback:**
Deactivate plugin → Everything back to normal. Keep a database backup just in case.

**After going live:**
Watch for 24 hours. Check `wp-content/debug.log` for errors. Fix anything weird immediately.
