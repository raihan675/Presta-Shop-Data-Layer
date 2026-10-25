# Enterprise GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads DataLayer Module for PrestaShop

[![PrestaShop Compatibility](https://img.shields.io/badge/PrestaShop-1.7.0.0%20--%208.2.x%20%2F%209.x-blue.svg)](https://www.prestashop.com/)
[![License: Academic Free License (AFL 3.0)](https://img.shields.io/badge/License-AFL%203.0-green.svg)](https://opensource.org/licenses/AFL-3.0)
[![Latest Release](https://img.shields.io/github/v/release/raihan675/Presta-Shop-Data-Layer?color=brightgreen)](https://github.com/raihan675/Presta-Shop-Data-Layer/releases/latest)

An enterprise-grade, high-performance PrestaShop tracking module implementing canonical **Google Analytics 4 (GA4)** ecommerce alongside native, first-class namespaces for **Meta (Facebook/Instagram) Pixel & CAPI**, **Pinterest Ads Tag & Conversions API (CAPI)**, **TikTok Pixel & Events API**, **Google Ads Dynamic Remarketing (Retail vertical)**, and **Microsoft Advertising (Bing Ads) & UET** with zero client-side transformation required in Google Tag Manager.

Includes **Server-Side GTM (sGTM)** support, **Server-to-Server Webhook Engine (Refunds, Offline Orders & Status Changes)**, and **100% backend-derived data sourcing (Zero DOM Scraping)**.

---

## Supported E-Commerce Events

The module supports all core ecommerce actions across the full customer funnel with native platform payload generation:

| # | GA4 Event Name | Trigger Location / User Action | Advertising Namespaces Captured |
| :-: | :--- | :--- | :--- |
| **1** | **`view_item_list`** | Visiting a genuine product listing page (**Category**, **Home Featured**, **Brand / Manufacturer**, **On Sale**, **New Products**, **Best Sellers**). Carries authentic `item_list_id` (e.g. `category_12`) and `item_list_name` (e.g. `Trinkbecher`). Never fires on search pages. | `ecommerce` (`items`, `item_list_id`, `item_list_name`), `google_ads` (`category` pagetype), `meta` (`ViewCategory`), `pinterest` (`ViewCategory`), `tiktok` (`ViewContent`), `microsoft_ads` (`view_item_list`, `category` pagetype) |
| **2** | **`view_search_results`** | Viewing on-site search results (`/search?s=...` or theme search controllers). Strictly represents search action with authoritative `search_term`, `search_results_count`, and returned `items`. Never triggers `view_item_list`. | `ecommerce` (`search_term`, `search_results_count`, `items`), `google_ads` (`searchresults` pagetype, `search_term`, `items`), `meta` (`Search`, `search_string`, `contents`), `pinterest` (`Search`, `search_query`, `line_items`), `tiktok` (`Search`, `search_string`, `query`, `contents`), `microsoft_ads` (`view_search_results`, `search_term`, `searchresults` pagetype) |
| **3** | **`view_item`** | Loading a **Product Detail Page (PDP)**, and dynamically re-firing when switching attributes/variants (Size, Color, etc.). | `ecommerce`, `meta` (`content_ids`, `contents`), `pinterest` (`PageVisit`, `line_items`), `tiktok` (`content_id`, `contents`), `google_ads` (`product`), `microsoft_ads` (`view_item`, `product` pagetype, `items`) |
| **4** | **`add_to_wishlist`** | Adding an item to a wishlist from the PDP or listing cards (supports PrestaShop official `blockwishlist` and standard endpoints with zero DOM scraping). | `ecommerce`, `meta`, `pinterest` (`AddToWishList`, `line_items`), `tiktok`, `google_ads`, `microsoft_ads` (`add_to_wishlist`, `other` pagetype) |
| **5** | **`add_to_cart`** | Adding an item from PDP, quick-add, or incrementing quantities in the cart/modal via Universal Cart Delta Engine. | `ecommerce`, `meta`, `pinterest` (`AddToCart`, `line_items`), `tiktok`, `google_ads` (`cart` pagetype), `microsoft_ads` (`add_to_cart`, `cart` pagetype, `revenue_value`) |
| **6** | **`remove_from_cart`** | Removing an item, decreasing quantity, or clearing the entire cart via Universal Cart Delta Engine. | `ecommerce`, `meta`, `pinterest` (`Custom: RemoveFromCart`), `tiktok`, `google_ads` (`cart` pagetype), `microsoft_ads` (`remove_from_cart`, `cart` pagetype) |
| **7** | **`view_cart`** | Viewing the shopping cart summary page (`/cart`). | `ecommerce`, `meta`, `pinterest` (`Custom: ViewCart`), `tiktok`, `google_ads` (`cart` pagetype), `microsoft_ads` (`view_cart`, `cart` pagetype) |
| **8** | **`begin_checkout`** | Entering the checkout funnel / order step 1 (`/order`). | `ecommerce`, `meta` (`num_items`), `pinterest` (`InitiateCheckout`, `line_items`), `tiktok`, `google_ads` (`cart` pagetype), `microsoft_ads` (`begin_checkout`, `checkout_step: 1`, `cart` pagetype) |
| **9** | **`add_shipping_info`** | Selecting and confirming a shipping carrier during checkout step 3. | `ecommerce` (`shipping_tier`), `meta`, `pinterest` (`Custom: AddShippingInfo`, `property`), `tiktok`, `google_ads` (`cart` pagetype), `microsoft_ads` (`add_shipping_info`, `checkout_step: 3`) |
| **10** | **`add_payment_info`** | Selecting a payment gateway during checkout step 4. | `ecommerce` (`payment_type`), `meta`, `pinterest` (`AddPaymentInfo`, `property`), `tiktok`, `google_ads` (`cart` pagetype), `microsoft_ads` (`add_payment_info`, `checkout_step: 4`) |
| **11** | **`purchase`** | Order completion on the **Order Confirmation page**. | `ecommerce`, `meta` (`num_items`), `pinterest` (`Checkout`, `order_id`, `line_items`, `user_data`), `tiktok`, `google_ads` (`purchase` pagetype, `cart_data`), `microsoft_ads` (`purchase`, `transaction_id`, `revenue_value`, `tax`, `shipping`, `pid`), `new_customer`, `customer_lifetime_value`, `order_totals`, `user_data` |
| **12** | **`refund`** *(Server-to-Server)* | Administrator generates a partial or full Credit Slip in PrestaShop Back Office (`actionOrderSlipAdd`). | Server-to-server HTTP POST to sGTM (`/g/collect?v=2`) with refunded items, catalog IDs, quantities, values, and SHA-256 customer data. Deduplicated via `ps_gtm_refund_log`. |
| **13** | **`purchase`** *(Offline Server-to-Server)* | Administrator creates or validates an order offline/manually in Back Office (`actionValidateOrder`). | Server-to-server HTTP POST to sGTM without requiring storefront browser session. Deduplicated via `gtm_purchase_log`. |
| **14** | **`order_status_update`** *(Server-to-Server)* | Order/payment status changes in Back Office (`actionOrderStatusPostUpdate`), e.g. Bank Wire `#10` to Payment Accepted `#2`, Mirakl `#11`. | Dispatches new status ID, localized name, native `is_paid` and `is_shipped` flags, items, and SHA-256 customer data. Features self-healing `payment_revoked` classification and `conversion_action: "retract"`. |

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
* `meta.user_data`: SHA-256 hashed customer identifiers for Meta Advanced Matching:
  - `em`: Lowercase, trimmed email (SHA-256)
  - `ph`: Digits only phone number with country calling code, strictly without `+` prefix (SHA-256)
  - `fn`: Lowercase first name with punctuation removed (SHA-256)
  - `ln`: Lowercase last name with punctuation removed (SHA-256)
  - `ct`: Lowercase city without spaces or punctuation (SHA-256)
  - `st`: Lowercase 2-letter state code (SHA-256)
  - `zp`: Lowercase postal code without spaces or dashes (SHA-256)
  - `country`: Lowercase 2-letter ISO country code (SHA-256)

### 2. Pinterest Ads (Pinterest Tag & Enhanced Matching)
* `pinterest.event_name`: Pinterest Tag standard event (`'PageVisit'`, `'ViewCategory'`, `'AddToCart'`, `'InitiateCheckout'`, `'AddPaymentInfo'`, `'AddToWishList'`, `'Checkout'`, `'Search'`, or `'Custom'`).
* `pinterest.custom_event_name`: Custom event label when applicable (e.g. `'ViewCart'`, `'AddShippingInfo'`, `'RemoveFromCart'`).
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
* `tiktok.user_data`: SHA-256 hashed customer identifiers for TikTok Advanced Matching:
  - `email`: Lowercase, trimmed email (SHA-256)
  - `phone_number`: E.164 normalized phone number (+[country code][digits]) (SHA-256)
* `tiktok.search_string` & `tiktok.query`: Search query keyword string on search results (`search_string` is TikTok official standard).
* `tiktok.currency` & `tiktok.value`: Normalized monetary value.

### 4. Google Ads Data Layer (Cart Data Conversions & Retail Dynamic Remarketing)
* **Retail Dynamic Remarketing (`google_ads.dynamic_remarketing`)**:
  * `google_ads.dynamic_remarketing.event_name`: Exact dynamic remarketing event name (`'view_item'`, `'view_item_list'`, `'view_search_results'`, `'add_to_cart'`, `'remove_from_cart'`, `'view_cart'`, `'begin_checkout'`, `'purchase'`).
  * `google_ads.dynamic_remarketing.items`: Dedicated dynamic remarketing items array containing strictly `id` (Merchant Center matching ID) and `google_business_vertical: 'retail'`.
  * `google_ads.dynamic_remarketing.ecomm_pagetype`: Strict standard funnel values (`'category'`, `'searchresults'`, `'product'`, `'cart'`, `'purchase'`).
  * `google_ads.dynamic_remarketing.ecomm_prodid`: String or Array of strings matching `<g:id>` in Google Merchant Center (GMC).
  * `google_ads.dynamic_remarketing.ecomm_totalvalue`: Total monetary value.
  * `google_ads.dynamic_remarketing.ecomm_category`: Category name for category listing pages.
* **Conversions with Cart Data (`google_ads.cart_data`)**:
  * `google_ads.cart_data.event_name`: Conversion event name (`'view_item'`, `'add_to_cart'`, `'view_cart'`, `'begin_checkout'`, `'purchase'`, etc.).
  * `google_ads.cart_data.value`: Monetary conversion value.
  * `google_ads.cart_data.currency`: 3-letter ISO 4217 currency code (`EUR`, `USD`, etc.).
  * `google_ads.cart_data.items`: Dedicated cart data items array containing strictly feed-matching `id`, units `quantity`, and unit `price`.
  * `google_ads.cart_data.transaction_id`: PrestaShop Order ID / Transaction reference (on purchase).
  * `google_ads.cart_data.discount`: Top-level order discount / voucher amount (on purchase).
  * `google_ads.cart_data.aw_merchant_id`: Linked Google Merchant Center Account ID (configured in Back Office).
  * `google_ads.cart_data.aw_feed_country`: 2-letter CLDR country code of order delivery address.
  * `google_ads.cart_data.aw_feed_language`: 2-letter ISO language code of order context.
  * `google_ads.cart_data.new_customer`: Boolean (`true`/`false`) identifying whether the purchase is from a first-time buyer (Google Ads New Customer Acquisition - NCA).
  * `google_ads.cart_data.customer_lifetime_value`: Total cumulative lifetime spend of the customer in store currency, calculated authoritatively from PrestaShop's core order database.

### 5. Microsoft Advertising (Bing Ads & UET)
* `microsoft_ads.event_action`: Official standard event name (`'view_item'`, `'view_item_list'`, `'view_search_results'`, `'select_item'`, `'add_to_cart'`, `'remove_from_cart'`, `'view_cart'`, `'begin_checkout'`, `'add_shipping_info'`, `'add_payment_info'`, `'purchase'`, `'add_to_wishlist'`).
* `microsoft_ads.revenue_value`: Normalized event conversion revenue.
* `microsoft_ads.currency`: 3-letter ISO currency code.
* `microsoft_ads.transaction_id`: Authoritative numeric Order ID (e.g., `'1239'`) on purchase conversions.
* `microsoft_ads.tax`, `microsoft_ads.shipping`, `microsoft_ads.coupon`: Order totals and voucher code.
* `microsoft_ads.items`: Dedicated product array matching Microsoft catalog specifications:
  ```json
  [
    {
      "id": "68_299",
      "name": "Trink und Snack Tasse",
      "brand": "Dooky",
      "category": "Trinkbecher",
      "variant": "Farbe: Stahlblau",
      "price": 13.00,
      "quantity": 1
    }
  ]
  ```
* `microsoft_ads.dynamic_remarketing`: Retail dynamic remarketing object:
  * `ecomm_pagetype`: `'home'`, `'category'`, `'searchresults'`, `'product'`, `'cart'`, `'purchase'`, `'other'`.
  * `ecomm_prodid`: Array of strings across all events conforming strictly to Microsoft UET specifications (e.g. `['68_299']` on PDP, `['68_299', '1013']` on listings/cart/purchase).
  * `ecomm_totalvalue`: Page-level conversion total value.
  * `ecomm_category`: Listing or product category name.
  * `ecomm_query`: Search keywords on search result listings.
* `microsoft_ads.pid`: SHA-256 hashed customer identifiers for Enhanced Conversions / Advanced Matching:
  * `email` / `em`: Lowercase trimmed email hash.
  * `phone_number` / `ph`: E.164 normalized phone hash.

> [!NOTE]
> **Strict Event Separation: Search vs Product-List Tracking**:
> * `view_search_results`: Strictly fires when a user performs a search action and views on-site search results. Communicates search intent and query parameters (`search_term`, `search_results_count`) alongside returned catalog `items` directly to GA4 Site Search reports, and triggers authoritative `Search` / dynamic remarketing tracking across Meta, TikTok, Pinterest, Google Ads, and Microsoft Advertising (`ecomm_pagetype: 'searchresults'`).
> * `view_item_list`: Strictly fires only when a user visits genuine product lists (Category, Home Featured, Brand, On Sale, New Products, Best Sellers) with authentic list IDs and names. A search page is never classified as a product list, and `view_item_list` never fires automatically following `view_search_results`.

---

## Google Tag Manager (GTM) Variable Mapping

Create these simple **Data Layer Variables (DLVs)** in your GTM container and bind them directly to your tags:

| GTM Variable Name | Data Layer Variable Path | Target Tag / Usage |
| :--- | :--- | :--- |
| `{{dlv - meta.event_name}}` | `meta.event_name` | Meta Pixel Event Name (`fbq('track', ...)`) |
| `{{dlv - meta.content_ids}}` | `meta.content_ids` | Meta Pixel `content_ids` |
| `{{dlv - meta.content_type}}` | `meta.content_type` | Meta Pixel `content_type` (`product` / `product_group`) |
| `{{dlv - meta.contents}}` | `meta.contents` | Meta Pixel / CAPI line-item breakdown (`id`, `quantity`, `item_price`) |
| `{{dlv - meta.user_data}}` | `meta.user_data` | Meta Pixel & CAPI Advanced Matching (`em`, `ph`, `fn`, `ln`, `ct`, `st`, `zp`, `country`) |
| `{{dlv - meta.order_id}}` | `meta.order_id` | Meta Pixel / CAPI `event_id` / transaction deduplication |
| `{{dlv - pinterest.event_name}}` | `pinterest.event_name` | Pinterest Tag Event (`pintrk`) |
| `{{dlv - pinterest.line_items}}` | `pinterest.line_items` | Pinterest Tag `line_items` parameter |
| `{{dlv - pinterest.user_data}}` | `pinterest.user_data` | Pinterest Tag Enhanced Matching (`em`, `ph`, `fn`, `ln`, `ct`, `st`, `zp`, `country`) |
| `{{dlv - pinterest.product_ids}}` | `pinterest.product_ids` | Pinterest Tag `product_ids` |
| `{{dlv - pinterest.order_quantity}}` | `pinterest.order_quantity` | Pinterest Tag `order_quantity` |
| `{{dlv - pinterest.order_id}}` | `pinterest.order_id` | Pinterest Tag `order_id` |
| `{{dlv - tiktok.event_name}}` | `tiktok.event_name` | TikTok Pixel Event Name (`ttq.track(...)`) |
| `{{dlv - tiktok.contents}}` | `tiktok.contents` | TikTok Pixel / Events API `contents` array |
| `{{dlv - tiktok.user_data}}` | `tiktok.user_data` | TikTok Advanced Matching (`email`, `phone_number`) |
| `{{dlv - tiktok.content_type}}` | `tiktok.content_type` | TikTok Pixel `content_type` |
| `{{dlv - tiktok.content_id}}` | `tiktok.content_id` | TikTok Pixel `content_id` |
| `{{dlv - tiktok.order_id}}` | `tiktok.order_id` | TikTok Pixel / Events API `event_id` / deduplication |
| `{{dlv - google_ads.dynamic_remarketing.items}}` | `google_ads.dynamic_remarketing.items` | Google Ads Retail Dynamic Remarketing `items` array (`id`, `google_business_vertical: 'retail'`) |
| `{{dlv - google_ads.dynamic_remarketing.event_name}}` | `google_ads.dynamic_remarketing.event_name` | Google Ads Dynamic Remarketing Event Name |
| `{{dlv - google_ads.dynamic_remarketing.ecomm_prodid}}` | `google_ads.dynamic_remarketing.ecomm_prodid` | Google Ads Dynamic Remarketing: **Product ID** |
| `{{dlv - google_ads.dynamic_remarketing.ecomm_pagetype}}` | `google_ads.dynamic_remarketing.ecomm_pagetype` | Google Ads Dynamic Remarketing: **Page Type** |
| `{{dlv - google_ads.dynamic_remarketing.ecomm_totalvalue}}` | `google_ads.dynamic_remarketing.ecomm_totalvalue` | Google Ads Dynamic Remarketing: **Total Value** |
| `{{dlv - google_ads.cart_data.items}}` | `google_ads.cart_data.items` | Google Ads Conversions with Cart Data `items` array (`id`, `quantity`, `price`) |
| `{{dlv - google_ads.cart_data.transaction_id}}` | `google_ads.cart_data.transaction_id` | Google Ads Conversion Transaction ID |
| `{{dlv - google_ads.cart_data.value}}` | `google_ads.cart_data.value` | Google Ads Conversion Total Value |
| `{{dlv - google_ads.cart_data.discount}}` | `google_ads.cart_data.discount` | Google Ads Conversion Top-Level Discount |
| `{{dlv - google_ads.cart_data.aw_merchant_id}}` | `google_ads.cart_data.aw_merchant_id` | Google Ads Conversion Merchant Center ID (`mid`) |
| `{{dlv - google_ads.cart_data.aw_feed_country}}` | `google_ads.cart_data.aw_feed_country` | Google Ads Conversion Feed Country |
| `{{dlv - google_ads.cart_data.aw_feed_language}}` | `google_ads.cart_data.aw_feed_language` | Google Ads Conversion Feed Language |
| `{{dlv - new_customer}}` | `new_customer` | Google Ads New Customer Acquisition (`true` / `false`) |
| `{{dlv - customer_lifetime_value}}` | `customer_lifetime_value` | Google Ads Customer Lifetime Value (CLV / LTV total spend) |
| `{{dlv - google_ads.cart_data.new_customer}}` | `google_ads.cart_data.new_customer` | Google Ads NCA `new_customer` boolean under `cart_data` |
| `{{dlv - google_ads.cart_data.customer_lifetime_value}}` | `google_ads.cart_data.customer_lifetime_value` | Google Ads CLV `customer_lifetime_value` numeric spend under `cart_data` |
| `{{dlv - ecommerce.currency}}` | `ecommerce.currency` | All tags (Currency ISO) |
| `{{dlv - ecommerce.value}}` | `ecommerce.value` | All tags (Event Revenue) |
| `{{dlv - user_data}}` | `user_data` | Enhanced Conversions, Meta Advanced Matching, Pinterest User Data |
| `{{dlv - search_term}}` | `search_term` | GA4 / Enhanced Measurement Search Query |
| `{{dlv - search_results_count}}` | `search_results_count` | Total Number of Search Results Found |
| `{{dlv - meta.search_string}}` | `meta.search_string` | Meta Search Tag (`search_string`) |
| `{{dlv - pinterest.search_query}}` | `pinterest.search_query` | Pinterest Search Tag (`search_query`) |
| `{{dlv - tiktok.search_string}}` | `tiktok.search_string` | TikTok Search Tag official standard (`search_string`) |
| `{{dlv - tiktok.query}}` | `tiktok.query` | TikTok Search Tag legacy alias (`query`) |
| `{{dlv - microsoft_ads.event_action}}` | `microsoft_ads.event_action` | Microsoft Advertising UET Event Action (`event_action`) |
| `{{dlv - microsoft_ads.revenue_value}}` | `microsoft_ads.revenue_value` | Microsoft Advertising Conversion Value (`revenue_value`) |
| `{{dlv - microsoft_ads.currency}}` | `microsoft_ads.currency` | Microsoft Advertising Conversion Currency (`currency`) |
| `{{dlv - microsoft_ads.transaction_id}}` | `microsoft_ads.transaction_id` | Microsoft Advertising Conversion Transaction ID (`transaction_id`) |
| `{{dlv - microsoft_ads.items}}` | `microsoft_ads.items` | Microsoft Advertising Product Array (`items`) |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_prodid}}` | `microsoft_ads.dynamic_remarketing.ecomm_prodid` | Microsoft Retail Dynamic Remarketing: **Product ID** |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_pagetype}}` | `microsoft_ads.dynamic_remarketing.ecomm_pagetype` | Microsoft Retail Dynamic Remarketing: **Page Type** |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_totalvalue}}` | `microsoft_ads.dynamic_remarketing.ecomm_totalvalue` | Microsoft Retail Dynamic Remarketing: **Total Value** |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_category}}` | `microsoft_ads.dynamic_remarketing.ecomm_category` | Microsoft Retail Dynamic Remarketing: **Category** |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_query}}` | `microsoft_ads.dynamic_remarketing.ecomm_query` | Microsoft Retail Dynamic Remarketing: **Search Query** |
| `{{dlv - microsoft_ads.pid}}` | `microsoft_ads.pid` | Microsoft Enhanced Conversions / UET Enhanced Matching (`email`, `phone_number`) |

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
* **Server-Side GTM Transport URL**: Custom domain or Stape.io endpoint for Server-side GTM tagging server (e.g., `https://gtm.yourdomain.com` or `https://xxxxxx.stape.io`).
* **Server-to-Server Refund Webhooks**: Automatically captures Back Office credit slips (`actionOrderSlipAdd`) and sends authoritative `refund` events to sGTM / GA4 / Meta CAPI / Google Ads via non-blocking cURL.
* **Server-to-Server Offline Order Webhooks**: Automatically dispatches `purchase` events for offline or admin-created orders validated in the Back Office (`actionValidateOrder`).
* **Server-to-Server Order Status Change Webhooks**: Automatically dispatches `order_status_update` events to sGTM whenever an order/payment status changes in the Back Office (`actionOrderStatusPostUpdate`), carrying `new_status_id`, `new_status_name`, `is_paid`, `is_shipped`, items, and hashed customer PII.

### Advertising Platform DataLayer Settings
* **Master Switch (`PS_GTM_ADV_MASTER_ENABLED`)**: Global switch to enable or suppress all advertising namespaces (`meta`, `pinterest`, `tiktok`, `google_ads`, `microsoft_ads`) across all events in a single click.
* **Platform-Based Expandable Routing Panels**: Each supported advertising platform (**Meta**, **Google Ads**, **TikTok**, **Pinterest**, and **Microsoft Advertising**) features its own expandable accordion panel.
* **Independent Platform Master Toggles**: Enable or disable data layer output for an individual platform directly on its header card. Disabling a platform preserves its underlying event selections.
* **Granular Event-Level Toggles**: Expand any platform to view its supported ecommerce events, payload specifications, and independently toggle data layer dispatch per event.
* **Platform-Specific Bulk Actions**: Each expanded panel includes instant "Enable all events" and "Disable all events" buttons scoped exclusively to that platform.
* **Global Controls**: Quick "Expand All", "Collapse All", "Enable All Platforms", and "Disable All Platforms" toolbar buttons.

---

## Server-to-Server Webhook Engine (Server GTM / Stape.io)

For ecommerce events that occur outside of active customer browser sessions, `ps_gtm_datalayer` features an asynchronous, non-blocking server-to-server webhook engine that dispatches events directly to your **Server-Side GTM Transport URL** (`/g/collect?v=2`).

### 1. Back Office Refunds (`refund`)
* **Hook**: `actionOrderSlipAdd`
* **Trigger**: Store administrator generates a partial or full Credit Slip in the Back Office (`Orders > View Order > Partial Refund`).
* **Payload**: Carries exact refunded line items (formatted according to your Catalog Strategy), unit prices, refunded quantities, shipping refund, and SHA-256 customer identifiers (`email_address`, `phone_number`, address).
* **Deduplication**: Recorded in `ps_gtm_refund_log` to guarantee every credit slip is transmitted strictly once.

### 2. Offline / Admin Orders (`purchase`)
* **Hook**: `actionValidateOrder`
* **Trigger**: Orders created manually by administrators in the Back Office or received via offline sync without an active storefront checkout session.
* **Payload**: Full GA4 `purchase` payload with line items, tax, shipping, discounts, and customer data.
* **Deduplication**: Verified against `gtm_purchase_log` to prevent double-counting of storefront orders.

### 3. Universal Order & Payment Status Changes (`order_status_update`)
* **Hook**: `actionOrderStatusPostUpdate`
* **Trigger**: Any status change in the Back Office (e.g. Bank Wire `#10` &rarr; Payment Accepted `#2`, Mirakl marketplace confirmation `#11`, Shipped `#4`, Delivered `#5`).
* **Universal Portability**: Reads PrestaShop's core `$orderState->paid` and `$orderState->shipped` flags with 0 hardcoded status IDs or languages.
* **Self-Healing Mistake Reversal Detection**:
  * Automatically detects if an administrator accidentally marks an order as paid and then reverts it to an unpaid or canceled state.
  * Transitions are automatically classified:
    * **`payment_confirmed`** (`conversion_action: "record"`): Unpaid &rarr; Paid. Triggers Google Ads offline conversion and Meta CAPI.
    * **`payment_revoked`** (`conversion_action: "retract"`): Paid &rarr; Unpaid/Canceled. Enables Google Ads **Conversion Retractions** and GA4 **Refunds** to automatically deduct mistakenly attributed revenue.
    * **`status_changed_while_paid`** (`conversion_action: "maintain"`): Paid &rarr; Paid. Preserves attribution without duplicate firing.
    * **`status_changed_unpaid`** (`conversion_action: "none"`): Informational status updates.
* **Fail-Safe Performance**: Dispatched via asynchronous cURL with a strict **1500ms execution timeout**, ensuring Back Office administration is never slowed down.

### 4. Dedicated PrestaShop Webhook Client Template for sGTM
To bypass path collisions on `/g/collect` and ensure 100% deterministic parameter parsing without reliance on third-party clients, the module includes a turnkey **Server GTM Client Template** (`prestashop-client-template.tpl`):
* **One-Click Import**: In Server GTM, navigate to **Templates > Client Templates > New > ⋮ (top right) > Import**, and select `prestashop-client-template.tpl`.
* **Zero Collision**: Listens on its own dedicated endpoint: `/ps-webhook` (configurable via `PS_SGTM_WEBHOOK_ENDPOINT_PATH`).
* **Instant Common Event Data**: Converts incoming PrestaShop JSON directly into standard Google Common Event Data (`event_name`, `transaction_id`, `items`, `user_data`, `status_transition_type`, `conversion_action`, `is_paid`), evaluating downstream tags (Meta CAPI, Google Ads, GA4) in real-time.
* **PrestaShop System Audit Logs**: All webhook dispatches are recorded directly in **Advanced Parameters > Logs** (`PrestaShopLogger`), indicating the event name, endpoint URL, and HTTP status code (`200 OK` or errors).

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
3. In PrestaShop Back Office, find **E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads** in Module Manager and click **Install**.

---

## License

This project is licensed under the **Academic Free License (AFL 3.0)**.
Author: **Raihan**
