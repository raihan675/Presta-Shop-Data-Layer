# Enterprise GA4 & sGTM DataLayer Module for PrestaShop

Production-ready PrestaShop module (compatible with PrestaShop 1.7.8.0 through 8.2.x and 9.x) that implements an enterprise-grade Google Tag Manager (GTM) Data Layer following the canonical Google Analytics 4 (GA4) ecommerce measurement schema with dynamic event prefixing.

## Key Features

- **Dynamic Data Layer Event Prefix:** Set any custom identifier in the Back Office (e.g. `raihan`, `masum`, `client_a`). The module automatically generates `{prefix}_{canonical_event}` while keeping `original_event` intact for clean GTM mapping.
- **Official GA4 Ecommerce Schema:** Full support for `view_item_list`, `select_item`, `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, and `purchase`.
- **Google Consent Mode v2:** Default denied parameters configured prior to GTM container loading (`ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`).
- **Server-Side GTM (sGTM) Ready:** Option to set custom transport URLs (e.g., Stape.io or GCP Cloud Run endpoints) to load `gtm.js` from first-party domains and bypass ad blockers.
- **Purchase Idempotency / Deduplication:** Dedicated MySQL database table (`ps_gtm_purchase_log`) preventing duplicate conversion tracking when customers reload the `OrderConfirmation` page.
- **Enhanced Conversions & Meta CAPI Hash Support:** Automatic SHA-256 hashing of customer email, phone, and address data attached as `user_data` to purchase events.
- **Stale Object Clearing:** Automatic dispatch of `dataLayer.push({ ecommerce: null })` before every ecommerce event to avoid attribute leakage in GTM.
- **PrestaShop AJAX Integration:** Native hooks into PrestaShop's frontend events (`updateCart`, `updatedProduct`) for seamless single-page actions and attribute combinations.

## Dynamic Event Prefixing & GTM Configuration

When a prefix is configured in the module (e.g., `raihan`), the dataLayer receives:

```javascript
window.dataLayer.push({
  event: "raihan_view_item",
  original_event: "view_item",
  ecommerce: { ... }
});
```

### Recommended GTM Setup for Dynamic Prefixes

1. **Custom Event Trigger (Regex):**
   * Event Name: `.*(view_item_list|select_item|view_item|add_to_cart|remove_from_cart|view_cart|begin_checkout|add_shipping_info|add_payment_info)$`
   * Check: **Use regex matching**
   *(Matches with or without prefix automatically).*

2. **GA4 Event Tag Event Name:**
   * Create a Data Layer Variable: `dlv - original_event` pointing to `original_event`.
   * In your GA4 Event tag, set **Event Name** to `{{dlv - original_event}}` (with fallback to `{{_event}}`).
   * This ensures GA4 always receives the standard canonical name (`view_item`), even when your Data Layer uses a custom identifier like `raihan_view_item`.

## Installation

### Method A: Uploading the ZIP via Back Office (Recommended)
1. Download `ps_gtm_datalayer.zip` from this repository's **Releases** or use the pre-built packages.
2. In your PrestaShop Back Office, navigate to **Modules > Module Manager**.
3. Click **Upload a module** and select the zip file.
4. Click **Configure** and enter your GTM Web Container ID (`GTM-XXXXXXX`).

### Method B: Git Clone / Direct Server Copy
1. Clone this repository directly into your PrestaShop modules directory:
   ```bash
   cd /path/to/prestashop/modules/
   git clone https://github.com/raihan675/Presta-Shop-Data-Layer.git ps_gtm_datalayer
   ```
2. Navigate to **Modules > Module Manager** in your PrestaShop Back Office.
3. Search for **Enterprise GA4 & sGTM DataLayer** and click **Install**.

## Configuration Options

- **GTM Container ID:** Your Google Tag Manager container ID (e.g. `GTM-XXXXXXX`).
- **Data Layer Event Prefix:** Custom prefix for dataLayer events (e.g. `raihan`, `client_a`). Leave blank for default standard names.
- **Server-Side GTM Transport URL:** Optional custom domain endpoint (e.g., `https://gtm.yourdomain.com`).
- **Google Consent Mode v2:** Enable or disable default denied consent setup.
- **Prefer SKU / Reference:** Choose whether `item_id` should prioritize product reference/SKU or product ID.
