# Enterprise GA4 & sGTM DataLayer Module for PrestaShop

[![PrestaShop Compatibility](https://img.shields.io/badge/PrestaShop-1.7.8%20--%208.2.x%20%2F%209.x-blue.svg)](https://www.prestashop.com/)
[![License: Academic Free License (AFL 3.0)](https://img.shields.io/badge/License-AFL%203.0-green.svg)](https://opensource.org/licenses/AFL-3.0)
[![Latest Release](https://img.shields.io/github/v/release/raihan675/Presta-Shop-Data-Layer?color=brightgreen)](https://github.com/raihan675/Presta-Shop-Data-Layer/releases/latest)

An enterprise-grade, high-performance PrestaShop tracking module implementing the canonical **Google Analytics 4 (GA4)** ecommerce dataLayer specification with **Server-Side GTM (sGTM)** support, **Google Consent Mode v2**, and **100% backend-derived data sourcing (Zero DOM Scraping)**.

---

## Supported E-Commerce Events

The module supports **9 core GA4 ecommerce events**, providing complete visibility across every step of the customer purchase journey:

| # | GA4 Event Name | Trigger Location / User Action | Key Parameters Captured |
| :-: | :--- | :--- | :--- |
| **1** | **`view_item_list`** | Visiting a **Category**, **Search Results**, or **Brand / Manufacturer** listing page. | `item_list_id`, `item_list_name`, `currency`, `value`, `items[]` (with `index`, `item_id`, `price`, `item_brand`, `item_category`) |
| **2** | **`select_item`** | Clicking any product card or link from a catalog listing or search grid. | `item_list_id`, `item_list_name`, `items[]` (the exact clicked item with list position and identifiers) |
| **3** | **`view_item`** | Loading a **Product Detail Page (PDP)**, and dynamically re-firing when switching attributes/variants (Size, Color, etc.). | `currency`, `value`, `items[]` (`item_id`, `variant_id`, `sku_id`, `item_variant`, discounted customer price) |
| **4** | **`add_to_wishlist`** | Adding an item to a wishlist from the PDP or listing cards (supports `blockwishlist`, `iqitwishlist`, and custom themes). | `currency`, `value`, `items[]` (authoritative base `item_id`, combination `variant_id`, exact price, quantity) |
| **5** | **`add_to_cart`** | Adding an item from PDP, quick-add from listings, or incrementing quantities in the cart/modal. | `currency`, `value`, `items[]` (calculated mathematically via the Cart Delta Engine, zero DOM dependency) |
| **6** | **`remove_from_cart`** | Removing an item, decreasing quantity, or clearing the entire cart. | `currency`, `value`, `items[]` (the exact units removed and their corresponding value delta) |
| **7** | **`view_cart`** | Viewing the shopping cart summary page (`/cart`). | `currency`, `value`, `items[]` (all items currently in cart with quantities and subtotal) |
| **8** | **`begin_checkout`** | Entering the checkout funnel / order step 1 (`/order`). | `currency`, `value`, `items[]` (reconciled item revenue excluding shipping) |
| **9** | **`purchase`** | Order completion on the **Order Confirmation page**. | `transaction_id`, `value`, `tax`, `shipping`, `currency`, `coupon`, `items[]`, `order_totals`, `user_data` |

---

## Key Architectural Highlights

- **100% Authoritative Backend Data (Zero DOM Scraping):** All prices, variants, combinations, brands, categories, and stock numbers are resolved directly from PrestaShop's core ObjectModels (`Product`, `Combination`, `SpecificPrice`, `Category`, `Manufacturer`). No fragile CSS selectors or HTML scraping.
- **Storefront & Database Price Parity:** Native calculation of promotions and specific prices on net bases before tax application, guaranteeing cent-perfect alignment with customer-facing prices (e.g. `14.99 €`).
- **Mathematical GA4 Financial Reconciliation:** Strictly enforces $\text{ecommerce.value} = \sum (\text{items}[i].\text{price} \times \text{items}[i].\text{quantity})$ across all funnel events. Shipping and taxes are isolated cleanly into GA4 `shipping`, `tax`, and custom `order_totals`.
- **Full Product Variant & Combination Tracking:** 
  - `item_id`: Primary product ID (e.g. `1023`).
  - `variant_id`: Active combination attribute ID (e.g. `299`), or empty string for simple products.
  - `sku_id`: Combination reference when present, falling back to base reference.
  - `item_variant`: Human-readable combination string (e.g. `Color: Blue | Size: M`).
- **Purchase Idempotency & Deduplication:** Dedicated MySQL database table (`ps_gtm_purchase_log`) guarantees purchase conversions are tracked **strictly once per order**, eliminating duplicate revenue when customers refresh the order confirmation page.
- **Enhanced Conversions & Meta CAPI Ready:** Unhashed first-party customer data (`email`, `phone_number`, `address`) formatted under `user_data` on purchase events for server-side GTM and Meta Conversion API normalization.
- **Google Consent Mode v2:** Default denied parameters configured prior to GTM container loading (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`).
- **Server-Side GTM (sGTM) Ready:** Direct custom transport URLs (e.g. Stape.io or GCP Cloud Run) to bypass ad blockers and improve tracking resilience.
- **Dynamic Event Prefixing:** Back-office setting allowing custom event namespaces (e.g. `raihan_view_item`, `raihan_purchase`) while preserving canonical GA4 event names in `original_event`.
- **Automated 1-Click Updates:** Automated update mechanism querying `update-manifest.json` from GitHub releases, allowing safe 1-click in-place upgrades from the PrestaShop Module Manager without manual file uploads.

---

## Installation & Setup

### Method 1: PrestaShop Back Office Upload (Recommended)
1. Download the latest release: [ps_gtm_datalayer.zip](https://github.com/raihan675/Presta-Shop-Data-Layer/releases/latest/download/ps_gtm_datalayer.zip).
2. In your PrestaShop Back Office, navigate to **Modules > Module Manager**.
3. Click **Upload a module** and select `ps_gtm_datalayer.zip`.
4. Click **Configure** once the installation completes.

### Method 2: Manual FTP / SFTP Installation
1. Download and extract `ps_gtm_datalayer.zip`.
2. Upload the unzipped `ps_gtm_datalayer` directory to `/modules/` on your web server.
3. In PrestaShop Back Office, go to **Modules > Module Manager**, find **Enterprise GA4 & sGTM DataLayer**, and click **Install**.

---

## Configuration Settings

In the module configuration screen (**Modules > Module Manager > Enterprise GA4 & sGTM DataLayer > Configure**):

| Setting | Description | Default / Example |
| :--- | :--- | :--- |
| **GTM Container ID** | Your Google Tag Manager Web Container ID | `GTM-XXXXXXX` |
| **Server-Side GTM Transport URL** | Custom domain endpoint for Server-side GTM | `https://gtm.yourdomain.com` |
| **Enable Google Consent Mode v2** | Initializes default denied consent flags for GDPR/DMA | Enabled (`Yes`) |
| **Custom Event Prefix** | Optional custom prefix for DataLayer events | Blank (standard GA4) or custom (e.g. `raihan`) |

---

## License

This project is licensed under the **Academic Free License (AFL 3.0)**.
