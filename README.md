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

## Key Features

- **Accurate Product & Variant Tracking:** Tracks the exact product ID, combination/variant (like Size or Color), and SKU so you always know precisely which item was viewed, added, or purchased.
- **Accurate Prices & Discounts:** Gets real prices and promotional discounts directly from your store database, so the tracked price always matches what customers see on their screen down to the cent.
- **Exact Cart & Revenue Calculation:** Total value is calculated cleanly from product prices and quantities, keeping shipping costs and taxes separated according to official GA4 standards.
- **No Duplicate Purchases:** Prevents duplicate conversion tracking if a customer refreshes their order confirmation page.
- **Built-in Google Consent Mode v2:** Fully compliant with EU GDPR and Digital Markets Act (DMA) regulations right out of the box.
- **Enhanced Conversions & Meta CAPI Ready:** Collects customer details (email, phone, address) on purchase to boost tracking accuracy in Google Ads and Meta (Facebook) Ads.
- **Server-Side GTM (sGTM) Compatible:** Easily route data through your own custom domain (e.g. Stape or Cloud Run) to bypass ad blockers.
- **Custom Event Names:** Add a custom prefix to your events (e.g. `mybrand_view_item`) or keep standard GA4 names.
- **1-Click Updates:** Update the module directly from your PrestaShop Back Office in one click whenever a new release is available.

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
