# Changelog

All notable changes to the **Enterprise GA4 & sGTM DataLayer (`ps_gtm_datalayer`)** module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.4.4] - 2026-10-21

### Fixed
- **Add-to-Cart Product Identifier Isolation**: Completely eliminated identifier cross-talk between related product carousels (e.g. cross-sell of "Trink und Snack Tasse", ID 68) and simple products (e.g. Product 15, Product 1010). Cart updates are strictly scoped to the exact product and combination being added.
- **Variant ID Normalization**: Added strict sanitization across both client and server pipelines to ensure `variant_id` is populated only when a genuine combination exists (`id_product_attribute > 0`). Numeric zeroes (`"0"` or `0`) are normalized to empty string (`""`) for simple products.
- **Immediate Authoritative Cart Dispatch**: Cart items matched from the native PrestaShop `CartController` response are dispatched immediately without unnecessary secondary AJAX round-trips, ensuring zero lag.
- **Selling Price Discount Accuracy**: Verified and enforced exact backend gross discounted customer-facing selling prices (e.g., 50% catalog price rule on Product 68 yielding 12.99 € against 25.99 € base gross price).
- **Added Native Upgrade Script**: Added `upgrade/upgrade-1.4.4.php` for seamless in-place upgrades.

---

## [1.4.3] - 2026-10-21

### Added
- **Module Manager 1-Click Update**: Direct notice banner at the top of PrestaShop Module Manager (`AdminModulesManage`, `AdminModulesUpdates`) and an inline "Update to v1.4.3" button on the module card.
- **Native PrestaShop Upgrade Script**: Added `upgrade/upgrade-1.4.3.php` to automatically register `displayBackOfficeTop` and `displayBackOfficeHeader` hooks during in-place module upgrades.
- **Order Identifiers in `order_totals`**: Exposes both `order_id` (database auto-increment integer, e.g. `1221`) and `order_reference` (alphanumeric code, e.g. `HTAIWHXQL`) alongside complete financial breakdowns.
- **Real-Time Update Manifest Resolver**: `UpdateManager.php` queries the GitHub raw contents API directly, bypassing edge CDN cache delays for instantaneous update detection.

### Fixed
- **POSIX-Compliant ZIP Packaging**: Switched archive creation from PowerShell `Compress-Archive` (which used Windows `\` backslashes) to POSIX-compliant forward slashes (`/`), resolving the *"Module installation failed: Invalid zip format of the module file!"* error on Linux servers.
- **Database Gross Price Consistency**: Enforced authoritative backend Gross Price calculation (`Product::getPriceStatic($id, true, $id_attr, 2)`) across all catalog listings, eliminating pre-tax float mismatches.

---

## [1.4.2] - 2026-10-21

### Added
- **GA4-Compliant Item Revenue**: `ecommerce.value` in purchase events strictly equals the item subtotal (sum of item price × quantity, excluding shipping charges).
- **Dedicated `order_totals` Object**: Preserves complete PrestaShop back-office financial breakdown (`product_subtotal`, `shipping_total`, `tax_total`, `order_total`, and `currency`).

---

## [1.4.1] - 2026-10-21

### Changed
- **Plaintext Customer PII**: `user_data` now sends unhashed plaintext customer contact and address information (`email`, `first_name`, `last_name`, `street`, `city`, `postal_code`, `country`).

### Removed
- **`meta_external_id`**: Completely removed `meta_external_id` from all dataLayer payloads.
- **`UserDataHasher.php`**: Deprecated SHA-256 customer data hashing.

---

## [1.4.0] - 2026-10-21

### Added
- **Universal PrestaShop Backend Architecture (Zero DOM Dependency)**: Eliminated all `document.querySelector`, CSS selectors, and HTML scraping across the frontend adapter.
- **Native AJAX FrontController (`controllers/front/ajax.php`)**: Directly resolves product attributes, brand, category, SKU, and combinations from PrestaShop's SQL tables (`ps_product`, `ps_product_attribute`).
- **Server-Side Cart Hook (`actionCartUpdateQuantityBefore`)**: Captures cart actions in native PHP before response hydration.
- **Pre-Hydrated Combinations Map**: Generates `psGtmConfig.productCombinations` dictionary server-side for instantaneous attribute switches on PDP.

---

## [1.3.2] - 2026-10-21

### Fixed
- **Brand Persistence Across Funnel**: Resolved missing `item_brand` in checkout and purchase via `Manufacturer::getNameById()`.
- **PDP Combination Isolation**: Ensured combination switches update active combination state cleanly.

---

## [1.3.1] - 2026-10-20

### Added
- Initial combination variant handling and multi-combination tracking support.
