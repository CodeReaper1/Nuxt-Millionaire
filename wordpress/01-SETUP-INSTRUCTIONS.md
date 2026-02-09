# Econt GraphQL Bridge — Setup Instructions

This guide walks you through installing and configuring everything needed to make Econt shipping work with your headless WooNuxt store.

---

## Prerequisites

Make sure the following WordPress plugins are **already installed and active**:

| Plugin | Minimum version | Where to get it |
|--------|----------------|-----------------|
| WooCommerce | 6.0+ | wordpress.org |
| WPGraphQL | 2.0+ | wordpress.org |
| WooGraphQL (WPGraphQL WooCommerce) | 0.20+ | github.com/wp-graphql/wp-graphql-woocommerce |
| Bulgarisation for WooCommerce | 3.0+ | wordpress.org |

---

## Step 1 — Install and configure Bulgarisation for WooCommerce

1. In WordPress admin go to **Plugins > Add New**.
2. Search for **"Bulgarisation for WooCommerce"**.
3. Install and **Activate**.

### Configure Econt in Bulgarisation

1. Go to **WooCommerce > Settings > Bulgarisation** (or the tab Bulgarisation adds).
2. Find the **Econt** section and fill in your credentials as Bulgarisation requires.
3. Save changes.

Bulgarisation handles the **shipping rate calculations** in WooCommerce. When a customer enters a Bulgarian shipping address, Bulgarisation will show Econt rates at checkout automatically. This flows to your Nuxt front-end through WooGraphQL without any extra work.

### Enable Econt as a shipping method

1. Go to **WooCommerce > Settings > Shipping**.
2. Open or create a shipping zone for **Bulgaria**.
3. Click **Add shipping method** and select the Econt option that Bulgarisation provides (e.g. "Еконт до офис", "Еконт до адрес").
4. Configure the method options (delivery type, fixed rate or API rates, etc.).
5. Save.

At this point, if you visit a regular (non-headless) WooCommerce checkout, you should see Econt as a shipping option. The rates will also appear in the headless Nuxt front-end via the existing `cart.availableShippingMethods` GraphQL field.

---

## Step 2 — Install the Econt GraphQL Bridge plugin

This is the custom plugin that exposes Econt offices, cities, and order meta through GraphQL. Bulgarisation handles shipping rates, but it doesn't expose office data via GraphQL — that's what this plugin does.

### Option A — Upload via FTP / file manager

1. Copy the entire `econt-graphql-bridge/` folder from this repository's `wordpress/` directory.
2. Upload it to `wp-content/plugins/` on your WordPress server, so the structure is:
   ```
   wp-content/plugins/econt-graphql-bridge/
   ├── econt-graphql-bridge.php
   └── includes/
       ├── class-econt-api.php
       ├── class-econt-graphql.php
       └── class-econt-checkout-handler.php
   ```
3. Go to **Plugins > Installed Plugins** in WordPress admin.
4. Find **"Econt GraphQL Bridge"** and click **Activate**.

### Option B — ZIP upload

1. Compress the `econt-graphql-bridge/` folder into a ZIP file.
2. In WordPress admin go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP and click **Install Now**.
4. Activate the plugin.

### Verify activation

After activating you should **not** see any error notices. If you see a red notice listing missing dependencies, install the plugins it mentions.

---

## Step 3 — Configure the bridge plugin's Econt API credentials

The bridge plugin uses its own credentials to call the Econt API (for offices, cities, and shipping calculations exposed through GraphQL). These are stored as WordPress options.

**By default the plugin starts in test mode** with demo credentials pre-filled, so it works out of the box for development.

### Plugin options (in `wp_options` table)

| Option key | Default | Description |
|------------|---------|-------------|
| `econt_gql_bridge_test_mode` | `yes` | `yes` = use demo API, `no` = use production API |
| `econt_gql_bridge_username` | `iasp-dev` | Econt API username (demo default pre-filled) |
| `econt_gql_bridge_password` | `1Asp-dev` | Econt API password (demo default pre-filled) |
| `econt_gql_bridge_sender_city` | `София` | Default sender city for shipping calculations |

### How to set these options

**Option 1 — WP-CLI (recommended):**

```bash
# These are the defaults — you don't need to run these for testing,
# they're already set. Only run them to change values.

# Stay in test/demo mode (default):
wp option update econt_gql_bridge_test_mode yes

# Switch to production:
wp option update econt_gql_bridge_test_mode no
wp option update econt_gql_bridge_username "your-econt-username"
wp option update econt_gql_bridge_password "your-econt-password"
wp option update econt_gql_bridge_sender_city "София"
```

**Option 2 — PHP snippet** (in `functions.php` or a mu-plugin):

```php
// Run once, then remove the code:
update_option( 'econt_gql_bridge_test_mode', 'no' );
update_option( 'econt_gql_bridge_username', 'your-econt-username' );
update_option( 'econt_gql_bridge_password', 'your-econt-password' );
```

**Option 3 — Database directly** (phpMyAdmin / Adminer):

Look in the `wp_options` table for rows with `option_name` starting with `econt_gql_bridge_`.

> **Note:** For development and testing, you don't need to change anything. The demo credentials are set as defaults and the demo API works immediately.

---

## Step 4 — Verify GraphQL queries work

1. Go to **GraphQL > GraphiQL IDE** in WordPress admin (provided by WPGraphQL).
2. Run this test query:

```graphql
query TestEcontOffices {
  econtOffices(city: "София") {
    id
    code
    name
    address
    city
  }
}
```

3. You should get a JSON response with a list of Econt offices in Sofia.
4. If you get an empty array, check:
   - The plugin is activated (no red error notices in admin).
   - Demo credentials are working (they should be by default).
   - Clear transient caches: `wp transient delete --all`
   - Check `wp-content/debug.log` for errors starting with `Econt GraphQL Bridge`.

---

## Step 5 — Configure the Nuxt front-end

The Nuxt side of the integration is already included in the main repository. Make sure:

1. Your `.env` has the correct `GQL_HOST` pointing to your WordPress GraphQL endpoint.
2. Run `npm install` and `npm run dev` to start the Nuxt dev server.
3. Add a product to cart, go to checkout, and verify:
   - Econt appears as a shipping method option.
   - After selecting Econt, the office selector component appears.
   - Selecting a city loads offices.
   - Selecting an office highlights it.

---

## Step 6 — Go to production

1. Set production credentials for the bridge plugin:
   ```bash
   wp option update econt_gql_bridge_test_mode no
   wp option update econt_gql_bridge_username "your-production-username"
   wp option update econt_gql_bridge_password "your-production-password"
   ```
2. Make sure Bulgarisation also has your production Econt credentials configured.
3. Clear transient caches after switching:
   ```bash
   wp transient delete --all
   ```
4. Verify with a real test order (ship a small package to yourself).

---

## File inventory

| File | Purpose |
|------|---------|
| `econt-graphql-bridge.php` | Main plugin bootstrap; dependency checks |
| `includes/class-econt-api.php` | Econt API HTTP wrapper; manages its own credentials via `wp_options` |
| `includes/class-econt-graphql.php` | Registers GraphQL types, queries, mutations, and order fields |
| `includes/class-econt-checkout-handler.php` | Persists Econt office meta on checkout; adds order notes |
