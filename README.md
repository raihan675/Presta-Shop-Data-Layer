# Enterprise GA4 & sGTM DataLayer Module for PrestaShop

Production-ready PrestaShop module (compatible with PrestaShop 1.7.8.0 through 8.2.x and 9.x) that implements an enterprise-grade Google Tag Manager (GTM) Data Layer following the canonical Google Analytics 4 (GA4) ecommerce measurement schema with dynamic event prefixing and automated version management.

## Key Features

- **Automated Version Management & 1-Click Updates:** Never manually re-upload a ZIP file again. The module queries GitHub releases via `update-manifest.json` and allows safe, 1-click in-place updates from the PrestaShop Back Office while preserving all settings.
- **Dynamic Data Layer Event Prefix:** Set any custom identifier in the Back Office (e.g. `raihan`, `masum`, `client_a`). The module automatically generates `{prefix}_{canonical_event}` while keeping `original_event` intact for clean GTM mapping.
- **Official GA4 Ecommerce Schema:** Full support for `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, and `purchase`.
- **Google Consent Mode v2:** Default denied parameters configured prior to GTM container loading (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`).
- **Server-Side GTM (sGTM) Ready:** Option to set custom transport URLs (e.g., Stape.io or GCP Cloud Run endpoints) to load `gtm.js` from first-party domains and bypass ad blockers.
- **Purchase Idempotency / Deduplication:** Dedicated MySQL database table (`ps_gtm_purchase_log`) preventing duplicate conversion tracking when customers reload the `OrderConfirmation` page.
- **Enhanced Conversions & Meta CAPI Hash Support:** Automatic SHA-256 hashing of customer email, phone, and address data attached as `user_data` to purchase events.
- **Stale Object Clearing:** Automatic dispatch of `dataLayer.push({ ecommerce: null })` before every ecommerce event to avoid attribute leakage in GTM.
- **PrestaShop AJAX Integration:** Native hooks into PrestaShop's frontend events (`updateCart`, `updatedProduct`) for seamless single-page actions and attribute combinations.

## Automated Updates & Version Management

Once installed on a store:
1. When a new release or bugfix is committed to this repository, `update-manifest.json` is updated.
2. The module checks for updates when viewing the module configuration in the Back Office.
3. If an update is detected, a notice displays the new version number, release notes, and a **"1-Click Update"** button.
4. Clicking update creates a temporary backup in `/var/cache/`, downloads the release archive, verifies integrity, extracts the new files, and runs any upgrade routines.
5. All user settings (including `PS_GTM_EVENT_PREFIX` and Container IDs) remain in the `ps_configuration` database table and are 100% preserved.

## Installation

### Initial Installation via Back Office
1. Download `ps_gtm_datalayer.zip` from this repository's root.
2. In your PrestaShop Back Office, navigate to **Modules > Module Manager**.
3. Click **Upload a module** and select `ps_gtm_datalayer.zip`.
4. Click **Configure** and enter your GTM Container ID (`GTM-XXXXXXX`).
5. All future updates can be installed with 1 click directly from the module configuration screen.

## Architecture

```text
ps_gtm_datalayer/
├── ps_gtm_datalayer.php          # Main entry, hooks, and back-office configuration
├── config.xml                    # Module metadata
├── logo.png                      # Module icon
├── update-manifest.json          # Canonical remote version manifest for automated updates
├── classes/
│   ├── UpdateManager.php         # Automated 1-click update & rollback engine
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
