# Enterprise GA4 & sGTM DataLayer Module for PrestaShop

Production-ready PrestaShop module (compatible with PrestaShop 1.7.8.0 through 8.2.x and 9.x) that implements an enterprise-grade Google Tag Manager (GTM) Data Layer following the canonical Google Analytics 4 (GA4) ecommerce measurement schema.

## Key Features

- **Official GA4 Ecommerce Schema:** Full support for `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, and `purchase`.
- **Google Consent Mode v2:** Default denied parameters configured prior to GTM container loading (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`).
- **Server-Side GTM (sGTM) Ready:** Option to set custom transport URLs (e.g., Stape.io or GCP Cloud Run endpoints) to load `gtm.js` from first-party domains and bypass ad blockers.
- **Purchase Idempotency / Deduplication:** Dedicated MySQL database table (`ps_gtm_purchase_log`) preventing duplicate conversion tracking when customers reload the `OrderConfirmation` page.
- **Enhanced Conversions & Meta CAPI Hash Support:** Automatic SHA-256 hashing of customer email, phone, and address data attached as `user_data` to purchase events.
- **Stale Object Clearing:** Automatic dispatch of `dataLayer.push({ ecommerce: null })` before every ecommerce event to avoid attribute leakage in GTM.
- **PrestaShop AJAX Integration:** Native hooks into PrestaShop's frontend events (`updateCart`, `updatedProduct`) for seamless single-page actions and attribute combinations.

## Installation

1. Download or clone this repository as `ps_gtm_datalayer`.
2. Ensure the root directory inside the zip is named `ps_gtm_datalayer`.
3. In your PrestaShop Back Office, navigate to **Modules > Module Manager**.
4. Click **Upload a module** and select `ps_gtm_datalayer.zip`.
5. Click **Configure** and enter your GTM Web Container ID (`GTM-XXXXXXX`).

## Configuration Options

- **GTM Container ID:** Your Google Tag Manager container ID.
- **Server-Side GTM Transport URL:** Optional custom domain endpoint (e.g., `https://gtm.yourdomain.com`).
- **Google Consent Mode v2:** Enable or disable default denied consent setup.
- **Prefer SKU / Reference:** Choose whether `item_id` should prioritize product reference/SKU or product ID.

## Architecture

```text
ps_gtm_datalayer/
├── ps_gtm_datalayer.php          # Main entry, hooks, and back-office configuration
├── config.xml                    # Module metadata
├── logo.png                      # Module icon
├── classes/
│   ├── PayloadBuilder.php        # GA4 payload formatting engine
│   ├── DeduplicationManager.php  # Database-driven purchase idempotency engine
│   └── UserDataHasher.php        # SHA-256 customer data hasher for EC & CAPI
└── views/
    ├── js/
    │   ├── datalayer-core.js     # Standalone normalization and validation library
    │   └── prestashop-adapter.js # PrestaShop event listeners and DOM binders
    └── templates/
        └── hook/
            ├── gtm_head.tpl      # GTM container & Consent Mode v2 loader
            ├── gtm_body.tpl      # Noscript iframe fallback
            └── datalayer_init.tpl# Server-hydrated initial event injection
```
