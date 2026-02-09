# How to Create an Econt API Connection

This document describes how to integrate **Econt** (Bulgarian courier/shipping) with your WooNuxt/WooCommerce store via the official Econt API.

---

## 1. Overview

Econt provides a **SOAP/JSON API** for:

- **Labels** — create, update, delete shipping labels
- **Shipments** — request courier pickup, track status
- **Nomenclatures** — countries, cities, offices, streets, quarters
- **Address validation** — validate addresses and service times
- **Profile** — client profiles and agreements

All requests use **HTTP POST** over **SSL**. Authentication is **HTTP Basic Auth**.

---

## 2. Prerequisites

### 2.1 Register for API access

| Environment | Registration | Base URL |
|-------------|--------------|----------|
| **Demo (testing)** | [login-demo.econt.com/register](https://login-demo.econt.com/register/) | `https://demo.econt.com/ee/services` |
| **Production** | [ee.econt.com – Register](https://ee.econt.com/load_direct.php?target=Register) or contact Econt | `https://ee.econt.com/services` |

- Start with **Demo** to develop and test.
- For **production**, use your e-econt user credentials or a new account from the link above.

### 2.2 Credentials

- **Demo (public test):**
  - Username: `iasp-dev`
  - Password: `1Asp-dev`
- **Production:** your e-econt username and password.

Store these in environment variables (e.g. `.env`) and never commit them.

---

## 3. Authentication

- **Method:** HTTP **Basic Authentication**
- **Header:** `Authorization: Basic <base64(username:password)>`
- SSL client certificates are **not** used; only HTTPS + Basic Auth.

Example (conceptual):

```http
POST /services/Shipments/LabelService.createLabel HTTP/1.1
Host: ee.econt.com
Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=
Content-Type: application/json
```

In code: send the same `Authorization` header with every request to the Econt API.

---

## 4. Environments and base URLs

| Purpose | Base URL | Notes |
|--------|----------|--------|
| **Demo** | `https://demo.econt.com/ee/services` | Replace `ee.econt.com/services` with `demo.econt.com/ee/services` for demo |
| **Production** | `https://ee.econt.com/services` | Live shipments and billing |

Use one base URL per environment (e.g. from `.env`: `ECONT_API_BASE_URL`).

---

## 5. Main API areas

### 5.1 Nomenclatures (reference data)

- **Service:** [NomenclaturesService](https://ee.econt.com/Nomenclatures/#NomenclaturesService)
- **Typical use:** offices dropdown, city/street validation, shipping zones.
- **Methods:** `getCountries`, `getCities`, `getOffices`, `getStreets`, `getQuarters`, `validateAddress`, `addressServiceTimes`, `getNearestOffices`.

### 5.2 Shipments & labels

- **LabelService:** [ee.econt.com/Shipments/#LabelService](https://ee.econt.com/Shipments/#LabelService)
  - `createLabel` / `createLabels` — create label(s)
  - `updateLabel` / `updateLabels` — update label(s)
  - `deleteLabels` — cancel label(s)
  - `checkPossibleShipmentEditions`, `grouping`, `groupingCancelation`
- **ShipmentService:** [ee.econt.com/Shipments/#ShipmentService](https://ee.econt.com/Shipments/#ShipmentService)
  - `requestCourier` — request pickup
  - `getShipmentStatuses` — track shipment
  - `getRequestCourierStatus`, `getMyAWB`, `setITUCode`

### 5.3 Profile

- **ProfileService:** [ee.econt.com/Profile/#ProfileService](https://ee.econt.com/Profile/#ProfileService)
- **Methods:** `getClientProfiles`, `createCDAgreement` (e.g. cash-on-delivery agreement).

---

## 6. Implementation plan (high level)

### Phase 1: Setup and reference data

1. **Environment**
   - Add to `.env` (or WordPress env):
     - `ECONT_API_BASE_URL` (demo or production)
     - `ECONT_USERNAME`
     - `ECONT_PASSWORD`
   - Use demo first; switch to production when going live.

2. **API client**
   - Create a small module (Nuxt server API or WordPress plugin) that:
     - Sends POST requests to Econt base URL.
     - Adds `Authorization: Basic ...` to every request.
     - Handles JSON or SOAP according to [official docs](https://ee.econt.com/services/).

3. **Nomenclatures**
   - Call `getCountries`, `getCities`, `getOffices` (and optionally streets/quarters).
   - Use this to:
     - Populate “Econt office” selector in checkout (WooNuxt/WooCommerce).
     - Validate city/address if needed.

### Phase 2: Checkout and labels

4. **Checkout**
   - In WooNuxt: add “Econt office” (and optionally “address”) to shipping step.
   - Store chosen office ID (and address data) with the order (WooCommerce order meta or custom tables).

5. **Create label after order**
   - When order is paid/confirmed (WooCommerce hook or Nuxt server action calling WordPress):
     - Call Econt `createLabel` / `createLabels` with:
       - Sender data (your Econt profile/address).
       - Receiver data from order (office ID or address).
       - Parcel info (weight, size if required).
     - Save returned AWB/label ID to the order (e.g. order meta).

6. **Optional: request courier**
   - If you ship from address (not from office): call `requestCourier` with time window; optionally poll `getRequestCourierStatus`.

### Phase 3: Tracking and UX

7. **Tracking**
   - Use `getShipmentStatuses` (by AWB or your reference) to show status in “My orders” and in WooCommerce admin.
   - Expose tracking link/status in WooNuxt (e.g. order history page).

8. **Labels and documents**
   - Econt may return label PDF/URL; store it and show “Print label” in admin and/or customer email.

---

## 7. Where to implement (WooNuxt + WordPress)

| Part | Suggested place | Reason |
|------|------------------|--------|
| **Credentials & base URL** | WordPress env or WooNuxt server `.env` | Secret handling on server |
| **API client (HTTP + Basic Auth)** | Nuxt server API route or WordPress plugin | Single place for all Econt calls |
| **Offices/cities (dropdowns)** | Nuxt composable or page calling your server API | Used in frontend checkout |
| **Create label / request courier** | WordPress (order hook) or Nuxt server calling WordPress + Econt | Triggered by order events |
| **Tracking display** | WooNuxt (order history) + optional WooCommerce admin | Frontend + backend |

You can keep all Econt logic in a **WordPress plugin** (PHP) and only call it from Nuxt via your existing backend, or implement the client in **Nuxt server** and call Econt from there; both are valid.

---

## 8. Checklist

- [ ] Register demo account (and later production).
- [ ] Add `ECONT_API_BASE_URL`, `ECONT_USERNAME`, `ECONT_PASSWORD` to env.
- [ ] Implement HTTP client with Basic Auth and POST to Econt.
- [ ] Integrate Nomenclatures (offices/cities) in checkout.
- [ ] Map WooCommerce order fields → Econt createLabel payload.
- [ ] Create label on order confirmation; save AWB to order.
- [ ] (Optional) Implement `requestCourier` if you use pickup.
- [ ] Implement tracking (getShipmentStatuses) and show in store + admin.
- [ ] Switch to production URL and credentials when going live.
- [ ] Handle errors (validation, network, Econt error responses).

---

## 9. Official links

| Resource | URL |
|----------|-----|
| **Services overview** | [ee.econt.com/services](https://ee.econt.com/services/) |
| **Nomenclatures** | [ee.econt.com/Nomenclatures](https://ee.econt.com/Nomenclatures/) |
| **Shipments / labels** | [ee.econt.com/Shipments](https://ee.econt.com/Shipments/) |
| **Profile** | [ee.econt.com/Profile](https://ee.econt.com/Profile/) |
| **Demo** | [demo.econt.com/ee/services](https://demo.econt.com/ee/services/) |
| **Production register** | [ee.econt.com – Register](https://ee.econt.com/load_direct.php?target=Register) |
| **XML manual (PDF)** | [econt.com/e-econt/doc_templates/XML_manual_engl.pdf](https://econt.com/e-econt/doc_templates/XML_manual_engl.pdf) |
| **Support** | support_integrations@econt.com |

---

## 10. Next steps

1. Implement a minimal **Econt API client** (one service, e.g. `getOffices`) with Basic Auth.
2. Add **Econt office selector** to WooNuxt checkout using that client.
3. Implement **createLabel** on order placement and **getShipmentStatuses** for tracking.

Once this is in place, the rest is mapping data and handling edge cases (missing offices, failed label creation, etc.).
