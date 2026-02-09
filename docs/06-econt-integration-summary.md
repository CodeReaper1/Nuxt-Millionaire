# Econt Integration — What We Built & How It Works

## The One-Sentence Version

We built a bridge that lets your headless Nuxt store talk to Econt through WordPress, so customers can pick an Econt office at checkout and the order gets all the right shipping data.

---

## The Problem (Why We Needed This)

Your store is **headless**: Nuxt (what the customer sees) and WordPress (where orders live) are completely separate. They only talk through GraphQL.

The **Bulgarisation for WooCommerce** plugin already handles Econt in regular WordPress stores — shipping rates, office selection, the whole thing. But it does this by injecting JavaScript into the WooCommerce checkout page. Your Nuxt frontend never sees that JavaScript. It's like having a translator in a room you never enter.

**What already works without our code:**
- Shipping rates. Bulgarisation calculates Econt prices, WooGraphQL exposes them on the cart. Your Nuxt checkout already shows them. Done.

**What was missing:**
- The office selector. Customers need to pick which Econt office they want their package delivered to. That UI only existed inside WordPress.
- Saving the chosen office to the order. Even if you somehow picked an office, there was no way to send that choice back to WordPress through GraphQL.

---

## What We Built

Three things, working together:

```
┌──────────────────────────────────────────────────┐
│  1. WordPress Plugin (econt-graphql-bridge)       │
│     Lives in: wordpress/econt-graphql-bridge/     │
│     Job: Expose Econt data via GraphQL            │
│          Save office choice to orders             │
└──────────────────────────┬───────────────────────┘
                           │ GraphQL
┌──────────────────────────┴───────────────────────┐
│  2. GraphQL Queries & Composable                  │
│     Lives in: app/queries/ + app/composables/     │
│     Job: Fetch offices/cities from WordPress      │
│          Track what the customer selected          │
│          Send office data with the checkout        │
└──────────────────────────┬───────────────────────┘
                           │ uses
┌──────────────────────────┴───────────────────────┐
│  3. Office Selector Component + Checkout Changes  │
│     Lives in: components/ + woonuxt_base/         │
│     Job: Show city dropdown + office list          │
│          Block checkout until office is picked     │
└──────────────────────────────────────────────────┘
```

---

## Part 1: The WordPress Plugin

**Folder:** `wordpress/econt-graphql-bridge/`

This is a regular WordPress plugin you upload to `wp-content/plugins/`. It works *alongside* Bulgarisation — it doesn't replace it.

### What each file does

| File | Think of it as... |
|------|-------------------|
| `econt-graphql-bridge.php` | The power switch. Checks that WPGraphQL, WooCommerce, and Bulgarisation are all active. If anything's missing, shows a red warning in WordPress admin. |
| `includes/class-econt-api.php` | The translator. Talks to Econt's API using its own credentials (stored in `wp_options`). Defaults to demo mode with test credentials pre-filled. Caches results so it doesn't hammer Econt's servers. |
| `includes/class-econt-graphql.php` | The menu. Adds new "dishes" to your GraphQL endpoint that Nuxt can order: offices, cities, shipping calculator, and Econt fields on orders. |
| `includes/class-econt-checkout-handler.php` | The bookkeeper. When an order comes in with Econt office data, it makes sure the data is saved in the format Bulgarisation expects, and adds a note to the order so you can see which office was picked. |

### Key design decision: own credentials, clean separation

The bridge plugin stores its own Econt API credentials in `wp_options` (keys starting with `econt_gql_bridge_`). By default it starts in **test mode** with demo credentials pre-filled, so it works immediately for development.

Bulgarisation handles its own credentials for WooCommerce shipping rate calculations. The bridge plugin handles its own credentials for the GraphQL office/city queries. Two separate concerns, no dependency on each other's internal settings.

---

## Part 2: The Nuxt Side

### GraphQL Queries (3 files)

**Folder:** `app/queries/`

| File | What it asks WordPress |
|------|----------------------|
| `getEcontOffices.gql` | "Give me Econt offices, optionally in this city or matching this search" |
| `getEcontCities.gql` | "Give me all Bulgarian cities Econt supports" |
| `saveEcontOffice.gql` | "Save this office to order #123" (fallback mutation, rarely needed) |

### The Composable

**File:** `app/composables/useEcont.ts`

This is the brain of the Nuxt integration. It's a single `useEcont()` function that gives you everything:

```
useEcont() returns:
├── cities           → list of Bulgarian cities (reactive)
├── offices          → list of offices for the selected city (reactive)
├── selectedOffice   → the office the customer picked (reactive)
├── selectedCity     → which city they're looking at (reactive)
├── loading          → is something loading right now? (reactive)
├── error            → did something break? (reactive)
│
├── fetchCities()    → load cities from GraphQL
├── fetchOffices()   → load offices from GraphQL
├── selectOffice()   → mark an office as chosen
├── clearSelection() → reset everything
├── getCheckoutMetaData()      → build the meta entries for checkout
└── isEcontShippingMethod()    → "is this shipping ID an Econt one?"
```

**The important method:** `getCheckoutMetaData()` returns an array like:

```json
[
  { "key": "_econt_office_code",    "value": "1234" },
  { "key": "_econt_office_name",    "value": "Офис София - Младост 1" },
  { "key": "_econt_office_address", "value": "бул. Ал. Малинов 51" },
  { "key": "_econt_office_city",    "value": "София" }
]
```

This gets merged into the checkout mutation's `metaData` array. WordPress receives it, WooGraphQL stores it as order meta, and the checkout handler on the WordPress side does any extra formatting.

### The Component

**File:** `components/shopElements/EcontOfficeSelector.vue`

What the customer sees:

1. A **city dropdown** (loads all Bulgarian cities on mount)
2. A **search box** (appears after selecting a city, debounced 300ms)
3. An **office list** (scrollable, shows name, address, working hours)
4. A **selected office summary** (green card with a "Change" button)

It supports dark mode, uses the same Tailwind classes as the rest of the store, and works with `v-model` so the parent component can read the selection.

---

## Part 3: Checkout Changes

### What changed in `checkout.vue`

Three additions:

1. **Imported `useEcont()`** at the top of the script to get `selectedOffice`, `isEcontShippingMethod`, and `getCheckoutMetaData`.

2. **Added `isEcontSelected` computed.** It checks if `cart.chosenShippingMethods[0]` contains the word "econt". When true, the office selector appears.

3. **Added checkout validation.** If Econt is selected but no office is chosen, the checkout button is disabled. No accidental orders without a delivery point.

In the template, the `<EcontOfficeSelector>` sits between the shipping methods and the payment methods.

### What changed in `useCheckout.ts`

One change inside `buildCheckoutPayload()`:

Before building the checkout payload, it calls `useEcont().getCheckoutMetaData()` and merges the result into the `metaData` array. If no Econt office is selected, the array is empty and nothing changes. If an office is selected, the four `_econt_*` meta entries ride along with the checkout mutation.

---

## The Full Flow (What Happens When a Customer Orders)

```
1. Customer adds product to cart

2. Goes to checkout
   → Nuxt asks WordPress: "what shipping methods are available?"
   → WordPress (via Bulgarisation) returns: "Еконт до офис — 5.50 лв."
   → Nuxt shows this as a shipping option

3. Customer clicks "Еконт до офис"
   → isEcontSelected becomes true
   → EcontOfficeSelector appears
   → Cities load from GraphQL (WordPress → Econt API → cached → response)

4. Customer selects "София"
   → Offices for Sofia load from GraphQL
   → Customer sees a scrollable list of offices

5. Customer clicks "Офис София - Младост 1"
   → Green summary card appears
   → Checkout button becomes enabled

6. Customer clicks "Complete Order"
   → buildCheckoutPayload() runs
   → metaData now includes _econt_office_code, _name, _address, _city
   → GraphQL checkout mutation fires
   → WooGraphQL creates the order with all the meta

7. WordPress receives the order
   → econt-checkout-handler detects _econt_office_code in the meta
   → Writes Bulgarisation-compatible duplicate keys
   → Adds order note: "[Econt] Избран офис: Офис София - Младост 1 — бул. Ал. Малинов 51, София"

8. You see the order in WooCommerce admin
   → Office details visible in order meta
   → Order note confirms the selection
   → Bulgarisation can generate the shipping label using the saved office data
```

---

## File Map (Everything We Created or Changed)

### New Files

| File | What |
|------|------|
| `wordpress/econt-graphql-bridge/econt-graphql-bridge.php` | WP plugin bootstrap |
| `wordpress/econt-graphql-bridge/includes/class-econt-api.php` | Econt API wrapper |
| `wordpress/econt-graphql-bridge/includes/class-econt-graphql.php` | GraphQL types, queries, mutations |
| `wordpress/econt-graphql-bridge/includes/class-econt-checkout-handler.php` | Order meta handler |
| `wordpress/01-SETUP-INSTRUCTIONS.md` | How to install and configure |
| `wordpress/02-GRAPHQL-QUERIES-REFERENCE.md` | All GraphQL queries with examples |
| `wordpress/03-TROUBLESHOOTING.md` | Common problems and fixes |
| `app/queries/getEcontOffices.gql` | GraphQL query |
| `app/queries/getEcontCities.gql` | GraphQL query |
| `app/queries/saveEcontOffice.gql` | GraphQL mutation |
| `app/composables/useEcont.ts` | Nuxt composable |
| `components/shopElements/EcontOfficeSelector.vue` | Office picker UI |

### Modified Files

| File | What changed |
|------|-------------|
| `woonuxt_base/app/pages/checkout.vue` | Added Econt composable, `isEcontSelected` computed, checkout validation, and `<EcontOfficeSelector>` in template |
| `woonuxt_base/app/composables/useCheckout.ts` | `buildCheckoutPayload()` now merges Econt office meta into `metaData` |

---

## Quick Setup Reminder

1. Install **Bulgarisation for WooCommerce** in WordPress
2. Configure Econt credentials in Bulgarisation settings (for shipping rates)
3. Add Econt as a shipping method in WooCommerce shipping zones (Bulgaria)
4. Upload `wordpress/econt-graphql-bridge/` to `wp-content/plugins/` and activate
5. Test in GraphiQL: `query { econtOffices(city: "София") { name address } }`
6. Run Nuxt dev, add a product to cart, go to checkout, select Econt, pick an office, complete the order
7. Check the order in WooCommerce admin — office data should be there

---

## What If Something Breaks?

**Offices don't load?** Check bridge plugin credentials (`wp option get econt_gql_bridge_username`). Clear transients. Check `wp-content/debug.log`.

**Shipping rates missing?** That's Bulgarisation's job, not the bridge plugin. Make sure Econt is added to a WooCommerce shipping zone for Bulgaria.

**Office not saved on order?** Open DevTools Network tab and check if `_econt_office_code` is in the checkout mutation's `metaData`. If it's there but not on the order, check the WordPress side.

**Checkout button stays disabled?** The button requires an office selection when Econt is chosen. Pick an office and it will enable.

Full troubleshooting guide: `wordpress/03-TROUBLESHOOTING.md`
