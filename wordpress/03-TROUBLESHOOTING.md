# Econt GraphQL Bridge — Troubleshooting

Common issues and their solutions.

---

## Plugin does not appear in the Plugins list

**Cause:** The plugin folder is not in the right place.

**Fix:** Ensure the folder structure is exactly:

```
wp-content/plugins/econt-graphql-bridge/
├── econt-graphql-bridge.php
└── includes/
    ├── class-econt-api.php
    ├── class-econt-graphql.php
    └── class-econt-checkout-handler.php
```

The main PHP file **must** be directly inside `econt-graphql-bridge/`, not nested deeper.

---

## Red admin notice: "required plugins are not active"

**Cause:** One or more dependencies are missing.

**Fix:** Install and activate all of:
- WPGraphQL
- WooCommerce
- Bulgarisation for WooCommerce

The bridge plugin checks for these on every page load and shows the notice until they are all active.

---

## GraphQL query returns empty array for offices / cities

**Possible causes:**

1. **Econt API credentials are wrong.**
   - The bridge plugin uses its own credentials stored in `wp_options`.
   - Check with WP-CLI: `wp option get econt_gql_bridge_username` and `wp option get econt_gql_bridge_password`
   - Defaults (demo): username `iasp-dev`, password `1Asp-dev`.
   - If blank, set them: `wp option update econt_gql_bridge_username "iasp-dev"` and `wp option update econt_gql_bridge_password "1Asp-dev"`

2. **Transient cache is stale or corrupt.**
   - Clear transients:
     ```bash
     wp transient delete econt_bridge_offices
     wp transient delete econt_bridge_cities
     ```
   - Or delete all transients: `wp transient delete --all`
   - Or use the "Delete Transients" plugin from wordpress.org.

3. **Server cannot reach the Econt API.**
   - Check if `wp_remote_request` works (some hosts block outgoing HTTP).
   - Verify SSL: the Econt API requires HTTPS.
   - Check `wp-content/debug.log` for error messages starting with `Econt GraphQL Bridge API Error:`.

4. **Test mode / production mode mismatch.**
   - Check current mode: `wp option get econt_gql_bridge_test_mode` (default: `yes`)
   - If set to `no` but you have no production credentials, the API will reject requests.
   - Switch back to test mode: `wp option update econt_gql_bridge_test_mode yes`

---

## Shipping rates don't appear in the Nuxt checkout

This is **not** a problem with the Econt GraphQL Bridge plugin. Shipping rates flow through WooGraphQL's standard `availableShippingMethods` field on the `Cart` type.

**Fix:**

1. Ensure Econt is added as a shipping method in a WooCommerce shipping zone that covers Bulgaria.
2. Ensure the cart has a valid shipping address (country = BG, city filled in).
3. Test in GraphiQL:
   ```graphql
   query {
     cart {
       availableShippingMethods {
         rates {
           id
           label
           cost
         }
       }
     }
   }
   ```
4. If rates appear in GraphiQL but not in Nuxt, check the Nuxt `ShippingOptions.vue` component and `useCart` composable.

---

## Office selector does not appear in the Nuxt checkout

**Possible causes:**

1. **Econt shipping method is not selected.**
   - The selector only shows when `cart.chosenShippingMethods[0]` contains the string `econt`.
   - Click on the Econt shipping option first.

2. **The `EcontOfficeSelector` component is not loaded.**
   - Check the browser console for import errors.
   - Verify the component exists at `components/shopElements/EcontOfficeSelector.vue`.

3. **GraphQL query fails silently.**
   - Open browser DevTools > Network tab and look for the `econtCities` query.
   - Check if the response contains errors.

---

## Office data is not saved on the order

**Possible causes:**

1. **metaData not included in checkout payload.**
   - Open DevTools > Network tab and inspect the `Checkout` mutation payload.
   - Look for `_econt_office_code` in the `metaData` array.

2. **WooGraphQL strips unknown meta keys.**
   - WooGraphQL should pass through any `metaData` entries to the order. If it doesn't, the `saveEcontOfficeToOrder` mutation is the fallback.

3. **Checkout handler not firing.**
   - Enable `WP_DEBUG_LOG` in `wp-config.php` and check `wp-content/debug.log`.
   - Add a temporary log line in `class-econt-checkout-handler.php` to confirm the hook fires.

---

## Order note not appearing

**Cause:** The note is only added once (controlled by `_econt_bridge_note_added` meta).

**Fix:** If you need to re-trigger it, delete the `_econt_bridge_note_added` meta from the order and re-save.

---

## Performance: too many API calls

**Symptoms:** Slow checkout, high Econt API usage.

**Fix:**

1. Offices are cached for **6 hours**, cities for **24 hours** via WP transients.
2. If you need longer cache times, edit the `set_transient()` calls in `class-econt-api.php`.
3. The Nuxt front-end should debounce search inputs (already implemented in the `EcontOfficeSelector` component with a 300ms delay).

---

## Enabling debug logging

Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Then check `wp-content/debug.log` for lines starting with `Econt GraphQL Bridge`.

**Important:** Set `WP_DEBUG` back to `false` in production. Keep `WP_DEBUG_LOG` as `true` if you want to continue logging without showing errors to visitors.

---

## Clearing all Econt caches

```bash
# Via WP-CLI
wp transient delete econt_bridge_offices
wp transient delete econt_bridge_cities
wp transient delete --all  # nuclear option

# Via PHP (e.g. in a test script or WP-CLI eval)
delete_transient( 'econt_bridge_offices' );
delete_transient( 'econt_bridge_cities' );
```

Or install the "Transient Manager" plugin and delete transients with the `econt_bridge_` prefix from the admin UI.
