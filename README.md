# Enterprise GA4, Meta, Pinterest, TikTok & Google Ads DataLayer Module for PrestaShop

[![PrestaShop Compatibility](https://img.shields.io/badge/PrestaShop-1.7.0.0%20--%208.2.x%20%2F%209.x-blue.svg)](https://www.prestashop.com/)
[![License: Academic Free License (AFL 3.0)](https://img.shields.io/badge/License-AFL%203.0-green.svg)](https://opensource.org/licenses/AFL-3.0)
[![Latest Release](https://img.shields.io/github/v/release/raihan675/Presta-Shop-Data-Layer?color=brightgreen)](https://github.com/raihan675/Presta-Shop-Data-Layer/releases/latest)

An enterprise-grade, high-performance PrestaShop tracking module implementing canonical **Google Analytics 4 (GA4)** ecommerce alongside native, first-class namespaces for **Meta (Facebook/Instagram) Pixel & CAPI**, **Pinterest Ads Tag & Conversions API (CAPI)**, **TikTok Pixel & Events API**, and **Google Ads Dynamic Remarketing (Retail vertical)** with zero client-side transformation required in Google Tag Manager.

Includes **Server-Side GTM (sGTM)** support, **Google Consent Mode v2**, and **100% backend-derived data sourcing (Zero DOM Scraping)**.

---

## Supported E-Commerce Events

The module supports all core ecommerce actions across the full customer funnel with native platform payload generation:

| # | GA4 Event Name | Trigger Location / User Action | Advertising Namespaces Captured |
| :-: | :--- | :--- | :--- |
| **1** | **`view_item_list`** | Visiting a **Category**, **Search Results**, or **Brand / Manufacturer** listing page. On search pages, carries `item_list_id: 'search_results'`, `ecomm_pagetype: 'searchresults'`, and avoids duplicate category signals. | `ecommerce` (`items`, `item_list_id`, `item_list_name`), `google_ads` (`category` or `searchresults` pagetype), `meta`/`pinterest`/`tiktok` on standard categories |
| **2** | **`view_search_results`** | Viewing on-site search results (`/search?s=...` or theme controllers). Captures search intent with authoritative `search_term` and `search_results_count`. | `ecommerce` (`search_term`, `search_results_count`), `meta` (`Search`, `search_string`, `contents`), `pinterest` (`Search`, `search_query`, `line_items`), `tiktok` (`Search`, `search_string`, `query`, `contents`), *(Google Ads Remarketing excluded)* |
| **3** | **`select_item`** | Clicking any product card or link from a catalog listing or search grid. | `ecommerce`, `meta` (`product`), `pinterest` (`PageVisit`), `tiktok`, `google_ads` (`product` pagetype) |
| **4** | **`view_item`** | Loading a **Product Detail Page (PDP)**, and dynamically re-firing when switching attributes/variants (Size, Color, etc.). | `ecommerce`, `meta` (`content_ids`, `contents`), `pinterest` (`PageVisit`, `line_items`), `tiktok` (`content_id`, `contents`), `google_ads` (`product`) |
| **5** | **`add_to_wishlist`** | Adding an item to a wishlist from the PDP or listing cards (supports `blockwishlist`, `iqitwishlist`, and custom themes). | `ecommerce`, `meta`, `pinterest` (`AddToWishList`, `line_items`), `tiktok`, `google_ads` |
| **6** | **`add_to_cart`** | Adding an item from PDP, quick-add from listings, or incrementing quantities in the cart/modal. | `ecommerce`, `meta`, `pinterest` (`AddToCart`, `line_items`), `tiktok`, `google_ads` (`cart` pagetype) |
| **7** | **`remove_from_cart`** | Removing an item, decreasing quantity, or clearing the entire cart. | `ecommerce`, `meta`, `pinterest` (`Custom: RemoveFromCart`), `tiktok`, `google_ads` (`cart` pagetype) |
| **8** | **`view_cart`** | Viewing the shopping cart summary page (`/cart`). | `ecommerce`, `meta`, `pinterest` (`PageVisit`), `tiktok`, `google_ads` (`cart` pagetype) |
| **9** | **`begin_checkout`** | Entering the checkout funnel / order step 1 (`/order`). | `ecommerce`, `meta` (`num_items`), `pinterest` (`InitiateCheckout`, `line_items`), `tiktok`, `google_ads` (`cart` pagetype) |
| **10** | **`add_shipping_info`** | Selecting and confirming a shipping carrier during checkout step 3. | `ecommerce` (`shipping_tier`), `meta`, `pinterest` (`Custom: AddShippingInfo`, `property`), `tiktok`, `google_ads` (`cart` pagetype) |
| **11** | **`add_payment_info`** | Selecting a payment gateway during checkout step 4. | `ecommerce` (`payment_type`), `meta`, `pinterest` (`AddPaymentInfo`, `property`), `tiktok`, `google_ads` (`cart` pagetype) |
| **12** | **`purchase`** | Order completion on the **Order Confirmation page**. | `ecommerce`, `meta` (`num_items`), `pinterest` (`Checkout`, `order_id`, `line_items`, `user_data`), `tiktok`, `google_ads` (`purchase` pagetype), `order_totals`, `user_data` |

---

## Native Advertising Platform Namespaces (Direct GTM Integration)

To eliminate brittle client-side custom JavaScript variables in GTM, every event pushes ready-to-use platform schemas directly alongside GA4:

### 1. Meta (Facebook/Instagram Pixel & Conversions API)
* `meta.event_name`: Meta standard event (`'ViewContent'`, `'ViewCategory'`, `'AddToCart'`, `'InitiateCheckout'`, `'AddPaymentInfo'`, `'AddShippingInfo'`, `'Purchase'`, `'AddToWishlist'`, `'Search'`, or `'Custom'`).
* `meta.custom_event_name`: Custom event label when applicable (e.g., `'RemoveFromCart'`).
* `meta.content_type`: `'product'` (PDP, cart, checkout, purchase) or `'product_group'` (category listings).
* `meta.content_name`: Product name or listing category name.
* `meta.content_category`: Product primary category.
* `meta.content_ids`: Array of strings matching the catalog item ID (`retailer_id` in Meta Commerce Manager).
* `meta.contents`: Array of line items matching Meta official catalog specifications (`id`, `quantity`, `item_price`):
  ```json
  [
    {
      "id": "68_299",
      "quantity": 2,
      "item_price": 13.00
    }
  ]
  ```
* `meta.num_items`: Total unit count on checkout and purchase.
* `meta.order_id`: Authoritative Order Reference for `Purchase` (used for Browser Tag & CAPI deduplication).
* `meta.currency` & `meta.value`: Normalized monetary value.
* `meta.search_string`: Search query string on search results.

### 2. Pinterest Ads (Pinterest Tag & Enhanced Matching)
* `pinterest.event_name`: Pinterest Tag standard event (`'PageVisit'`, `'ViewCategory'`, `'AddToCart'`, `'InitiateCheckout'`, `'AddPaymentInfo'`, `'AddToWishList'`, `'Checkout'`, `'Search'`, or `'Custom'`).
* `pinterest.custom_event_name`: Custom event label when applicable (e.g. `'AddShippingInfo'`, `'RemoveFromCart'`).
* `pinterest.line_items`: Official item array matching Pinterest Web Tag specifications:
  ```json
  [
    {
      "product_id": "68_299",
      "product_name": "Trink und Snack Tasse",
      "product_price": 13.00,
      "product_quantity": 2,
      "product_category": "Trinkbecher",
      "product_brand": "Dooky",
      "product_variant": "Farbe: Stahlblau",
      "product_variant_id": "299"
    }
  ]
  ```
* `pinterest.user_data`: SHA-256 hashed customer identifiers for Pinterest Enhanced / Advanced Matching:
  - `em`: Lowercase, trimmed email (SHA-256)
  - `ph`: Digits only phone number with country code (SHA-256)
  - `fn`: Lowercase first name (SHA-256)
  - `ln`: Lowercase last name (SHA-256)
  - `ct`: Lowercase city without spaces (SHA-256)
  - `st`: Lowercase 2-letter state code (SHA-256)
  - `zp`: Lowercase postal code without spaces (SHA-256)
  - `country`: Lowercase 2-letter ISO country code (SHA-256)
* `pinterest.product_ids`: Top-level array of product catalog IDs.
* `pinterest.order_quantity`: Total item quantity in the event.
* `pinterest.order_id`: Authoritative Order Reference for `Checkout` transaction tracking.
* `pinterest.search_query`: Search query string captured on `view_search_results` / `Search`.
* `pinterest.property`: Contextual metadata (carrier name for `AddShippingInfo`).
* `pinterest.currency` & `pinterest.value`: Exact monetary figures.

### 3. TikTok (Pixel & Events API)
* `tiktok.event_name`: TikTok Pixel standard event (`'ViewContent'`, `'AddToCart'`, `'InitiateCheckout'`, `'AddPaymentInfo'`, `'AddShippingInfo'`, `'CompletePayment'`, `'AddToWishlist'`, `'Search'`, or `'Custom'`).
* `tiktok.custom_event_name`: Custom event label when applicable (e.g., `'RemoveFromCart'`).
* `tiktok.content_type`: `'product'` or `'product_group'`.
* `tiktok.content_name`: Product name or listing category name.
* `tiktok.content_category`: Product category.
* `tiktok.content_id`: Top-level catalog SKU ID.
* `tiktok.content_ids`: Array of strings matching catalog item IDs.
* `tiktok.contents`: Array of line items matching official TikTok Pixel & Events API specifications:
  ```json
  [
    {
      "content_id": "68_299",
      "content_type": "product",
      "content_name": "Trink und Snack Tasse",
      "content_category": "Trinkbecher",
      "quantity": 2,
      "price": 13.00
    }
  ]
  ```
* `tiktok.quantity`: Total item count across all line items.
* `tiktok.order_id`: Authoritative Order Reference for `CompletePayment` (used for Pixel & Events API deduplication).
* `tiktok.search_string` & `tiktok.query`: Search query keyword string on search results (`search_string` is TikTok official standard).
* `tiktok.currency` & `tiktok.value`: Normalized monetary value.

### 4. Google Ads Dynamic Remarketing (Retail Vertical)
* `google_ads.ecomm_pagetype`: Strict standard funnel values (`'category'`, `'searchresults'`, `'product'`, `'cart'`, `'purchase'`).
* `google_ads.ecomm_prodid`: String or Array of strings matching `<g:id>` in Google Merchant Center (GMC).
* `google_ads.ecomm_totalvalue`: Total monetary value.
* `google_ads.ecomm_category`: Category name for category listing pages.
* `google_ads.items`: Array of Google Tag item objects with `google_business_vertical: 'retail'`.

> [!NOTE]
> **Search Architecture & Dual-Event Role Separation**:
> * `view_search_results`: Communicates search intent and query parameters (`search_term`, `search_results_count`) to GA4 Site Search reports, and triggers authoritative `Search` tracking across Meta, TikTok, and Pinterest. To prevent double item impressions in GA4, product items are cleanly delegated to `view_item_list`.
> * `view_item_list`: Communicates product catalog impressions (`item_list_id: 'search_results'`, `item_list_name: 'Search: <query>'`, `items: [...]`) to GA4 Monetization Item List reports, and triggers Google Ads Dynamic Remarketing with `ecomm_pagetype: 'searchresults'`. On search listings, false `ViewCategory` / `ViewContent` emissions are strictly suppressed for Meta, TikTok, and Pinterest.

---

## Google Tag Manager (GTM) Variable Mapping

Create these simple **Data Layer Variables (DLVs)** in your GTM container and bind them directly to your tags:

| GTM Variable Name | Data Layer Variable Path | Target Tag / Usage |
| :--- | :--- | :--- |
| `{{dlv - meta.event_name}}` | `meta.event_name` | Meta Pixel Event Name (`fbq('track', ...)`) |
| `{{dlv - meta.content_ids}}` | `meta.content_ids` | Meta Pixel `content_ids` |
| `{{dlv - meta.content_type}}` | `meta.content_type` | Meta Pixel `content_type` (`product` / `product_group`) |
| `{{dlv - meta.contents}}` | `meta.contents` | Meta Pixel / CAPI line-item breakdown (`id`, `quantity`, `item_price`) |
| `{{dlv - meta.order_id}}` | `meta.order_id` | Meta Pixel / CAPI `event_id` / transaction deduplication |
| `{{dlv - pinterest.event_name}}` | `pinterest.event_name` | Pinterest Tag Event (`pintrk`) |
| `{{dlv - pinterest.line_items}}` | `pinterest.line_items` | Pinterest Tag `line_items` parameter |
| `{{dlv - pinterest.user_data}}` | `pinterest.user_data` | Pinterest Tag Enhanced Matching (`em`, `ph`, `fn`, `ln`, `ct`, `st`, `zp`, `country`) |
| `{{dlv - pinterest.product_ids}}` | `pinterest.product_ids` | Pinterest Tag `product_ids` |
| `{{dlv - pinterest.order_quantity}}` | `pinterest.order_quantity` | Pinterest Tag `order_quantity` |
| `{{dlv - pinterest.order_id}}` | `pinterest.order_id` | Pinterest Tag `order_id` |
| `{{dlv - tiktok.event_name}}` | `tiktok.event_name` | TikTok Pixel Event Name (`ttq.track(...)`) |
| `{{dlv - tiktok.contents}}` | `tiktok.contents` | TikTok Pixel / Events API `contents` array |
| `{{dlv - tiktok.content_type}}` | `tiktok.content_type` | TikTok Pixel `content_type` |
| `{{dlv - tiktok.content_id}}` | `tiktok.content_id` | TikTok Pixel `content_id` |
| `{{dlv - tiktok.order_id}}` | `tiktok.order_id` | TikTok Pixel / Events API `event_id` / deduplication |
| `{{dlv - google_ads.ecomm_prodid}}` | `google_ads.ecomm_prodid` | Google Ads Dynamic Remarketing: **Product ID** |
| `{{dlv - google_ads.ecomm_pagetype}}` | `google_ads.ecomm_pagetype` | Google Ads Dynamic Remarketing: **Page Type** |
| `{{dlv - google_ads.ecomm_totalvalue}}` | `google_ads.ecomm_totalvalue` | Google Ads Dynamic Remarketing: **Total Value** |
| `{{dlv - ecommerce.currency}}` | `ecommerce.currency` | All tags (Currency ISO) |
| `{{dlv - ecommerce.value}}` | `ecommerce.value` | All tags (Event Revenue) |
| `{{dlv - user_data}}` | `user_data` | Enhanced Conversions, Meta Advanced Matching, Pinterest User Data |
| `{{dlv - search_term}}` | `search_term` | GA4 / Enhanced Measurement Search Query |
| `{{dlv - search_results_count}}` | `search_results_count` | Total Number of Search Results Found |
| `{{dlv - meta.search_string}}` | `meta.search_string` | Meta Search Tag (`search_string`) |
| `{{dlv - pinterest.search_query}}` | `pinterest.search_query` | Pinterest Search Tag (`search_query`) |
| `{{dlv - tiktok.search_string}}` | `tiktok.search_string` | TikTok Search Tag official standard (`search_string`) |
| `{{dlv - tiktok.query}}` | `tiktok.query` | TikTok Search Tag legacy alias (`query`) |

---

## Configuration Settings & Back Office Controls

In your PrestaShop Back Office (**Modules > Module Manager > E-Commerce DataLayer > Configure**):

### Core DataLayer Settings
* **GTM Container ID**: Web container ID (`GTM-XXXXXXX`).
* **Data Layer Event Prefix**: Custom prefix for events (e.g. `raihan`, `shop`) or leave empty for standard GA4 event names.
* **Product Catalog Identifier Strategy**: Choose how product IDs match your merchant feeds:
  1. **Composite Database ID**: `{id_product}_{id_product_attribute}` (e.g., `68_299` or `1033`). **(Recommended)**
  2. **Base Product ID**: `{id_product}` (e.g., `68` or `1033`).
  3. **Reference / Physical SKU**: Authoritative combination/product reference (e.g., `BHK125-B`).
* **Server-Side GTM Transport URL**: Custom domain endpoint for Server-side GTM tagging server (e.g., `https://gtm.yourdomain.com`).
* **Enable Google Consent Mode v2**: Automatically injects GDPR/DMA compliant default denied consent flags before GTM loads.

### Advertising Platform DataLayer Settings
* **Master Switch (`PS_GTM_ADV_MASTER_ENABLED`)**: Global switch to enable or suppress all advertising namespaces (`meta`, `pinterest`, `tiktok`, `google_ads`) across all events in a single click.
* **Platform-Based Expandable Routing Panels**: Each supported advertising platform (**Meta**, **Google Ads**, **TikTok**, and **Pinterest**) features its own expandable accordion panel.
* **Independent Platform Master Toggles**: Enable or disable data layer output for an individual platform directly on its header card. Disabling a platform preserves its underlying event selections.
* **Granular Event-Level Toggles**: Expand any platform to view its supported ecommerce events, payload specifications, and independently toggle data layer dispatch per event.
* **Platform-Specific Bulk Actions**: Each expanded panel includes instant "Enable all events" and "Disable all events" buttons scoped exclusively to that platform.
* **Global Controls**: Quick "Expand All", "Collapse All", "Enable All Platforms", and "Disable All Platforms" toolbar buttons.

---

## Installation & Setup

### Method 1: PrestaShop Back Office Upload (Recommended)
1. Download the latest release archive: [ps_gtm_datalayer.zip](https://github.com/raihan675/Presta-Shop-Data-Layer/releases/latest/download/ps_gtm_datalayer.zip).
2. In PrestaShop Back Office, navigate to **Modules > Module Manager**.
3. Click **Upload a module** and select `ps_gtm_datalayer.zip`.
4. Click **Configure** once installation succeeds.

### Method 2: Manual FTP / SFTP Installation
1. Download and extract `ps_gtm_datalayer.zip`.
2. Upload the unzipped `ps_gtm_datalayer` folder into `/modules/` on your server.
3. In PrestaShop Back Office, find **E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok & Google Ads** in Module Manager and click **Install**.

---

## License

This project is licensed under the **Academic Free License (AFL 3.0)**.
Author: **Raihan**
