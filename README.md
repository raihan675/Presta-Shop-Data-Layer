# Enterprise GA4, Meta, TikTok & Google Ads DataLayer Module for PrestaShop

[![PrestaShop Compatibility](https://img.shields.io/badge/PrestaShop-1.7.8%20--%208.2.x%20%2F%209.x-blue.svg)](https://www.prestashop.com/)
[![License: Academic Free License (AFL 3.0)](https://img.shields.io/badge/License-AFL%203.0-green.svg)](https://opensource.org/licenses/AFL-3.0)
[![Latest Release](https://img.shields.io/github/v/release/raihan675/Presta-Shop-Data-Layer?color=brightgreen)](https://github.com/raihan675/Presta-Shop-Data-Layer/releases/latest)

An enterprise-grade, high-performance PrestaShop tracking module implementing canonical **Google Analytics 4 (GA4)** ecommerce alongside native, first-class namespaces for **Meta (Facebook/Instagram) Pixel & CAPI**, **TikTok Pixel & Events API**, and **Google Ads Dynamic Remarketing (Retail vertical)** with zero client-side transformation required in Google Tag Manager.

Includes **Server-Side GTM (sGTM)** support, **Google Consent Mode v2**, and **100% backend-derived data sourcing (Zero DOM Scraping)**.

---

## Supported E-Commerce Events

The module supports **11 core ecommerce events**, providing complete visibility across every step of the customer purchase funnel:

| # | GA4 Event Name | Trigger Location / User Action | Advertising Namespaces Captured |
| :-: | :--- | :--- | :--- |
| **1** | **`view_item_list`** | Visiting a **Category**, **Search Results**, or **Brand / Manufacturer** listing page. | `ecommerce`, `meta` (`product_group`), `tiktok` (`product_group`), `google_ads` (`category` pagetype) |
| **2** | **`select_item`** | Clicking any product card or link from a catalog listing or search grid. | `ecommerce`, `meta` (`product`), `tiktok`, `google_ads` (`product` pagetype) |
| **3** | **`view_item`** | Loading a **Product Detail Page (PDP)**, and dynamically re-firing when switching attributes/variants (Size, Color, etc.). | `ecommerce`, `meta` (`content_ids`, `contents`), `tiktok` (`content_id`, `contents`), `google_ads` (`product`) |
| **4** | **`add_to_wishlist`** | Adding an item to a wishlist from the PDP or listing cards (supports `blockwishlist`, `iqitwishlist`, and custom themes). | `ecommerce`, `meta`, `tiktok`, `google_ads` |
| **5** | **`add_to_cart`** | Adding an item from PDP, quick-add from listings, or incrementing quantities in the cart/modal. | `ecommerce`, `meta`, `tiktok`, `google_ads` (`cart` pagetype) |
| **6** | **`remove_from_cart`** | Removing an item, decreasing quantity, or clearing the entire cart. | `ecommerce`, `meta`, `tiktok`, `google_ads` (`cart` pagetype) |
| **7** | **`view_cart`** | Viewing the shopping cart summary page (`/cart`). | `ecommerce`, `meta`, `tiktok`, `google_ads` (`cart` pagetype) |
| **8** | **`begin_checkout`** | Entering the checkout funnel / order step 1 (`/order`). | `ecommerce`, `meta` (`num_items`), `tiktok`, `google_ads` (`cart` pagetype) |
| **9** | **`add_shipping_info`** | Selecting and confirming a shipping carrier during checkout. | `ecommerce` (`shipping_tier`), `meta`, `tiktok`, `google_ads` (`cart` pagetype) |
| **10** | **`add_payment_info`** | Selecting a payment gateway during checkout. | `ecommerce` (`payment_type`), `meta`, `tiktok`, `google_ads` (`cart` pagetype) |
| **11** | **`purchase`** | Order completion on the **Order Confirmation page**. | `ecommerce`, `meta` (`num_items`), `tiktok`, `google_ads` (`purchase` pagetype), `order_totals`, `user_data` |

---

## Native Advertising Platform Namespaces (Direct GTM Integration)

To eliminate brittle client-side custom JavaScript variables in GTM, every event pushes ready-to-use platform schemas directly alongside GA4:

### 1. Meta (Facebook/Instagram Pixel & Conversions API)
* `meta.content_type`: `'product'` (PDP, cart, checkout, purchase) or `'product_group'` (category listings).
* `meta.content_ids`: Array of strings matching the catalog item ID (`retailer_id` in Meta Commerce Manager).
* `meta.contents`: Array of line items with `{ id, quantity, item_price }`.
* `meta.num_items`: Total unit count on checkout and purchase.
* `meta.currency` & `meta.value`: Normalized monetary value.

### 2. TikTok (Pixel & Events API)
* `tiktok.content_type`: `'product'` or `'product_group'`.
* `tiktok.content_id`: Top-level catalog SKU ID.
* `tiktok.contents`: Array of line items with `{ content_id, content_type, content_name, quantity, price }`.
* `tiktok.currency` & `tiktok.value`: Normalized monetary value.

### 3. Google Ads Dynamic Remarketing (Retail Vertical)
* `google_ads.ecomm_pagetype`: Strict standard funnel values (`'category'`, `'searchresults'`, `'product'`, `'cart'`, `'purchase'`).
* `google_ads.ecomm_prodid`: String or Array of strings matching `<g:id>` in Google Merchant Center (GMC).
* `google_ads.ecomm_totalvalue`: Total monetary value.
* `google_ads.ecomm_category`: Category name for category listing pages.
* `google_ads.items`: Array of Google Tag item objects with `google_business_vertical: 'retail'`.

---

## Google Tag Manager (GTM) Variable Mapping

Create these simple **Data Layer Variables (DLVs)** in your GTM container and bind them directly to your advertising tags:

| GTM Variable Name | Data Layer Variable Path | Target Tag / Usage |
| :--- | :--- | :--- |
| `{{dlv - meta.content_ids}}` | `meta.content_ids` | Meta Pixel `content_ids` |
| `{{dlv - meta.content_type}}` | `meta.content_type` | Meta Pixel `content_type` (`product` / `product_group`) |
| `{{dlv - meta.contents}}` | `meta.contents` | Meta Pixel / CAPI line-item breakdown |
| `{{dlv - tiktok.contents}}` | `tiktok.contents` | TikTok Pixel / Events API `contents` array |
| `{{dlv - tiktok.content_type}}` | `tiktok.content_type` | TikTok Pixel `content_type` |
| `{{dlv - tiktok.content_id}}` | `tiktok.content_id` | TikTok Pixel `content_id` |
| `{{dlv - google_ads.ecomm_prodid}}` | `google_ads.ecomm_prodid` | Google Ads Dynamic Remarketing: **Product ID** |
| `{{dlv - google_ads.ecomm_pagetype}}` | `google_ads.ecomm_pagetype` | Google Ads Dynamic Remarketing: **Page Type** |
| `{{dlv - google_ads.ecomm_totalvalue}}` | `google_ads.ecomm_totalvalue` | Google Ads Dynamic Remarketing: **Total Value** |
| `{{dlv - ecommerce.currency}}` | `ecommerce.currency` | All tags (Currency ISO) |
| `{{dlv - ecommerce.value}}` | `ecommerce.value` | All tags (Event Revenue) |
| `{{dlv - user_data}}` | `user_data` | Enhanced Conversions & Meta Advanced Matching |

---

## Configuration Settings

In the module configuration screen (**Modules > Module Manager > Enterprise GA4, Meta, TikTok & Google Ads DataLayer > Configure**):

| Setting | Description | Default / Options |
| :--- | :--- | :--- |
| **GTM Container ID** | Your Google Tag Manager Web Container ID | `GTM-XXXXXXX` |
| **Product Catalog Identifier Strategy** | Selects how product IDs match your advertising feeds (GMC, Meta Catalog, TikTok Catalog) | **Composite Database ID: `{id_product}_{id_product_attribute}`** (Recommended) / **Base Product ID: `{id_product}`** / **Reference / SKU: `{sku}`** |
| **Data Layer Event Prefix** | Custom prefix for DataLayer events | Blank (standard GA4) or custom (e.g. `raihan`, `masum`) |
| **Server-Side GTM Transport URL** | Custom domain endpoint for Server-side GTM | `https://gtm.yourdomain.com` |
| **Enable Google Consent Mode v2** | Initializes default denied consent flags for GDPR/DMA | Enabled (`Yes`) |

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
3. In PrestaShop Back Office, go to **Modules > Module Manager**, find **Enterprise GA4, Meta, TikTok & Google Ads DataLayer**, and click **Install**.

---

## License

This project is licensed under the **Academic Free License (AFL 3.0)**.
