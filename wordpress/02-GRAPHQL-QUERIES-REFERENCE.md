# Econt GraphQL Bridge — Query & Mutation Reference

All queries and mutations below are available once the **Econt GraphQL Bridge** plugin is active. Test them in the **GraphiQL IDE** (WordPress admin > GraphQL > GraphiQL IDE).

---

## Queries

### 1. Get Econt offices

Fetch offices, optionally filtered by city or free-text search.

```graphql
query GetEcontOffices($city: String, $search: String) {
  econtOffices(city: $city, search: $search) {
    id
    code
    name
    nameEn
    address
    city
    postCode
    latitude
    longitude
    workingTimeFrom
    workingTimeTo
    workingTimeHalfFrom
    workingTimeHalfTo
  }
}
```

**Variables:**

```json
{
  "city": "София",
  "search": null
}
```

**Notes:**
- Both `city` and `search` are optional.
- Results are cached for 6 hours server-side (WP transient).
- `search` does a case-insensitive substring match on `name` + `address`.

---

### 2. Get Econt cities

Fetch Bulgarian cities supported by Econt.

```graphql
query GetEcontCities($search: String) {
  econtCities(search: $search) {
    id
    name
    nameEn
    postCode
    regionName
  }
}
```

**Variables:**

```json
{
  "search": "Со"
}
```

**Notes:**
- Cities are cached for 24 hours.
- `search` matches against the Bulgarian city name.

---

### 3. Calculate shipping price

Get a real-time shipping quote from Econt. Uses the Econt `LabelService.createLabel` API with `mode=calculate` under the hood.

```graphql
query CalculateEcontShipping(
  $weight: Float!
  $receiverCity: String
  $senderCity: String
  $senderOfficeCode: String
  $receiverOfficeCode: String
  $codAmount: Float
) {
  econtCalculateShipping(
    weight: $weight
    receiverCity: $receiverCity
    senderCity: $senderCity
    senderOfficeCode: $senderOfficeCode
    receiverOfficeCode: $receiverOfficeCode
    codAmount: $codAmount
  ) {
    price
    currency
    deliveryDays
  }
}
```

**Example 1 — Office-to-office by office codes:**

```json
{
  "weight": 1.5,
  "receiverOfficeCode": "7538"
}
```

**Example 2 — City-to-office:**

```json
{
  "weight": 2.0,
  "senderCity": "София",
  "receiverOfficeCode": "7538"
}
```

**Example 3 — With COD:**

```json
{
  "weight": 3.0,
  "receiverOfficeCode": "7538",
  "codAmount": 50.0
}
```

**Notes:**
- `senderCity` defaults to the sender city configured in the bridge plugin settings (`econt_gql_bridge_sender_city`, default: "София").
- You can provide either `receiverOfficeCode` (for office delivery) or `receiverCity` (for address delivery).
- `codAmount` is in EUR (Econt switched to EUR from 01.01.2026).
- Returns `null` if the API call fails — check WordPress error log for details.

---

### 4. Read Econt data from an order

After checkout, Econt meta is available on the Order type:

```graphql
query GetOrderEcontData($orderId: Int!) {
  order(id: $orderId, idType: DATABASE_ID) {
    databaseId
    status
    total
    econtOfficeCode
    econtOfficeName
    econtOfficeAddress
    econtOfficeCity
    econtTrackingNumber
    econtLabelUrl
  }
}
```

**Fields added to `Order`:**

| Field | Source meta key | Description |
|-------|----------------|-------------|
| `econtOfficeCode` | `_econt_office_code` | Selected office code |
| `econtOfficeName` | `_econt_office_name` | Selected office display name |
| `econtOfficeAddress` | `_econt_office_address` | Selected office full address |
| `econtOfficeCity` | `_econt_office_city` | Selected office city |
| `econtTrackingNumber` | `_econt_tracking_number` | Tracking/AWB number (set after label creation) |
| `econtLabelUrl` | `_econt_label_url` | PDF label URL (set after label creation) |

---

## Mutations

### 1. Save Econt office to an order (post-checkout)

Use this to save or update the selected office on an order after it has been created.

```graphql
mutation SaveEcontOffice(
  $orderId: Int!
  $officeCode: String!
  $officeName: String
  $officeAddress: String
  $officeCity: String
) {
  saveEcontOfficeToOrder(
    input: {
      orderId: $orderId
      officeCode: $officeCode
      officeName: $officeName
      officeAddress: $officeAddress
      officeCity: $officeCity
    }
  ) {
    success
    message
  }
}
```

**Variables:**

```json
{
  "orderId": 123,
  "officeCode": "1234",
  "officeName": "Офис София - Младост 1",
  "officeAddress": "бул. Александър Малинов 51",
  "officeCity": "София"
}
```

---

## How office data flows during checkout

The **primary** (and preferred) way to save the office is via the **checkout mutation's `metaData` array**. The Nuxt front-end includes entries like:

```json
[
  { "key": "_econt_office_code",    "value": "1234" },
  { "key": "_econt_office_name",    "value": "Офис София - Младост 1" },
  { "key": "_econt_office_address", "value": "бул. Ал. Малинов 51" },
  { "key": "_econt_office_city",    "value": "София" }
]
```

WooGraphQL stores these as order meta automatically. The `Econt_Bridge_Checkout_Handler` class then:

1. Detects the `_econt_office_code` meta on the newly created order.
2. Writes Bulgarisation-compatible meta keys (`_shipping_econt_office_*`).
3. Adds an admin-visible order note with the selected office details.

The `saveEcontOfficeToOrder` mutation is a **fallback** for cases where you need to update the office after the order is already created.
