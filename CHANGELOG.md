# Changelog

All notable changes to the **Enterprise GA4, Meta, TikTok & Google Ads DataLayer (`ps_gtm_datalayer`)** module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.10.0] - 2026-10-22

### Added
- **Native Advertising Platform Namespaces (`meta`, `tiktok`, `google_ads`)**:
  - Automatically attaches catalog-matching namespaces to every ecommerce Data Layer event alongside GA4.
  - **Meta Pixel & CAPI**: `content_type` (`'product'` / `'product_group'`), `content_ids` (matching `retailer_id`), `contents[]` (`id`, `quantity`, `item_price`), `num_items`, `currency`, and `value`.
  - **TikTok Pixel & Events API**: `content_type`, `content_id`, `contents[]` (`content_id`, `content_type`, `content_name`, `quantity`, `price`), `currency`, and `value`.
  - **Google Ads Dynamic Remarketing (Retail Vertical)**: `ecomm_pagetype` (`'category'`, `'searchresults'`, `'product'`, `'cart'`, `'purchase'`), `ecomm_prodid` (matching Google Merchant Center `<g:id>`), `ecomm_totalvalue`, `ecomm_category`, and `items[]`.
- **PrestaShop Product Catalog Identifier Strategy**:
  - Configurable in Back Office under **Modules > Module Manager > Enterprise GA4, Meta, TikTok & Google Ads DataLayer > Configure**.
  - Supports `composite` (default: `{id_product}_{id_product_attribute}`), `id_product` (parent base ID), and `reference_sku` (physical supplier/catalog SKU).
- **Checkout Funnel Expansion**:
  - Added native observers for `add_shipping_info` (`shipping_tier`) and `add_payment_info` (`payment_type`).
  - Total supported ecommerce events expanded to 11.
- **In-Place Upgrade Migration**:
  - Added `upgrade/upgrade-1.10.0.php` to initialize settings, synchronize DB version, and clear Smarty caches automatically.

---

## [1.9.2] - 2026-10-21

### Enhanced & Hardened
- **Defensive Error Encapsulation**: Wrapped `hookDisplayHeader`, `hookDisplayOrderConfirmation`, and core pricing static calls in `try ... catch (\Throwable $e)` to guarantee the module can never trigger fatal crashes on storefront page loads.
- **Controller Method Guards**: Added defensive fallback checking for `registerJavascript` vs legacy `addJS` for non-standard or third-party controllers.
- **Path-Independent AJAX Controller**: Replaced hardcoded `_PS_MODULE_DIR_ . 'ps_gtm_datalayer'` with dynamic `dirname(__FILE__, 3)` in `controllers/front/ajax.php`.
- **Full AST Syntax Validation**: 100% verified syntax balance across all 26 PHP files, 2 JS files, JSON manifests, and XML templates.
- **Native In-Place Upgrade**: Seamless 1-click update via `upgrade/upgrade-1.9.2.php`.

---

## [1.9.1] - 2026-10-21

### Fixed
- **Critical Hotfix (`classes/PayloadBuilder.php`)**:
  - Removed duplicate return statement and closing brace in `buildPurchasePayload` that caused PHP fatal parse error on server execution.
  - Added automated syntax parser validation across all module PHP files.
  - Added in-place upgrade script `upgrade/upgrade-1.9.1.php`.

---

## [1.9.0] - 2026-10-21

### Added
- **Universal PrestaShop Backend Pricing & Financial Reconciliation**:
  - Direct integration with native PrestaShop core pricing engine (`Product::getPriceStatic(..., usereduc = true)`).
  - Eliminates intermediate gross rounding drift by evaluating discounts on the unrounded net base before applying taxes and currency precision ($25.20 \times 0.50 \times 1.19 = 14.994 \to \mathbf{14.99\ €}$).
  - Strictly reconciles `ecommerce.value = Σ(items[i].price * items[i].quantity)` across all funnel events (`view_item`, `view_item_list`, `add_to_cart`, `remove_from_cart`, `add_to_wishlist`, `begin_checkout`, `purchase`).
  - Purchases use stored, finalized order-line prices (`OrderDetail::$unit_price_tax_incl`) from the order record rather than recalculating against the live catalog.
  - Strict isolation of shipping and tax from `ecommerce.value`, while preserving clean custom `order_totals` (`order_id`, `order_reference`, `product_subtotal`, `shipping_total`, `tax_total`, `order_total`, `currency`).
  - Added native upgrade migration `upgrade/upgrade-1.9.0.php`.

---

## [1.8.3] - 2026-10-21

### Fixed
- **Price Calculation Parity (`PricingEngine.php`)**:
  - Direct integration with native PrestaShop core pricing engine (`Product::getPriceStatic(..., usereduc = true)`).
  - Eliminates intermediate gross rounding drift ($29.99 \times 0.50 = 14.995 \to 15.00$).
  - Correctly calculates tax on net discounted base ($25.20 \times 0.50 \times 1.19 = 14.994 \to 14.99\ €$), ensuring 100% exact parity with storefront Bruttopreis.

---

## [1.8.2] - 2026-10-21

### Fixed
- **`add_to_wishlist` Event Dispatch in Official `blockwishlist` & Themes**:
  - Fixed query string parameter extraction for PrestaShop official `blockwishlist` GraphQL/fetch calls (`params[id_product]` and `params%5Bid_product%5D`).
  - Added native integration with PrestaShop's official Vue `window.WishlistEventBus` (`addedToWishlist`).
  - Added `currentList` lookup in `resolveBackendProduct` for instant resolution on category and listing pages without waiting for AJAX.
  - Added fallback delegated click listener on wishlist triggers with 1,500ms debounce protection to support custom themes (Warehouse, Leo, Alysum, Transformer) while strictly preserving backend data resolution (Zero DOM).

---

## [1.8.1] - 2026-10-21

### Added
- **Universal Wishlist Integration Layer (`classes/WishlistIntegrationLayer.php`)**:
  - Reusable backend architectural layer separating wishlist detection, successful state validation, and canonical GA4 payload assembly.
  - Automatic detection of active wishlist modules (`blockwishlist`, `ps_wishlist`, `iqitwishlist`, `leowishlist`, `wtwishlist`).
- **Zero DOM Wishlist Tracking**:
  - Completely eliminated all DOM/CSS selector click listeners (`.wishlist-button`, `.btn-wishlist`, etc.).
  - Implemented strictly verified network response interception (XHR & Fetch API) checking HTTP 200 and backend `isSuccess` (`resp.success === true || resp.status === 'success' || !resp.hasError`).
  - Explicit URL filtering preventing false positives on wishlist deletions/removals (`indexOf('delete') === -1 && indexOf('remove') === -1`).
  - Integration with native PrestaShop event bus (`prestashop.on('addToWishlist')`, `prestashop.on('wishlist:add')`, `blockwishlist:added`).
- **Authoritative Backend Data Resolution**:
  - Direct resolution of base product ID, combination attribute ID, SKU, localized title, brand, default category, and gross customer price via `ProductIdentityResolver` and `PricingEngine`.
  - 1,200ms deduplication signature ledger preventing duplicate events.
- **Native In-Place Upgrade**: Added `upgrade/upgrade-1.8.1.php` for seamless 1-click update.

---

## [1.8.0] - 2026-10-21

### Added
- **GA4 `add_to_wishlist` Event Tracking**:
  - Implemented according to official Google Analytics 4 reference specifications (`currency`, `value`, `items`).
  - Multi-tier universal capture:
    - XMLHttpRequest & Fetch API interceptor for native PrestaShop wishlist modules (`blockwishlist`, `ps_wishlist`, custom theme wishlist controllers).
    - Event bus listeners for `prestashop.on('addToWishlist')` and custom document events (`blockwishlist:added`).
    - Delegated click listeners with 1,200ms debounce protection.
  - 100% backend product data resolution via `resolveBackendProduct()` (Zero DOM).
  - Added native upgrade migration `upgrade/upgrade-1.8.0.php`.

---

## [1.7.0] - 2026-10-21

### Added
- **GA4 `select_item` Event Implementation**:
  - Implemented according to official Google Analytics 4 reference specifications.
  - Automatically captures customer item selection from Category, Search, and Brand product listings.
  - Intercepts both direct navigation clicks (capture-phase pre-unload execution) and native PrestaShop Quick View modal triggers (`prestashop.on('clickQuickView')`).
  - Populates authoritative backend product parameters (`item_id`, `item_name`, `item_brand`, `item_category`, `item_variant`, `sku_id`, `price`, `quantity`, `index`, `item_list_id`, `item_list_name`).
  - Zero DOM scraping: correlates clicked item ID directly with server-hydrated `currentList` records.
  - Added native upgrade migration `upgrade/upgrade-1.7.0.php`.

---

## [1.6.1] - 2026-10-21

### Fixed
- **Category `view_item_list` Complete GA4 Schema**:
  - Added `ecommerce.currency` (`ISO 4217` code e.g. `'EUR'`) dynamically retrieved from PrestaShop context.
  - Added `ecommerce.value` calculating the total retail gross value of displayed category products.
  - Propagated `item_list_id` and `item_list_name` to both the event level and each individual item in `items[]` for granular item-scoped GA4 reporting.
  - Enhanced pagination to dynamically read `Tools::getValue('page', Tools::getValue('p', 1))` and dynamic per-page configuration (`PS_PRODUCTS_PER_PAGE`).
  - Added native upgrade migration `upgrade/upgrade-1.6.1.php`.

---

## [1.6.0] - 2026-10-21

### Added
- **Universal Cart Activity Tracking Engine (`EDL.processCartDelta`)**:
  - Pure mathematical before-and-after state delta engine ($\Delta Q = Q_{\text{new}} - Q_{\text{old}}$) completely independent of DOM selectors, themes, and frontend scrapers.
  - Accurate GA4 ecommerce event mapping:
    - Incremental additions & quantity increases map to `add_to_cart` with `quantity: ΔQ` and `value: sum(price * ΔQ)`.
    - Partial decrements & complete removals map to `remove_from_cart` with `quantity: |ΔQ|` and `value: sum(price * |ΔQ|)`.
    - Unchanged quantities ($\Delta Q = 0$) emit no events.
    - Full-cart clearing accurately emits `remove_from_cart` for every affected item and its previously held quantity.
  - Multi-item batch processing: simultaneously partitions mixed cart modifications into distinct `add_to_cart` and `remove_from_cart` payloads within the same operation.
  - In-memory Cart Product Metadata Cache (`cartProductsData`): preserves full product details (item_id, variant_id, sku_id, item_name, item_brand, item_category, item_variant, price) even after items are removed from `ps_cart_product` and omitted from the updated cart.
  - Server-side Failure & Concurrency Guard: detects `resp.hasError` and `resp.errors` to prevent false tracking events on failed or out-of-stock operations.
  - Pre-hydrated cart state in `ps_gtm_datalayer.php` and `datalayer_init.tpl`.
  - Comprehensive 10-scenario regression test suite covering single adds, quantity increments, decrements, removals, multi-item updates, combination items, empty cart, and error handling.

---

## [1.5.2] - 2026-10-21

### Changed
- **Numeric Order ID as `ecommerce.transaction_id`**: In `raihan_purchase` / `purchase` dataLayer events, `ecommerce.transaction_id` now directly captures the PrestaShop numeric Order ID (e.g., `"1225"`) instead of the alphanumeric Order Reference (`"CLLAKIJQI"`).
- **Audit Preservation**: The alphanumeric PrestaShop Order Reference is cleanly retained under `order_totals.order_reference` for back-office cross-referencing and reconciliation.
- **Native Upgrade Script**: Added `upgrade/upgrade-1.5.2.php` for seamless 1-click update in Module Manager.

---

## [1.5.1] - 2026-10-21

### Fixed
- **Universal Cart Quantity Resolution (Zero DOM)**: Captures multi-quantity additions (e.g. 2 units) via `CartController` HTTP POST parameters and real-time `ps_cart_product` delta math ($\Delta Q = Q_{\text{new}} - Q_{\text{old}}$), accurately pushing `quantity: 2` and calculated `ecommerce.value: 26.00 €` across all themes without touching the DOM.
- **Variant Object Sanitization (`[object Object]` fix)**: Integrated `EDL.formatVariant()` to parse PrestaShop `CartPresenter` attribute structures and combination dictionaries, completely eliminating `[object Object]` strings.
- **Category Name Consistency**: Aligned `item_category` with authoritative display names (`Baby Geschirr`) across all funnel events.
- **Resilient 1-Click Update**: Eliminated Symfony 500 error in Module Manager by routing updates through the authenticated `AdminModules` controller with CSRF token protection.
- **Native Upgrade Script**: Added `upgrade/upgrade-1.5.1.php` for seamless in-place updates.

---

## [1.5.0] - 2026-10-21

### Added
- **Universal Architecture - PlatformAdapter (`classes/PlatformAdapter.php`)**:
  - Dynamic platform version & capability detection (`isPrestaShop17()`, `isPrestaShop8()`, `isPrestaShop9OrHigher()`).
  - Dynamic currency precision resolver (`getCurrencyPrecision()`) handling PrestaShop 1.7.6+, 8.x, and legacy configurations.
  - Tax-inclusive vs. tax-exclusive customer group evaluator (`isTaxIncluded()`).
  - Automated `ps_module` database version table synchronizer (`syncModuleDatabaseVersion()`) and multi-layer cache flusher (`clearAllCaches()`).
- **Universal Architecture - PricingEngine (`classes/PricingEngine.php`)**:
  - Full separation of pricing concerns: dynamic customer price resolution with 6-decimal internal precision before applying percentage, amount, or reduction rules.
  - Universal commercial `ROUND_HALF_UP` arithmetic (`roundHalfUp()`) matching storefront retail display across all catalog rules, customer groups, and currencies.
- **Universal Architecture - CanonicalDataModel (`classes/CanonicalDataModel.php`)**:
  - Strict GA4 data schema validation and formatting for products, combinations, carts, and orders.
  - Dynamic cart and item total value calculator (`calculateItemsValue()`).
- **Universal Test Suite Expansion**: Added comprehensive automated tests for multi-currency precision (USD/EUR, JPY 0-decimal, BHD 3-decimal), complex multi-attribute apparel combinations (Size, Color, Material), brand-less fallbacks, and multi-store catalog pricing rules (18/18 passing).
- **Native Upgrade Script**: Added `upgrade/upgrade-1.5.0.php` for seamless in-place updates.

### Fixed
- **Universal Cart Delta Engine (Zero DOM Quantity Resolution)**: Replaced DOM scraping with a 100% backend-driven cart delta algorithm ($\Delta Q = Q_{\text{new}} - Q_{\text{old}}$) combined with native `CartController` JSON response attributes (`event.resp.quantity`), ensuring multi-quantity additions (e.g. 2 units) reliably push `quantity: 2` and accurate calculated `ecommerce.value` across all themes without touching the DOM.
- **Variant Object Sanitization (`[object Object]` fix)**: Added `EDL.formatVariant()` to parse PrestaShop `CartPresenter` attribute structures (arrays/associative objects) into canonical human-readable strings (e.g. `Farbe1: Stahlblau`) and resolved combinations from pre-hydrated database mappings, completely eliminating `[object Object]` leaks.
- **Category Name Consistency**: Standardized `item_category` to prioritize authoritative display category names (`Baby Geschirr`) across all funnel events over URL rewrite slugs (`baby-geschirr`).
- **1-Click Update 500 Error in Module Manager**: Resolved Symfony 500 Internal Server Error in PrestaShop 8 caused by update forms posting directly to the GET-only Symfony route (`/improve/modules/manage`). Routed update submissions safely through the authenticated `AdminModules` module controller with CSRF token validation.
- **Resilient Update Pipeline**: Wrapped all update actions, upgrade scripts, and cache flushing operations in `\Throwable` safety nets with filesystem error suppression to prevent server crashes under strict host environments.
- **Direct Package Download Option**: Added a direct "Download ZIP" action in the update notification banner for offline or upload-based upgrades.

### Changed
- Refactored `classes/PayloadBuilder.php` and `classes/ProductIdentityResolver.php` to delegate cleanly to `PricingEngine`, `CanonicalDataModel`, and `PlatformAdapter`.
- Updated client-side `datalayer-core.js` and `prestashop-adapter.js` to dynamically consume store currency precision from backend configuration.
- Zero store-specific logic: completely eliminated any store, product, or catalog assumptions across both backend and frontend layers.

---

## [1.4.5] - 2026-10-21

### Fixed
- **Customer-Facing Price Parity & Commercial Rounding (`ROUND_HALF_UP`)**: Aligned the data layer product price (`price` and `ecommerce.value`) with the exact displayed retail price on the storefront. Eliminated the premature intermediate net pre-tax truncation in `Product::getPriceStatic` ($10.92 \times 1.19 = 12.9948 \rightarrow 12.99$) and replaced it with customer-facing gross retail reduction math ($25.99 \times 0.50 = 12.995 \rightarrow 13.00$).
- **Dedicated Backend Price Resolver**: Implemented `ProductIdentityResolver::getCustomerPrice()` which dynamically checks active specific prices, promotional percentages, fixed discounts, and taxes with full 6-decimal precision before applying commercial half-up rounding.
- **Universal Dynamic Coverage**: Applies consistently across simple products, multi-variant combinations, category/search listings, cart events, and checkout.
- **Client-Side Epsilon Rounding**: Integrated `roundHalfUp()` with IEEE-754 epsilon protection in `datalayer-core.js` and `prestashop-adapter.js` to ensure absolute parity between frontend and backend.
- **Native Upgrade Script**: Added `upgrade/upgrade-1.4.5.php` to automatically update the `ps_module` database version table upon upgrade.

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
