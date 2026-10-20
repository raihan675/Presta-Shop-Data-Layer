# Enterprise GA4 & sGTM DataLayer Module for PrestaShop

Production-ready PrestaShop module (compatible with PrestaShop 1.7.8.0 through 8.2.x and 9.x) that implements an enterprise-grade Google Tag Manager (GTM) Data Layer following the canonical Google Analytics 4 (GA4) ecommerce measurement schema with catalog-accurate multi-feed matching and automated version management.

## Key Features

- **Multi-Catalog Product Identity Engine:** Automatically resolves and outputs matching identifiers for Google Merchant Center (`item_id_gmc`), Meta Commerce Manager (`item_id_meta`), SKU (`item_sku`), Database ID (`item_internal_id`), and GTIN (`item_gtin`).
- **Precision Value & Order Reconciliation:** Exact floating-point price calculations derived directly from PrestaShop core ObjectModels (`Product::getPriceStatic()`, `$cart->getOrderTotal()`, `$order->total_paid_tax_incl`).
- **Automated Version Management & 1-Click Updates:** Never manually re-upload a ZIP file again. The module queries GitHub releases via `update-manifest.json` and allows safe, 1-click in-place updates from the PrestaShop Back Office while preserving all settings.
- **Dynamic Data Layer Event Prefix:** Set any custom identifier in the Back Office (e.g. `raihan`, `masum`, `client_a`). The module automatically generates `{prefix}_{canonical_event}` while keeping `original_event` intact for clean GTM mapping.
- **Enhanced Conversions & Meta CAPI Hash Support:** Automatic SHA-256 hashing of customer email, phone, and address data attached as `user_data` to purchase events.
- **Google Consent Mode v2:** Default denied parameters configured prior to GTM container loading (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`).
- **Server-Side GTM (sGTM) Ready:** Option to set custom transport URLs (e.g., Stape.io or GCP Cloud Run endpoints) to load `gtm.js` from first-party domains and bypass ad blockers.
- **Purchase Idempotency / Deduplication:** Dedicated MySQL database table (`ps_gtm_purchase_log`) preventing duplicate conversion tracking when customers reload the `OrderConfirmation` page.

## Configuration Options

- **GTM Container ID:** Your Google Tag Manager container ID (e.g. `GTM-XXXXXXX`).
- **Data Layer Event Prefix:** Custom prefix for dataLayer events (e.g. `raihan`, `client_a`). Leave blank for default standard names.
- **Google Merchant Center Item ID Strategy:** Choose how GMC product feeds identify items (`SKU / Reference`, `Product ID`, `ProductID-AttributeID`, or `EAN-13`).
- **Meta Catalog Content ID Strategy:** Choose how Meta Catalog identifies products (`SKU / Reference`, `Product ID`, `ProductID-AttributeID`, or `EAN-13`).
- **Server-Side GTM Transport URL:** Optional custom domain endpoint (e.g., `https://gtm.yourdomain.com`).
- **Google Consent Mode v2:** Enable or disable default denied consent setup.

## Architecture

```text
ps_gtm_datalayer/
├── ps_gtm_datalayer.php            # Main entry, hooks, and back-office configuration
├── config.xml                      # Module metadata (v1.2.0)
├── logo.png                        # Module icon
├── update-manifest.json            # Canonical remote version manifest for automated updates
├── classes/
│   ├── ProductIdentityResolver.php # Multi-catalog identifier resolution engine
│   ├── CustomerDataEngine.php      # PII normalization and SHA-256 cryptographic hasher
│   ├── PayloadBuilder.php          # GA4 payload formatting engine
│   ├── DeduplicationManager.php    # Database-driven purchase idempotency engine
│   └── UpdateManager.php           # Automated 1-click update & rollback engine
└── views/
    ├── js/
    │   ├── datalayer-core.js       # Standalone normalization and validation library
    │   └── prestashop-adapter.js   # PrestaShop event listeners and DOM binders
    └── templates/
        └── hook/
            ├── gtm_head.tpl        # GTM container & Consent Mode v2 loader
            ├── gtm_body.tpl        # Noscript iframe fallback
            └── datalayer_init.tpl  # Server-hydrated initial event injection
```
