# Changelog

All notable changes to the **Enterprise GA4, Meta, Pinterest, TikTok, Google, Microsoft, OpenAI & Snapchat Ads DataLayer (`ps_gtm_datalayer`)** module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.42.7] - 2026-10-28

### Changed & Cleaned
- **Clean sGTM Preview / Debug Header Field (`PS_SGTM_PREVIEW_HEADER`)**:
  - Removed example preview header string placeholder (`placeholder="..."`) from the Back Office configuration template (`views/templates/admin/configuration.tpl`).
  - Removed example token string argument from the setting helper description in `ps_gtm_datalayer.php`.
  - Ensures the input field is completely clean and empty by default, preventing any visual confusion between sample tokens and user-provided preview headers while preserving full guidance in the helper text.

---

## [1.42.6] - 2026-10-28

### Added & Fixed
- **Server GTM Debug / Preview Header Support (`PS_SGTM_PREVIEW_HEADER`)**:
  - Re-introduced the `PS_SGTM_PREVIEW_HEADER` configuration setting in the Back Office and HelperForm, allowing merchants to paste their `X-Gtm-Server-Preview` header token directly from GTM Server Container Preview mode (*Send requests manually*).
  - Automatically attaches `X-Gtm-Server-Preview` to all outgoing server-to-server HTTP webhook dispatches (`order_status_update`, `refund`, `purchase`, `test_ping`), ensuring server-side requests are claimed and rendered in real time within the Google Tag Manager Server Preview console.
- **Pre-Update State Capture Hook (`actionOrderStatusUpdate`)**:
  - Registered and hooked `actionOrderStatusUpdate` to capture authoritative `$order->current_state` in static memory right before status mutations are persisted to the database.
  - Guarantees `hookActionOrderStatusPostUpdate` reliably captures exact previous status IDs and status transition types (`payment_confirmed`, `refund_issued`, `order_canceled`, `status_changed_while_paid`), even when database transaction flushes or custom status history overrides are present.
- **Decoupled Server Webhook Gating**:
  - Decoupled `isEventEnabled('order_status_update')` and `isEventEnabled('refund')` from browser active event lists (`PS_GTM_ACTIVE_EVENTS`), ensuring that server-to-server webhooks are governed authoritatively by their dedicated configuration switches (`PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED`, `PS_GTM_WEBHOOK_REFUND_ENABLED`).
- **Live Test Order Status Webhook Dispatcher**:
  - Added an interactive "Test Order Status Webhook" button directly in the Back Office Server-Side Tracking section.
  - Sends a realistic test `order_status_update` payload with items, transaction ID, and active preview header, returning immediate visual verification of HTTP response code, latency, and endpoint delivery.
- **cURL Network Resilience**:
  - Extended cURL connect timeout (2000ms) and execution timeout (3500ms) to accommodate cloud server cold starts (Cloud Run, AWS ECS), with automatic fallback retry on SSL CA certificate bundle issues.

---

## [1.42.5] - 2026-10-27

### Added & Enhanced
- **Custom Section Icons**:
  - Integrated 10 dedicated high-resolution custom icon assets across 11 Back Office configuration sections and sub-cards:
    - `Google Tag Manager Web Container Settings` (`views/img/gtm_web_container.png`)
    - `Primary GTM Container ID` (`views/img/primary_gtm_id.png`)
    - `DataLayer Event Prefix (Optional)` (`views/img/datalayer_prefix.png`)
    - `Product Catalog Identifier Strategy` (`views/img/catalog_identifier_strategy.png`)
    - `Select Catalog Strategy` (`views/img/select_strategy.png`)
    - `Advertising Platform DataLayers & Event Routing` (`views/img/advertising_routing_icon.png`)
    - `Google Ads Conversions with Cart Data (Basket Reporting)` (`views/img/google_ads_cart_data.png`)
    - `Server-Side Tracking & Webhooks (sGTM)` (`views/img/serverside_tracking.png`)
    - `Advanced Custom Script Injection` (`views/img/script_injection.png`)
    - `Raw <head> HTML Snippet` and `Raw <body> HTML Snippet` (`views/img/html_snippet.png`)
- **Animated Save Settings Button Interaction**:
  - Upgraded "Save Settings" buttons across all configuration tabs to an interactive, state-driven animated component:
    - **Default State**: Save icon + "Save Settings".
    - **Saving State**: Button disabled to prevent duplicate submissions, loading spinner icon, label "Saving...".
    - **Success State**: Smooth background transition to emerald green (`#16a34a`), checkmark pop animation, label "Saved Successfully".
    - **Error State**: Smooth transition to crimson red (`#dc2626`), warning icon, label "Save Failed", preserving all user entered values for immediate retry.
    - **Return to Default**: Automatically transitions back to default state after 2.5s with fixed `min-width: 175px` to eliminate layout shift.
    - **Asynchronous AJAX Persistence**: Seamless background persistence via `fetch()` with graceful fallback to standard HTML POST.
    - **Accessibility & Motion Support**: `aria-busy` and `aria-live` screen-reader feedback with `prefers-reduced-motion` compliance.

---

## [1.42.4] - 2026-10-27

### Changed & Refined
- **OpenAI / ChatGPT Ads Event Precision**:
  - Restricted OpenAI `contents_viewed` standard event to fire strictly on `view_item` (Product Detail Page and variant combinations).
  - Re-mapped catalog discovery events `view_item_list` and `view_search_results` to standard OpenAI custom measurement actions (`command: 'measure'`, `event_name: 'custom'`, `data: { type: 'custom' }`, and `options: { custom_event_name: 'view_item_list' | 'view_search_results' }`), preventing false PDP view inflation while retaining granular discovery analytics.

---

## [1.42.3] - 2026-10-27

### Changed & Fixed
- **Module Display Name & Description Synchronization**:
  - Updated module runtime `displayName` in `ps_gtm_datalayer.php` to include Snapchat Ads: `"E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok, Google, Microsoft, OpenAI & Snapchat Ads"`.
  - Updated module runtime `description` in `ps_gtm_datalayer.php` to reflect Snapchat Ads catalog integration alongside Meta, Pinterest, TikTok, Google, Microsoft, and OpenAI ChatGPT Ads.
- **Brand Asset Refresh**:
  - Updated Snapchat brand icon with clean circular high-resolution asset across `views/img/snapchat_app.png` and `views/img/snapchat.png`.

---

## [1.42.2] - 2026-10-27

### Changed & Fixed
- **Custom Event Mapping for `view_cart`**:
  - **TikTok**: Mapped `view_cart` to `event_name: 'Custom'` with `custom_event_name: 'ViewCart'` (aligning with Meta and Pinterest custom action standards, rather than standard `ViewContent`).
  - **OpenAI Ads**: Mapped `view_cart` to `event_name: 'custom'`, `type: 'custom'`, `custom_event_name: 'view_cart'`.
  - **Snapchat Ads**: Mapped `view_cart` to `event_name: 'CUSTOM_EVENT_4'` with `custom_event_name: 'ViewCart'`, preserving full itemized cart line contents while isolating from single PDP `VIEW_CONTENT`.
- **Snapchat Brand Icon Cache Buster**:
  - Renamed icon reference to `snapchat_app.png?v={$module_version}` across Back Office table and group headers to permanently prevent browser HTTP disk caching of stale icon assets.

---

## [1.42.1] - 2026-10-27

### Changed
- **Back Office UI & Branding Refinement**:
  - Replaced Snapchat icon with user-provided custom icon in `views/img/snapchat.png`.
  - Removed `7/7` badge counter from the left navigation menu item (`Advertising Platforms`) and restored standard right chevron icon (`icon-chevron-right`) for clean, uniform alignment across all 5 configuration menu items.

---

## [1.42.0] - 2026-10-27

### Added
- **Snapchat Ads Pixel & Conversions API (CAPI) Integration**:
  - Native `snapchat` namespace emitted across all 12 canonical e-commerce events with zero DOM scraping and authoritative PrestaShop backend models.
  - **Dual Sub-Object Architecture**:
    - `snapchat.pixel`: Browser-focused parameters for client-side Snap Pixel (`price`, `currency`, `item_ids`, `number_items`, `item_category`, `transaction_id`, `search_string`, `description`).
    - `snapchat.dynamic_ads`: Official Conversions API (CAPI v3) Dynamic Ads parameters (`content_type`, `content_ids`, `contents` array with `id`/`quantity`/`item_price`/`brand`, `content_name`, `content_category`, `brands`, `value`, `currency`, `order_id`, `num_items`, `search_string`).
    - `snapchat.user_data`: SHA-256 hashed customer parameters (`em`, `ph` in E.164 without leading symbols, `fn`, `ln`, `ct`, `st`, `zp`, `country`) for maximum Snapchat Event Quality Score (EQS).
  - **7th Column in Advertising Platform Matrix Table**:
    - Seamlessly integrated as 7th platform column in the Back Office **Advertising Platform DataLayers & Event Routing** matrix with group toggles, individual event switches, and master sync (`PS_GTM_ADV_SNAPCHAT_ENABLED`, `PS_GTM_ADV_SNAPCHAT_EVENTS`).
    - Synchronized active platforms counter to display `X / 7`.
    - Added high-resolution official Snapchat brand logo icon (`views/img/snapchat.png`).
  - **State Isolation & Pre-Event Purge**:
    - Registered `snapchat: null` across all pre-event purge reset objects in `datalayer-core.js` and `datalayer_init.tpl` to eliminate cross-event state pollution in GTM.
  - **Automated Test Suite**:
    - Added `tests/snapchatAdsCatalogMatching.test.js` validating all 15 unit and integration scenarios across dual sub-object parameters, event mapping, catalog ID strategies (`composite`, `id_product`, `reference_sku`), selective event routing, and state isolation.
  - **Upgrade Routine**:
    - Added `upgrade/upgrade-1.42.0.php` with default configuration registration and automated cache flushing.

---

## [1.41.2] - 2026-10-27

### Changed
- **UI Simplification in Advertising Platform Tab**:
  - Removed redundant OpenAI / ChatGPT Ads informational subcard below the matrix table to keep the layout minimal, unified, and consistent with Meta, TikTok, Pinterest, and Microsoft Advertising.
  - All event routing configuration remains centralized in the matrix table above.

---

## [1.41.1] - 2026-10-27

### Fixed & Enhanced
- **Back Office Advertising Platform Routing Matrix UI**:
  - Integrated native **ChatGPT / OpenAI Ads** column header with brand logo into `views/templates/admin/configuration.tpl`.
  - Added category group toggle buttons (`psGtmToggleGroupPlatform(groupId, 'openai')`) across Product Discovery, Cart & Checkout, and Conversion sections.
  - Added individual event toggle switches (`adv_openai_{$e.name}`) across all 11 storefront events.
  - Added hidden state input `PS_GTM_ADV_OPENAI_ENABLED` to preserve platform master state on form submission.
- **Back Office JavaScript Architecture**:
  - Registered `openai` in `pKeys` and `platforms` arrays in `views/js/admin.js`.
  - Connected column header toggle (`psGtmTogglePlatformColumn('openai')`) and global bulk toggles (`psGtmToggleAllPlatforms(state)`).
  - Added label mapping for Schema Inspector drawer: `'OpenAI / ChatGPT Ads Measurement'`.
- **ChatGPT Ads Configuration Subcard**:
  - Added dedicated configuration subcard below the Event Routing matrix table detailing ISO 4217 minor unit conversion, SHA-256 Customer Advanced Matching, and GTM DataLayer variable implementation.
- **Admin Navigation Counter**:
  - Synchronized active advertising platforms badge to display `X / 6` (supporting Meta, Google Ads, TikTok, Pinterest, Microsoft, and OpenAI).

---

## [1.41.0] - 2026-10-27

### Added
- **OpenAI / ChatGPT Ads DataLayer Integration**:
  - Introduced dedicated `openai_ads` namespace across storefront dataLayer events.
  - Generates standard OpenAI measurement commands `oaiq('measure', '<event_name>', { ... })` for standard events (`contents_viewed`, `items_added`, `checkout_started`, `order_created`) and custom events (`custom`).
  - **Universal ISO 4217 Minor Currency Unit Engine**: Converts monetary values to exact integer minor units for all currencies worldwide (0-decimal JPY/KRW/VND, 3-decimal KWD/BHD/OMR, 4-decimal CLF, and standard 2-decimal USD/EUR/GBP/BDT).
  - **Advanced Customer Matching**: Standardized SHA-256 encrypted fields (`email_sha256`, `phone_number_sha256`, `first_name_sha256`, `last_name_sha256`, `external_id_sha256`) and unhashed address parameters (`country`, `city`, `region`, `postal_code`) for OpenAI Ads measurement.
  - Strictly omits `event_id` per measurement specification.
  - Full Back Office integration in **Advertising Platform DataLayers & Event Routing** matrix with master switch, platform toggle, event routing matrix, and DataLayer purge reset (`openai_ads: null`).
  - Added official OpenAI brand logo icon at `views/img/openai.png`.
  - Added comprehensive test suite `tests/openaiAdsCatalogMatching.test.js` covering 23 unit and integration scenarios.

---

## [1.40.10] - 2026-10-26

### Security & Reliability
- **100% Defensive Hook & Lifecycle Encapsulation**:
  - Enclosed all 10 registered storefront and Back Office hooks with defensive `try ... catch (\Throwable $e)` wrapping, specifically protecting `hookActionCartUpdateQuantityBefore`, `queuePendingEvent`, `hookDisplayAfterBodyOpeningTag`, `hookDisplayBackOfficeTop`, and `hookDisplayBackOfficeHeader`.
  - Guaranteed that any unexpected edge-case exception during cart quantity manipulation, cookie serialization, or asset enqueuing will never interrupt core store checkout or admin management.
- **Production Readiness Audit Verified**:
  - Successfully verified all 10 audit pillars: zero DOM scraping, authoritative backend data hydration, full PrestaShop 1.7.0 – 8.2+ compatibility, 11 storefront event lifecycles, 2 server-side webhook pipelines, catalog ID strategy parity, and non-blocking sGTM transport.

---

## [1.40.9] - 2026-10-26

### Added
- **Dedicated Custom Ecommerce Event Icons**:
  - Integrated tailored, high-resolution PNG brand icons across all 11 supported ecommerce events (`view_item_list`, `view_search_results`, `view_item`, `add_to_wishlist`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`).
  - Implemented responsive image rendering (`20x20px`) inside thematic pastel badge containers with automatic error fallback to legacy font-icons (`onerror` safety handler).
  - Enhanced the interactive **Inspect Drawer** header icon box to dynamically render the corresponding high-resolution event graphic.

---

## [1.40.8] - 2026-10-26

### Fixed
- **Group Header Titles Typography & Localization**:
  - Fixed HTML entity double escaping on the Cart & Checkout event group header (`Cart &amp; Checkout` -> `Cart & Checkout`) using defensive Smarty normalization.
  - Standardized marketing event group headers (`Product Discovery`, `Cart & Checkout`, `Conversion`) to prevent collision with PrestaShop core translation dictionaries that previously translated "Conversion" to the German currency conversion term "Umwandlung".

---

## [1.40.7] - 2026-10-26

### Fixed
- **Storefront DataLayer Push Restoration**:
  - Removed an obsolete `<input type="hidden" name="PS_GTM_EVENTS_SUBMITTED" value="1">` tag from the unified settings form that previously caused the backend to overwrite `PS_GTM_ACTIVE_EVENTS` with an empty array upon saving module configuration.
  - Hardened `postProcess()` so that canonical event settings are only updated if explicit canonical event toggles are present in the HTTP POST request.
- **Fail-Safe Autonomous Self-Healing**:
  - Updated `isEventEnabled()` to safely return `true` whenever `PS_GTM_ACTIVE_EVENTS` is empty, corrupted, or evaluates to an empty array, guaranteeing that all canonical storefront events (`view_item`, `view_item_list`, `view_search_results`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `refund`, `order_status_update`) are always enabled by default.
  - Added self-healing sweeps in `getContent()` and `hookDisplayHeader()` to automatically detect and purge any corrupted `[]` records from the configuration table.
  - Created database cleanup in `upgrade-1.40.7.php` with automatic cache flushing.

---

## [1.40.6] - 2026-10-26

### Added
- **Modern Grouped Event Routing & Advertising Interface**:
  - Re-designed the Advertising Platform DataLayers & Event Routing view (Tab 3) into 3 collapsible, colored event group cards matching enterprise design standards:
    - **Product Discovery** (`view_item_list`, `view_search_results`, `view_item`) with soft blue theme (`#f0f7ff`).
    - **Cart & Checkout** (`add_to_wishlist`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`) with soft purple theme (`#f5f3ff`).
    - **Conversion** (`purchase`) with soft emerald theme (`#f0fdf4`).
  - Added dedicated row selection checkboxes that toggle all 5 advertising platform destinations for that individual event with tri-state indeterminate visual feedback.
  - Added quick-toggle column headers in each group to enable or disable individual advertising destinations (Meta, Google Ads, TikTok, Pinterest, Microsoft Ads) per group with a single click.
  - Integrated View Switcher toolbar supporting both **By Event Group** accordion mode and **Full List** mode without DOM duplication.
  - Added bulk action controls (**Enable All** / **Disable All**) and modern footer bar featuring live active event counters (`11 events`), dynamic unsaved changes indicators, Reset button, and primary Save button.
  - Restored full interactive slide-out **Inspect Drawer** accessed directly via the `(i)` info button on every event row, displaying internal identifiers, PrestaShop trigger hooks, destination routing states, and comprehensive DataLayer schemas.

---

## [1.40.5] - 2026-10-26

### Fixed
- **Advertising Matrix Table Visibility & Layout**:
  - Re-engineered the cross-platform event routing table with responsive column sizing (`25%` event column, `15%` per platform) and compact header controls, ensuring **Microsoft Ads** is 100% visible and accessible alongside Meta, Google Ads, TikTok, and Pinterest.
  - Added horizontal scroll wrapping (`overflow-x: auto`) on `#ps-gtm-routing-matrix-view` to guarantee that no advertising platform is ever clipped on lower-resolution screens or narrower Back Office viewports.
  - Aligned table headers and data cells with consistent padding and centered toggle alignment.

---

## [1.40.4] - 2026-10-26

### Removed
- **`page_view` and `select_item` UI References**:
  - Removed deprecated `page_view` and `select_item` entries from the Back Office canonical event catalog and advertising platform matrix.
  - Aligned canonical event lists across `getCanonicalEventsCatalog()` and `getSupportedEvents()`.

### Fixed
- **Advertising Platform Event Routing Persistence**:
  - Resolved duplicate input name collisions between Matrix View (`#ps-gtm-routing-matrix-view`) and Platform Cards View (`#ps-gtm-routing-cards-view`) that previously caused unchecked event states to be overwritten by hidden card inputs upon form submission.
  - Added live two-way synchronization in `views/js/admin.js` for all `adv_` and `PS_GTM_ADV_` inputs so both views remain in 100% lockstep.
  - Added submit-time form deduplication in `admin.js` to disable duplicate inputs in the inactive routing view, ensuring the browser sends a single authoritative state for each setting.
  - Added dedicated platform master toggle switches directly to the Matrix View column headers for Meta, Google Ads, TikTok, Pinterest, and Microsoft Ads.
  - Removed the `required` attribute from the `PS_GTM_CONTAINER_ID` field in Tab 1 to eliminate silent browser validation halts when saving from tabs 2, 3, 4, or 5.
  - Added hidden `PS_GTM_CORE_SUBMITTED`, `PS_GTM_ADV_SUBMITTED`, and `PS_GTM_EVENTS_SUBMITTED` inputs to ensure all configuration sections persist reliably regardless of active tab.

---

## [1.40.3] - 2026-10-26

### Added
- **Custom Configuration Menu Brand Icons**:
  - Replaced generic monochrome font icons in the left sidebar menu with tailored visual brand icons:
    - **GTM Web Container**: Google Tag Manager geometric emblem.
    - **Catalog Strategy**: Product item and catalog interface illustration.
    - **Advertising Platforms**: Digital marketing broadcast and megaphone card.
    - **Server-Side & Webhooks**: Data server cluster stack illustration.
    - **Custom Scripts**: Browser code injection icon.
  - Enhanced `.ps-gtm-nav-icon` styling with crisp 38x38px rounded badges, soft category-themed backgrounds, borders, and tactile active hover elevation.

---

## [1.40.2] - 2026-10-26

### Fixed
- **Back Office Configuration Save Persistence**:
  - Restored missing admin security token in `form_action` URL and hidden `<input type="hidden" name="token">` to prevent silent CSRF drops by PrestaShop's `AdminController::postProcess()`.
  - Updated submit detection in `getContent()` to recognize `submitps_gtm_datalayer` and `submit` action parameters reliably.
  - Fixed submit event listener in `views/js/admin.js` to preserve form input values during submission without prematurely setting `disabled = true` on the submit button before payload dispatch.
- **Advertising Platform Matrix Headers**:
  - Redesigned the Matrix View table headers with elevated platform header cards, consistent 18px icons, bold platform titles, and streamlined dual-action toggle pills (`All` / `None`).
- **Enterprise Save Button Styling**:
  - Replaced inconsistent buttons with a standardized, elevated primary button style (`.ps-gtm-btn-save-primary`) featuring a solid blue theme (`#2563eb`), clear icon alignment, tactile hover elevation, and an interactive submission loading spinner.

---

## [1.40.1] - 2026-10-26

### Fixed
- **Professional 2-Column Grid & Elevated Cards Design**:
  - Replaced custom flexbox wrappers with PrestaShop native Bootstrap grid (`.row`, `.col-lg-3`, `.col-lg-9`), ensuring guaranteed side-by-side rendering regardless of browser cache states.
  - Enclosed all settings inside distinct, elevated `.ps-gtm-card` and `.ps-gtm-subcard` containers with crisp borders, pastel icon badges, readable typography, and dedicated card save buttons.
  - Separated **Product Catalog Strategy** into its own dedicated menu tab on the left sidebar featuring prominent 3-card interactive selection.
  - Added embedded layout styles inside the admin template and version query strings (`?v=1.40.1`) on all asset enqueues to prevent stale CSS caching.

---

## [1.40.0] - 2026-10-26

### Changed
- **Streamlined Left-Sidebar Panel Navigation Architecture**:
  - Replaced top horizontal tabs with a dedicated, responsive left-side navigation sidebar panel.
  - Divided core module settings into 4 dedicated panels:
    - **GTM & DataLayer Settings**: Web Container ID, Event Prefix, and interactive 3-card Product Catalog Identifier Strategy.
    - **Platform Routing**: Advertising Master Switch, Display Mode (Matrix View / Platform Cards), bulk switches, and Meta/Google Ads/TikTok/Pinterest/Microsoft Ads event routing.
    - **Server-Side Tracking & Webhooks**: First-party sGTM Transport URL, Webhook Path, live connection ping diagnostic test, and Server-to-Server Refund, Offline Order, and Order Status Change webhooks.
    - **Custom Scripts**: Direct `<head>` and `<body>` script injection without theme template overrides.
  - Added dedicated save controls inside each panel footer in addition to the global floating unsaved changes banner.
  - Cleaned up interface by removing Overview, Events Catalog, Data Mapping, and Diagnostics & Testing views as requested.

---

## [1.39.1] - 2026-10-25

### Fixed
- **PHP 8 / Smarty Fatal TypeError Hotfix**:
  - Resolved Back Office HTTP 500 Internal Server Error caused by `{$e.active_dest_names|implode:', '}` in `views/templates/admin/configuration.tpl`. In PHP 8.0+, invoking PHP's `implode` via Smarty modifier syntax passes array as first argument (`implode($array, $glue)`), triggering a fatal `TypeError` in Symfony/PrestaShop.
  - Pre-formatted active destination names into server-side string properties (`active_dest_names_str`) in `ps_gtm_datalayer.php` (`renderAdminTabs()`), eliminating all runtime template array transformation modifiers.
- **Defensive Back Office Exception Guard**:
  - Wrapped `getContent()` and `renderAdminTabs()` in comprehensive `try ... catch (\Throwable $e)` exception boundaries to intercept and gracefully log any unexpected rendering errors, preventing any 500 white-screen or Symfony crash in PrestaShop Back Office.

---

## [1.39.0] - 2026-10-25

### Added
- **Redesigned Events Management Interface**:
  - Implemented clean, modern table layout with reorganized columns: `STATUS`, `EVENT`, `CATEGORY`, `DESCRIPTION`, `DESTINATIONS`, and `ACTIONS`.
  - Added dedicated event category badges (`Discovery`, `Interaction`, `Cart`, `Checkout`, `Orders & Returns`) with distinct color themes.
  - Added event-specific category icons (`icon-eye`, `icon-th-list`, `icon-search`, `icon-hand-pointer-o`, `icon-cube`, `icon-heart`, `icon-cart-plus`, `icon-trash`, `icon-shopping-cart`, `icon-sign-in`, `icon-truck`, `icon-credit-card`, `icon-check-square-o`, `icon-undo`, `icon-refresh`).
  - Added compact, readable Destination Summary pills (e.g. `5 destinations`) showing active destination count with detailed hover tooltips listing all enabled platforms.
  - Streamlined table rows by moving technical hook execution paths and zero-DOM event bus details into the Inspect drawer, eliminating visual clutter.
- **Slide-Out Inspect Drawer**:
  - Replaced legacy modal dialog with a modern slide-out drawer matching user design specifications.
  - Live status indicator reflecting real-time enabled/disabled event state.
  - Internal event name card with configured event prefix and one-click copy button.
  - Authoritative event metadata card including Category, Trigger description, Source hook/event bus, and dependencies.
  - Dynamic Destination Routing manager with live platform status badges:
    - `Active` (Platform enabled and event routed)
    - `Inactive` (Platform enabled but event unrouted)
    - `Disabled Globally` (Platform disabled globally in Advertising tab)
    - `Not Supported` (Event unsupported by platform)
  - Interactive quick-toggle switches inside drawer that sync directly with the configuration form and update table destination counts in real time.
  - Complete Data Parameters specification table (`Parameter`, `Type`, `Source`, `Purpose / Mapping`).
  - Collapsible accordions for:
    - Platform-specific formatted tracking code snippets (`fbq`, `gtag`, `ttq`, `pintrk`, `uetq`).
    - Trigger, zero-DOM event bus, and deduplication specifications.
  - Verified DataLayer JSON payload preview with one-click copy button.
- **Enhanced Filter Toolbar & Bulk Actions**:
  - Replaced ambiguous icon buttons with clearly labeled, dedicated bulk action buttons (`[Enable All]` and `[Disable All]`) separated from search filters.
  - Added live Status dropdown filter (`All Statuses`, `Enabled Only`, `Disabled Only`).
- **Unsaved Changes Tracking & Save State Feedback**:
  - Added floating notification banner warning of unsaved modifications when any setting or switch is changed.
  - Added animated progress indicator on save button (`Saving All Settings...`) with double-submit prevention.
- **Backend-Driven Event Active State Persistence**:
  - Added `PS_GTM_ACTIVE_EVENTS` configuration key and `isEventEnabled()` helper method.
  - Ensures event suppression across storefront hooks, order confirmations, and server-side webhooks when disabled by the administrator.

---

## [1.38.0] - 2026-10-25

### Changed
- **Module Title & Branding**:
  - Standardized official displayed module title to: `E-Commerce DataLayer: GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads`.
  - Fixed HTML entity escaping in Back Office templates so the ampersand character (`&`) renders cleanly as a literal `&` rather than the visible HTML entity `&amp;`.
  - Updated module update notices, configuration banner titles, and Module Manager DOM selectors across PrestaShop 1.7, 8, and 9.

### Added
- **Global Typography & Readability Overhaul**:
  - Implemented comprehensive typographic scale throughout the entire Back Office interface:
    - Main page title increased to 28px bold (700) with high-contrast subtitle (15px).
    - Section and card headings boosted to 20–22px bold (700).
    - Subsection and platform headers styled at 17–18px semibold (600).
    - Form labels and field names enlarged to 15.5px semibold (600) with dark slate `#0f172a`.
    - Input controls, textareas, and select dropdowns scaled to 15px with generous padding and 46px touch target height.
    - Event names and platform names prominent at 16–17px bold.
    - Main body text, help descriptions, and instructions set to 14–15px in high-contrast slate `#475569` / `#334155` (zero washed-out gray).
    - Primary save buttons and action triggers upgraded to 15–16px bold with clear visual prominence.
    - Status badges, category pills, and platform tags increased to 13–14px semibold.
  - Eliminated text smaller than 13px across all configuration tabs, tables, modals, and metric tiles.

---

## [1.37.1] - 2026-10-25

### Fixed
- **Product Catalog Identifier Strategy UI**:
  - Restored full interactive 3-card comparison cards for all Product Catalog Identifier strategies under the Configuration tab dropdown.
  - Re-introduced strategy badges (*Recommended*, *Parent Only*, *ERP / Barcode*), concise "When to use" descriptions, ID format formulas, and expandable drawers displaying live PrestaShop Back Office ID mapping examples for simple products vs combinations.
  - Ensured seamless bidirectional synchronization between cards and the native `<select name="PS_GTM_CATALOG_ID_STRATEGY">` setting.

---

## [1.37.0] - 2026-10-25

### Added
- **Unified 6-Tab Back Office UI/UX Architecture**:
  - **Overview**: Executive tracking health summary, active channel metrics, canonical event counters, and one-click quick navigator.
  - **Configuration**: Streamlined card-based technical settings grouping GTM & DataLayer Core, Server-Side Tracking & Webhooks (sGTM), and Advanced Script Injection.
  - **Events Manager**: Dedicated catalog table with real-time search, category filter pills (Discovery, Interaction, Cart, Checkout, Orders & Returns), status filters, and one-click schema inspection.
  - **Platform Routing Matrix**: Cross-grid matrix view (Events rows × Platforms columns) with column-level toggle-all controls and dual-view switcher for detailed platform accordions with Google Ads Cart Data and Merchant ID configuration.
  - **Data Mapping Inspector**: Authoritative parameter mapping inspector displaying source GA4 fields versus mapped parameters across Meta Pixel/CAPI, Google Ads, TikTok, Pinterest, and Microsoft Advertising (Bing Ads), accompanied by copyable verified JSON payloads.
  - **Diagnostics & Testing**: Integrated health audit and real-time Server-Side GTM transport URL ping test executing non-blocking diagnostic probes directly from the server with response latency measurement.

---

## [1.36.0] - 2026-10-25

### Fixed
- **Initial Checkout Order Creation Guard**:
  - Suppressed premature `order_status_update` webhook dispatch during customer online order placement.
  - Added strict transition guard (`$previousStatusId <= 0 || $previousStatusId === $newOrderStatus->id`), ensuring webhooks fire strictly when an existing order's status is legitimately changed or updated to a different status in the Back Office.

---

## [1.35.0] - 2026-10-25

### Fixed
- **Accurate Previous Order Status Resolution**:
  - Fixed previous status detection in `actionOrderStatusPostUpdate` hook by inspecting `$params['oldOrderStatus']` and querying historical `ps_order_history` logs, resolving `previous_status_id: 0`.
- **Ironclad Conversion Retraction for Refunds and Cancellations**:
  - Overridden status transition classification so moving to **Refunded** (Status 7, 20, 26, or "Refunded" / "Erstattet") or **Canceled** (Status 6, or "Canceled" / "Storniert") ALWAYS outputs `conversion_action: "retract"`.
  - Classifies `status_transition_type` as `refund_issued` or `order_canceled`, enabling automated offline conversion reversals in Google Ads, Meta CAPI, and GA4 without ambiguity.

---

## [1.34.0] - 2026-10-25

### Changed
- **Streamlined Server-Side Webhook Architecture**:
  - Removed redundant `PS_SGTM_GA4_MEASUREMENT_ID` and `PS_SGTM_PREVIEW_HEADER` inputs from Back Office configuration.
  - Standardized all server-to-server webhook dispatches (`refund`, `purchase`, `order_status_update`) on pure, canonical JSON payloads (`application/json`).
  - Seamlessly routes to `/ps-webhook` (or custom endpoint path) for consumption by the dedicated `PrestaShop Webhook Client` template or Stape Data Client.
  - Preview debugging is managed cleanly via Stape's native Preview Header Power-Up or client routing without bloating store settings.

---

## [1.33.0] - 2026-10-25

### Added
- **Dedicated PrestaShop Webhook Client for Server GTM (`prestashop-client-template.tpl`)**:
  - Purpose-built sandboxed sGTM Client template that claims `/ps-webhook` (and `/prestashop/webhook`).
  - Natively maps PrestaShop webhooks (`order_status_update`, `purchase`, `refund`) directly into Google Tag Manager **Common Event Data** (`event_name`, `transaction_id`, `items`, `user_data`, `status_transition_type`, `conversion_action`, `is_paid`, etc.).
  - Bypasses GA4 Client collisions and Stape Data Client formatting restrictions for sub-second real-time event evaluation.
- **Configurable Webhook Endpoint Path (`PS_SGTM_WEBHOOK_ENDPOINT_PATH`)**:
  - Added dedicated Back Office input to route webhooks to any path (defaults to `/ps-webhook`, or `/data` for Stape Data Client).
  - Automatically respects full path URLs entered in `PS_SGTM_TRANSPORT_URL`.
- **Dual Flat & Envelope JSON Payloads**:
  - Enriches outgoing JSON payloads with top-level `event_name`, `client_id`, and parameters while retaining nested `events` array for universal sGTM client compatibility.

---

## [1.32.0] - 2026-10-25

### Added
- **Native sGTM GA4 Client Compatibility (`PS_SGTM_GA4_MEASUREMENT_ID`)**:
  - Automatically encodes server-to-server webhook payloads (`refund`, `purchase`, `order_status_update`) into canonical GA4 beacon format (`v=2&tid=...&cid=...&en=...&pr1=...&ep.*`) when a GA4 Measurement ID is configured.
  - Guarantees immediate `HTTP 200 OK` acceptance by Google's native sGTM GA4 Client without requiring custom Data Clients or template installations.
- **Server GTM Debug / Preview Header Injection (`PS_SGTM_PREVIEW_HEADER`)**:
  - Added dedicated Back Office input to paste the `X-Gtm-Server-Preview` token from sGTM Debug window.
  - Automatically attaches the header to all outgoing webhook cURL requests, allowing real-time inspection in the GTM Server Preview UI.
- **Automatic Self-Healing Hook Registration**:
  - Module constructor and Back Office configuration view automatically verify and register missing server hooks (`actionOrderStatusPostUpdate`, `actionOrderSlipAdd`, `actionValidateOrder`) in `ps_hook_module`.
- **PrestaShop System Logger Integration**:
  - Logs all webhook dispatches with event name, endpoint URL, HTTP response code, and cURL errors into PrestaShop's core log (`Advanced Parameters > Logs`) for effortless troubleshooting.

---

## [1.31.0] - 2026-10-25

### Added
- **Self-Healing Payment Revocation & Mistake Reversal Detection**:
  - Automatically detects when a Back Office administrator mistakenly marks an order as paid and subsequently reverts it to an unpaid or canceled state.
  - Compares `$previousOrderState->paid` with `$newOrderStatus->paid` to classify transitions:
    - `payment_confirmed` (`conversion_action: "record"`) when unpaid &rarr; paid.
    - `payment_revoked` (`conversion_action: "retract"`) when paid &rarr; unpaid/canceled.
    - `status_changed_while_paid` (`conversion_action: "maintain"`).
    - `status_changed_unpaid` (`conversion_action: "none"`).
  - Supplies `conversion_action` and `status_transition_type` in the Server GTM payload, allowing Google Ads to execute Conversion Retractions and GA4 to issue refunds automatically for human errors.
  - Added unit test cases 10 and 11 in `tests/serverSideWebhook.test.js`.

---

## [1.30.0] - 2026-10-25

### Added
- **Universal Server-to-Server Order & Payment Status Change Webhooks (`actionOrderStatusPostUpdate`)**:
  - Automatically captures manual and asynchronous order status transitions in the PrestaShop Back Office (e.g. Bank Wire `#10` to Payment Accepted `#2`, Mirakl `#11`, Shipped `#4`, Delivered `#5`).
  - Dispatches canonical `order_status_update` payloads to Server GTM (`/g/collect?v=2`) carrying `new_status_id`, localized `new_status_name`, native `is_paid` and `is_shipped` booleans, order financials, and items.
  - Normalizes customer PII into SHA-256 hashes for Meta CAPI and Google Ads Offline Conversion Value Adjustments.
  - Implemented 100% universal compatibility across all PrestaShop versions (1.7 through 8.x / 9.x) using core `$orderState->paid` properties with zero hardcoded status strings or IDs.
  - Added dedicated Back Office toggle `PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED` with interactive "Learn more" drawer.
  - Added unit test cases 8 and 9 to `tests/serverSideWebhook.test.js`.

---

## [1.29.0] - 2026-10-25

### Added
- **Server-to-Server Refund & Offline Order Webhooks to Server GTM (sGTM)**:
  - Intercepts Back Office credit slip generation (`actionOrderSlipAdd`) to dispatch asynchronous server-to-server `refund` conversion events to Server-Side GTM (`/g/collect`), Stape.io, or custom tagging endpoints.
  - Intercepts offline/Back Office order creation (`actionValidateOrder`) to dispatch server-to-server `purchase` events.
  - Implemented `RefundDataResolver` extracting authoritative order, credit slip, variant combination items, prices, and tax directly from PrestaShop core models (Zero DOM).
  - Implemented `ServerSideWebhookDispatcher` with non-blocking cURL HTTP POST (1500ms max timeout) and defensive error logging.
  - Implemented SHA-256 customer PII normalization (email, phone, address) for Google Enhanced Conversions and Meta CAPI.
  - Implemented strict idempotency deduplication with new `ps_gtm_refund_log` database table.
  - Added dedicated Back Office switches for refund and offline order webhooks with accessible "Learn more" drawers.
  - Created automated test suite `tests/serverSideWebhook.test.js` validating GA4/sGTM payload formatting and catalog strategy alignment.

---

## [1.28.5] - 2026-10-25

### Removed
- **Removed Google Consent Mode v2 Switch and Template Injection**:
  - Completely removed the "Enable Google Consent Mode v2" toggle and its configuration from the Back Office Core Settings form.
  - Removed database configuration handling for `PS_GTM_CONSENT_MODE` in installation, uninstallation, form submission, and upgrade routines (`upgrade-1.28.5.php`).
  - Removed default denied consent script tag injection from `gtm_head.tpl` to allow dedicated Consent Management Platforms (CMPs) or GTM to handle consent without conflicts or race conditions.

---

## [1.28.4] - 2026-10-25

### Changed
- **Refined Strategy Cards Learn More Explanations**:
  - Updated Option 1 (Composite Database ID) explanation with exact criteria for variant-level feed rows and Back Office composite format examples (`68_299` vs `15`).
  - Updated Option 2 (Base Product ID) explanation with parent-only catalog matching guidance (`68` vs `15`).
  - Updated Option 3 (Reference / SKU) explanation with ERP/warehouse inventory feed guidance (`BHK125-B` with clean database ID fallback).

---

## [1.28.3] - 2026-10-25

### Added
- **Modern Settings Cards UI/UX & Interactive Learn More Drawers**:
  - Implemented 5-tier card structure (Setting title, Short description, Setting control, Learn more interactive control, Expanded explanation drawer) across all Core Settings and Product Catalog Identifier Strategy cards.
  - Interactive "Learn more" pill controls revealing rich guidance: What it does, Why it matters, Tracking behavior, When to use, and practical PrestaShop Back Office ID format examples.
  - Fully accessible keyboard navigation (`Tab`, `Space`, `Enter`) with proper `aria-expanded`, `aria-controls`, and `role="region"` attributes.
  - Clean visual states: subtle `#f8fafc` explanation drawer backgrounds, emerald green active selection borders, smooth slide animations, and zero layout shift.

---

## [1.28.2] - 2026-10-25

### Fixed
- **Product Catalog Identifier Strategy Description Visibility**:
  - Isolated strategy card body CSS class by renaming to `.ps-gtm-strategy-body` with explicit `display: block !important;` to eliminate unintended CSS override collision with `.ps-gtm-card-body` from advertising accordion panels.
  - Ensured all three strategy card descriptions, "When to use" guidance, and Back Office format examples render immediately visible on PrestaShop Back Office configuration page.

---

## [1.28.1] - 2026-10-25

### Changed
- **Enhanced Product Catalog Identifier Strategy UI**:
  - Full-width, high-contrast select dropdown styling preventing text truncation (`max-width: 720px`).
  - Redesigned 3 interactive strategy cards with permanent, always-visible descriptions and practical examples.
  - Included real PrestaShop Back Office ID format specifications and examples for combinations (Product #68 + Variant #299) vs simple products (Product #15).

---

## [1.28.0] - 2026-10-24

### Changed
- **Universal Theme Independence & Zero DOM Scraping**:
  - Removed all fragile DOM click listeners and class queries (`.wishlist-button`, `.btn-wishlist`, `.remove-from-cart`, `a[href*="delete=1"]`, `input.js-cart-line-product-quantity`, `input[name="s"]`).
  - Switched 100% of cart add/remove tracking to authoritative PrestaShop application models via the Universal Cart Delta Engine (`EDL.processCartDelta`) and core network response interception.
  - Omitted fragile listing click scraping (`select_item`) in favor of clean catalog separation (`view_item_list` and `view_item`).
  - Decoupled wishlist tracking from CSS classes to native event bus (`prestashop.on('addToWishlist')`) and official `blockwishlist` network interceptor.
  - Decoupled payment method and search discovery from DOM labels and inputs to backend-hydrated maps and server query inputs.

---

## [1.27.0] - 2026-10-24

### Changed
- **Classic PrestaShop UI Architecture (v1.21.3 Design)**:
  - Preserved the clean, native PrestaShop Back Office interface architecture without custom SaaS dashboard layout or overflowing titles.
  - Guaranteed 100% hook method integrity across all storefront lifecycles.

### Added
- **Raw GTM Container Script Injection**:
  - Added dedicated Back Office textarea inputs for raw GTM `<head>` script and raw GTM `<body>` (noscript) container code.
  - Automatically injected into storefront `<head>` and opening `<body>` tags across all pages.
- **Enhanced Product Catalog Identifier Strategy**:
  - Organized dropdown selector with full width (`max-width: 650px; height: 38px`).
  - Added permanent guidance cards with clear "When to use" descriptions and real-world store examples for Option 1 (Composite ID), Option 2 (Base Product ID), and Option 3 (Reference / SKU).

---

## [1.21.3] - 2026-10-24

### Fixed
- **TikTok Ads DataLayer Activation & Configuration Healing**:
  - Restored and healed `PS_GTM_ADV_TIKTOK_ENABLED` to active state in database configuration via upgrade migration script `upgrade-1.21.3.php`.
  - Re-hydrated and verified `PS_GTM_ADV_TIKTOK_EVENTS` to ensure all 12 supported ecommerce events are active.
  - Resolved deceptive Back Office status counter where disabled platforms reported active event counts on initial PHP render; the counter now correctly indicates `(Platform Off)` whenever a platform is disabled.
  - Cleared Smarty template caches to immediately reflect live `tiktok` data layer output across both server hydration and client JavaScript pushes.

---

## [1.21.2] - 2026-10-24

### Changed
- **User-Friendly Back Office Platform Descriptions**:
  - Replaced technical internal variable names (`&quot;meta&quot;`, `ecomm_prodid`, `ecomm_pagetype`, `line_items`, `search_string`, `pid`) with clean, professional, human-understandable descriptions explaining what each advertising platform achieves for merchants.
  - Eliminated literal HTML entity artifacts (`&quot;` and `&amp;`) across all Back Office platform cards by using unquoted text and `ENT_NOQUOTES`.

---

## [1.21.1] - 2026-10-24

### Changed
- **Strict Array-of-Strings Format for `microsoft_ads` `ecomm_prodid`**:
  - Standardized `ecomm_prodid` inside `microsoft_ads.dynamic_remarketing` to always emit an array of strings (e.g. `['153']` on single product PDPs and `['153', '456']` on listings, cart, and checkout), strictly conforming to Microsoft Advertising UET retail dynamic remarketing specifications.
  - Eliminated single bare string output for single-product events.
- **Enhanced Conversion Parameters Forwarding**:
  - Ensured client-side `tax`, `shipping`, and `coupon` values are properly propagated into `microsoft_ads` payload options during browser interactions.

---

## [1.21.0] - 2026-10-24

### Added
- **Dedicated Microsoft Advertising (Bing Ads) & UET Integration (`microsoft_ads`)**:
  - Implemented universal dataLayer payload namespace `microsoft_ads` matching Microsoft Universal Event Tracking (UET) specifications and Microsoft Merchant Center (MMC) feeds.
  - **Standard Ecommerce Conversion Events**:
    - Dispatches standard Microsoft Advertising events with `event_action`: `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `view_item_list`, `view_search_results`, and `add_to_wishlist`.
    - Supplies transaction financials: `revenue_value`, `currency`, `transaction_id` (numeric format e.g. `"1239"`), `tax`, `shipping`, `coupon`, `checkout_step`, and `checkout_option`.
  - **Retail Dynamic Remarketing (`dynamic_remarketing`)**:
    - Standardized `dynamic_remarketing` schema with `ecomm_pagetype` (`home`, `product`, `category`, `searchresults`, `cart`, `purchase`, `other`), `ecomm_prodid` (string on product, array on listings/cart/purchase), `ecomm_totalvalue`, `ecomm_category`, and `ecomm_query`.
  - **Product Line Items Array**:
    - Generates dedicated `items` array with `id`, `name`, `brand`, `category`, `variant`, `price`, `quantity`, `list_name`, and `list_position`.
  - **Microsoft Merchant Center (MMC) Catalog Matching**:
    - Respects the module's Catalog ID Strategy (`composite`, `id_product`, `reference_sku`), ensuring 100% ID synchronization between dynamic remarketing tags and MMC product catalogs.
  - **Enhanced Conversions / Advanced Matching (`pid`)**:
    - Built-in `CustomerDataEngine::buildMicrosoftUserData()` producing normalized, SHA-256 hashed customer identifiers with dual-key compatibility (`email`/`em` and `phone_number`/`ph`) for UET tag and CAPI ingestion.
  - **Back Office Configuration & Event-Level Routing Matrix**:
    - Added dedicated Microsoft Advertising accordion card with official 4-square Microsoft branding (`views/img/microsoft_ads.png`).
    - Granular per-event toggles allowing merchants to independently enable or suppress `microsoft_ads` output across all 12 supported ecommerce events.
  - **DataLayer Purge Isolation**:
    - Integrated `microsoft_ads: null` into event purge resets, preventing stale parameter inheritance across consecutive single-page interactions.

---

## [1.20.3] - 2026-10-24

### Added
- **High-Resolution Branded Platform Icons**:
  - Integrated official high-resolution brand icons for all four advertising platforms (Meta / Facebook, Google Ads, TikTok, Pinterest) directly in the Back Office configuration interface.
  - Replaced generic FontAwesome icons and workarounds (such as music note for TikTok) with crisp branded logos in `views/img/`.
  - Added responsive icon container styling with clean neutral borders and shadows.

---

## [1.20.2] - 2026-10-24

### Changed
- **Strict Accounting Standard for Customer Lifecycle Metrics**:
  - Enforced strict `valid = 1` verification in `CustomerDataEngine::resolveCustomerLifecycleMetrics()` for prior orders count and customer lifetime value (`customer_lifetime_value`).
  - Offline pending orders (e.g. Bank Wire / Cash on Delivery with state *Awaiting bank wire payment*, `valid = 0`) are not counted as completed prior orders until an administrator accepts payment (`valid = 1`) in the PrestaShop Back Office.
  - Ensures 100% accounting accuracy for Google Ads New Customer Acquisition (NCA) and CLV bidding models.

---

## [1.20.1] - 2026-10-24

### Fixed
- **New Customer Acquisition (NCA) & CLV Support for Bank Wire and Offline Payments**:
  - Fixed database order query in `CustomerDataEngine::resolveCustomerLifecycleMetrics()` to include placed orders with non-canceled states (`valid = 1` or `current_state NOT IN (PS_OS_CANCELED, PS_OS_ERROR)`).
  - Resolved issue where test orders placed using Banküberweisung (`ps_wirepayment`, initial state 10 `valid = 0`) were omitted from prior orders and lifetime value calculations.
  - Guaranteed that repeat purchasers using bank wire or cash on delivery correctly fire `new_customer: false` and accumulate prior orders into `customer_lifetime_value`.

---

## [1.20.0] - 2026-10-24

### Added
- **Google Ads New Customer Acquisition (NCA) & Customer Lifetime Value (CLV)**:
  - Added authoritative backend calculation for `new_customer` (boolean `true`/`false`) in `CustomerDataEngine` based on PrestaShop core orders database (`ps_orders`), supporting both registered customers and guest checkout histories.
  - Added cumulative `customer_lifetime_value` (numeric spend) reflecting total customer revenue in store currency.
  - Included both `new_customer` and `customer_lifetime_value` at the root DataLayer level (for direct GTM variable mapping), inside `google_ads.cart_data` on `purchase`, and inside `user_data`.
  - Added full test coverage for first-time and repeat customer purchase scenarios.

---

## [1.19.3] - 2026-10-24

### Changed
- **Streamlined Google Ads Data Layer Schema**:
  - Eliminated redundant `conversion_data` object in favor of `cart_data` to ensure clean, deduplicated schema consistency.
  - Removed duplicate root-level parameters (`ecomm_pagetype`, `ecomm_prodid`, `ecomm_totalvalue`, `items`, `ecomm_category`) under `google_ads`, keeping them strictly isolated within their authoritative `google_ads.dynamic_remarketing` and `google_ads.cart_data` child objects.
  - Maintained complete separation between `dynamic_remarketing.items` (with `google_business_vertical: 'retail'`) and `cart_data.items` (with `id`, `quantity`, and `price`) across all conversion events.

---

## [1.19.2] - 2026-10-24

### Added
- **Google Ads Dynamic Remarketing and Cart Data for `view_search_results`**:
  - Activated Google Ads dynamic remarketing on `view_search_results` with `ecomm_pagetype: 'searchresults'`, authoritative `ecomm_prodid` array, search query `search_term`, total matching value `ecomm_totalvalue`, and retail dynamic remarketing `items` (`google_business_vertical: 'retail'`).
  - Added cart data items array (`conversion_data.items` and `cart_data.items`) containing search catalog item IDs, quantities, and prices.
  - Enabled active toggle in the PrestaShop Back Office Advertising Event Routing Matrix for Google Ads on `view_search_results`.

---

## [1.19.1] - 2026-10-23

### Fixed
- **Google Ads Dual Items Array Availability Across All Events**:
  - Expanded `conversion_data.items` (and alias `cart_data.items`) to all ecommerce events that contain product items (`add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `view_item`), rather than restricting it solely to `purchase`.
  - Both `dynamic_remarketing.items` (containing strictly `id` and `google_business_vertical: 'retail'`) and `conversion_data.items` / `cart_data.items` (containing strictly `id`, `quantity`, and `price`) are now simultaneously present and kept completely separate on all interactions.

---

## [1.19.0] - 2026-10-23

### Added
- **Google Ads Conversions with Cart Data (`google_ads.conversion_data`)**:
  - Structured dedicated basket-level conversion payload for Google Ads conversion tracking tags according to official Google specifications ([Answer 14943675](https://support.google.com/google-ads/answer/14943675)).
  - Implemented the official conversion cart data items schema containing strictly feed-matching `id`, units sold `quantity`, and unit selling `price`.
  - Added transaction-level parameters: `transaction_id`, `value`, `currency`, top-level `discount`, `aw_feed_country`, `aw_feed_language`, and optional `aw_merchant_id`.
  - Added Back Office toggle `PS_GTM_ADV_GADS_CART_DATA` and optional input `PS_GTM_ADV_GADS_MERCHANT_ID` under the Advertising tab.
- **Google Ads Retail Dynamic Remarketing (`google_ads.dynamic_remarketing`)**:
  - Structured dedicated dynamic remarketing payload across all shopping lifecycle events (`view_item`, `view_item_list`, `view_search_results`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `purchase`).
  - Implemented the official retail dynamic remarketing items schema containing strictly `id` and `google_business_vertical: 'retail'`.
  - Included standard and legacy remarketing parameters: `event_name`, `value`, `currency`, `ecomm_pagetype`, `ecomm_prodid`, `ecomm_totalvalue`, and `ecomm_category`.
- **Clean Namespace Separation & 100% Backward Compatibility**:
  - Maintained complete separation between `conversion_data` (strictly on `purchase`) and `dynamic_remarketing` (on remarketing events).
  - Preserved root-level backward-compatible aliases (`google_ads.ecomm_prodid`, `google_ads.ecomm_pagetype`, `google_ads.ecomm_totalvalue`, `google_ads.items`) so existing GTM tags require no mandatory reconfiguration.

---

## [1.18.3] - 2026-10-23

### Fixed
- **Wishlist Click Isolation & False `select_item` Suppression**:
  - Fixed an issue where clicking "Add to Wishlist" on a Product Detail Page (PDP) or catalog card triggered a false `select_item` event before `add_to_wishlist`.
  - Added comprehensive exclusion filters across all official and third-party theme wishlist selectors (`.wishlist-button-add`, `.btn-wishlist`, `[data-action*="wishlist"]`, `[data-wishlist-add]`, `.addToWishlist`, `.iqitwishlist-add`, `a[href*="wishlist"]`, etc.) to immediately bypass the `select_item` click listener.
  - Isolated the main product on Product Detail Pages (`pageContext.controller === 'product'`) so clicks on the current product's details, gallery, buttons, or options cannot trigger `select_item` for itself.
  - Implemented a 2000ms wishlist state debounce lock in `handleSelectItem` ensuring that no asynchronous or bubbling wishlist event can leak a stray `select_item` push.

---

## [1.18.2] - 2026-10-23

### Fixed
- **Strict Separation of Search vs Product-List Events**:
  - Eliminated consecutive dual-event firing on search pages: executing an on-site search or visiting search results now fires strictly `view_search_results`, and no longer automatically fires an unintended `view_item_list` immediately after.
  - Sourced `view_search_results` with authoritative search intent parameters (`search_term`, `search_results_count`) and catalog matching products (`items`) directly into GA4 and advertising namespaces (Meta `Search`, TikTok `Search`, Pinterest `Search`).
  - Preserved `view_item_list` strictly for genuine product catalog listing contexts (**Category**, **Home Featured**, **Brand / Manufacturer**, **On Sale**, **New Products**, **Best Sellers**) with authentic `item_list_id` (e.g. `category_12`) and `item_list_name` (e.g. `Trinkbecher`).
  - Removed misleading synthetic strings (`item_list_id: "search_results"` and `item_list_name: "Search: <term>"`) from search results items and page contexts.
  - Enhanced `select_item` to resolve product items from search results or categories cleanly without injecting synthetic search list IDs.

---

## [1.18.1] - 2026-10-23

### Fixed
- **Multi-Quantity Cart Item Removal Resolution**:
  - Fixed an issue where removing a multi-quantity item (e.g. 3 units) from the cart recorded `quantity: 1` instead of `3` in the `remove_from_cart` event.
  - Re-engineered `dispatchDirectRemoveFromCart` and `extractCartParamsFromUrlOrBody` to prioritize authoritative in-memory cart quantities (`cartQuantities[key]`), cart line item DOM inputs, and delta reconciliation rather than defaulting to `1`.
  - Guaranteed full mathematical financial reconciliation (`value: unit_price * full_quantity`) across all advertising platforms simultaneously:
    - **GA4**: `items[0].quantity = 3`, `value = 3 * price`
    - **Meta Pixel & CAPI**: `meta.num_items = 3`, `meta.contents[0].quantity = 3`, `meta.value = 3 * price`
    - **Pinterest Tag & API**: `pinterest.order_quantity = 3`, `pinterest.line_items[0].product_quantity = 3`, `pinterest.value = 3 * price`
    - **TikTok Pixel & Events API**: `tiktok.quantity = 3`, `tiktok.contents[0].quantity = 3`, `tiktok.value = 3 * price`
    - **Google Ads Dynamic Remarketing**: `google_ads.ecomm_totalvalue = 3 * price`, `google_ads.items[0].quantity = 3`

---

## [1.18.0] - 2026-10-23

### Added
- **Meta Advanced Matching (`meta.user_data`)**:
  - Implemented authoritative customer data extraction, normalization, and SHA-256 hashing for Meta Pixel & Conversions API (CAPI) Advanced Matching parameters:
    - `em`: Lowercase, trimmed email SHA-256 hashed.
    - `ph`: Phone number with country calling code, digits only (**strictly without `+` sign**), SHA-256 hashed.
    - `fn`: Lowercase first name (punctuation stripped) SHA-256 hashed.
    - `ln`: Lowercase last name (punctuation stripped) SHA-256 hashed.
    - `ct`: Lowercase city (spaces and punctuation stripped) SHA-256 hashed.
    - `st`: Lowercase 2-letter state code SHA-256 hashed.
    - `zp`: Lowercase postal/zip code (spaces and dashes stripped) SHA-256 hashed.
    - `country`: Lowercase 2-letter ISO country code SHA-256 hashed.
  - Pre-hydrated server-side on storefront page loads for authenticated customer sessions (`window.psGtmConfig.metaUserData`) and populated during checkout and purchase (`Purchase`) for maximum Event Quality Score (EQS) on Meta Events Manager.
  - Strictly omitted `external_id` and first-party browser cookies per privacy specification.

### Changed
- **Meta ViewCart Event Alignment**:
  - Reclassified Meta `view_cart` mapping from generic `ViewContent` to `Custom` event with `custom_event_name: 'ViewCart'`.
  - Preserved standard `ViewContent` strictly for product detail views (`view_item`) and miniature card interactions (`select_item`), preventing cart summary views from skewing product view metrics on Meta Events Manager.

---

## [1.17.1] - 2026-10-23

### Changed
- **Pinterest ViewCart Event Alignment**:
  - Reclassified Pinterest `view_cart` mapping from standard `PageVisit` to `Custom` event with `custom_event_name: 'ViewCart'`.
  - Preserved standard `PageVisit` strictly for product detail page views (`view_item`) and miniature card interactions (`select_item`), preventing cart summary views from generating conflicting product page signals on Pinterest Ads Manager.
  - Updated Back Office event specifications and documentation in `README.md`.

---

## [1.17.0] - 2026-10-23

### Added
- **TikTok Advanced Matching (`tiktok.user_data`)**:
  - Implemented authoritative customer extraction and SHA-256 hashing for TikTok Advanced Matching parameters:
    - `email`: Lowercase, trimmed email SHA-256 hashed.
    - `phone_number`: E.164 formatted international phone number (+[country code][digits]) SHA-256 hashed.
  - Pre-hydrated server-side on storefront page loads for authenticated customer sessions (`window.psGtmConfig.tiktokUserData`) and populated during checkout and purchase (`CompletePayment`) for maximum Event Match Quality (EMQ) on TikTok Events Manager.
  - Documented Google Tag Manager Data Layer Variables (`{{dlv - tiktok.user_data}}`, `{{dlv - tiktok.user_data.email}}`, `{{dlv - tiktok.user_data.phone_number}}`) in `README.md`.

---

## [1.16.0] - 2026-10-23

### Added
- **Pinterest Enhanced / Advanced Matching (`pinterest.user_data`)**:
  - Implemented authoritative customer extraction and SHA-256 hashing for Pinterest Advanced Matching parameters:
    - `em`: Lowercase, trimmed email SHA-256 hashed.
    - `ph`: Digits only phone number including country code SHA-256 hashed.
    - `fn`: Lowercase first name SHA-256 hashed.
    - `ln`: Lowercase last name SHA-256 hashed.
    - `ct`: Lowercase city without spaces SHA-256 hashed.
    - `st`: Lowercase 2-letter state code SHA-256 hashed.
    - `zp`: Lowercase postal code without spaces SHA-256 hashed.
    - `country`: Lowercase 2-letter ISO country code SHA-256 hashed.
  - Pre-hydrated server-side on storefront page loads for authenticated customer sessions (`window.psGtmConfig.pinterestUserData`) and populated during checkout and purchase for maximum Event Quality Score (EQS) on Pinterest Ads Manager.

### Changed
- **Pinterest Official Standard Events Alignment**:
  - Upgraded `begin_checkout` to official Pinterest standard event code `'InitiateCheckout'`.
  - Upgraded `add_payment_info` to official Pinterest standard event code `'AddPaymentInfo'`.
  - Upgraded `add_to_wishlist` to official Pinterest standard event code `'AddToWishList'`.
  - Removed obsolete custom event wrapping and suppressed redundant `custom_event_name` on standard events.
- **Pinterest Namespace Schema Optimization**:
  - Omitted redundant `contents` array across server-side and client-side payload builders in favor of official Pinterest Web Tag `line_items` (`product_id`, `product_name`, `product_price`, `product_quantity`, `product_category`, `product_brand`, `product_variant`, `product_variant_id`).
  - Omitted server-side GTM (`sGTM`) parameter names (`capi_event_name`, `event_id`) to keep the dataLayer lightweight and clean.

---

## [1.15.0] - 2026-10-23

### Added
- **Platform-Based Expandable Event Routing UI**:
  - Replaced the monolithic matrix table in the Back Office configuration with modern, platform-by-platform expandable accordion panels for **Meta**, **Google Ads**, **TikTok**, and **Pinterest**.
  - **Integrated Platform Master Toggles**: Each platform panel header now houses its own dedicated master enable/disable switch, status badge (`Enabled` / `Disabled`), live active event counter (e.g. `12 of 12 events active`), and expand/collapse chevron.
  - **Preserved Selections on Platform Disable**: Disabling a platform's master toggle preserves all underlying event-level selections, restoring them immediately when the platform is re-enabled.
  - **Platform-Scoped Bulk Actions**: Added independent "Enable all events" and "Disable all events" controls within each expanded panel that affect only the selected platform.
  - **Global Toolbar**: Added quick controls for "Expand All", "Collapse All", "Enable All Platforms", and "Disable All Platforms".
  - **Payload Builder Empty Routing Fix**: Fixed PHP payload builder evaluation when all events are disabled for a platform (`metaEvents = []`), ensuring strict suppression without fallback to default arrays.

---

## [1.14.5] - 2026-10-23

### Changed
- **TikTok Events Schema Alignment with Official Parameters**:
  - **Standard Parameter Alignment**: Added official `search_string` parameter to the `tiktok` advertising namespace on search events (`view_search_results` / `search`), directly aligning with TikTok's standard event specifications.
  - **Backwards Compatibility**: Retained `query` parameter as a legacy alias so existing Google Tag Manager containers and custom templates continue to function seamlessly without breaking changes.
  - **Documentation & GTM Variable Mappings**: Updated module `README.md` guidelines, events matrix, and GTM variable mapping table to reflect both `{{dlv - tiktok.search_string}}` and `{{dlv - tiktok.query}}`.

---

## [1.14.4] - 2026-10-23

### Fixed
- **Strict `select_item` Event Deduplication & Quick View Isolation**:
  - **Synchronous Click Debouncing**: Moved `lastSelectedItemId` and `lastSelectedItemTime` assignment to the entry of `handleSelectItem()`, preventing concurrent asynchronous resolution callbacks from dispatching duplicate `select_item` events during HTTP AJAX product resolution.
  - **Quick View Selector Exclusion**: Expanded listing click event exclusions to include theme-specific Quick View button selectors (`.js-quick-view-iqit`, `.js-quick-view`, `[data-link-action="quickview"]`, `[data-link-action="quick-view"]`), preventing capture click listeners from executing in tandem with PrestaShop core `clickQuickView` event bus listeners.
  - **Adapter Re-entrancy Protection**: Added `window.__psGtmAdapterLoaded` initialization guard in `prestashop-adapter.js` to guarantee single-instance DOM event listener binding.

---

## [1.14.3] - 2026-10-23

### Fixed
- **Search Results Dual-Event Semantic Isolation & Payload Deduping**:
  - **Eliminated Duplicate Item Impressions in GA4**: Sourced GA4 `view_search_results` strictly as a search query intent event (`search_term`, `search_results_count`) without repeating the full 24-product `ecommerce.items` array that belongs to `view_item_list`, preventing double list impression counts in Google Analytics 4.
  - **Strict Advertising Namespace Isolation on Search Listings**: Fixed `view_item_list` on search results pages (`searchresults`) so it strictly excludes false `ViewCategory` (Meta Pixel), `ViewCategory` (Pinterest Tag), and `ViewContent` (TikTok Pixel) emissions. The authoritative `Search` event dispatched with `view_search_results` now stands alone for social ad platforms without category pollution.
  - **Single-Query Search Optimization**: PrestaShop's `Search::find` catalog results are now loaded once on the search page and shared between `view_search_results` and `view_item_list`, cutting backend database query load in half.
  - **Google Ads Dynamic Remarketing Parity**: Maintained `ecomm_pagetype: 'searchresults'`, product ID arrays, and total search value cleanly on the listing impression event `view_item_list`.

---

## [1.14.2] - 2026-10-23

### Fixed
- **Universal Search Controller Detection (Warehouse/iqitsearch, Leo Search, Jolisearch, AS4)**:
  - Fixed search detection on stores running third-party search modules such as Warehouse theme (`module-iqitsearch-searchiqit`), Leo Search (`leosearch`), Jolisearch, and custom module search controllers.
  - Sourced search queries flexibly across all common parameters (`s`, `search_query`, `q`, `query`, `tag`).
  - Added client-side search discovery and third-party theme safety net in `prestashop-adapter.js` ensuring `view_search_results` is captured under all theme routing architectures.

---

## [1.14.1] - 2026-10-23

### Documentation & Guidelines
- **Comprehensive Documentation Updates in `README.md`**:
  - Added `view_search_results` to the Supported E-Commerce Events table with authoritative database search sourcing.
  - Documented `pinterest.search_query` under Pinterest Ads specifications.
  - Added custom search remarketing architecture note under Google Ads Dynamic Remarketing.
  - Added full Google Tag Manager Data Layer Variable (DLV) mapping table entries for `{{dlv - search_term}}`, `{{dlv - search_results_count}}`, `{{dlv - meta.search_string}}`, `{{dlv - pinterest.search_query}}`, and `{{dlv - tiktok.query}}`.

---

## [1.14.0] - 2026-10-23

### Added
- **Official GA4 `view_search_results` Event Tracking**:
  - Automatically fired when a user views on-site search results, strictly adhering to GA4 Enhanced Measurement and official eCommerce specifications.
  - Automatically captures `search_term` (query keyword) and `search_results_count` (total matching catalog results from PrestaShop database model `Search::find`).
  - Pre-hydrates matching catalog `items` in standard GA4 schema with catalog-accurate prices, brand, categories, and identifiers.
  - Dynamic event prefixing fully supported (e.g. `raihan_view_search_results`, `ps_view_search_results`).
- **Advertising Platform Search Namespaces**:
  - **Meta**: Exposes official `Search` event with `search_string = search_term`, catalog `content_ids`, and official `contents` array (`id`, `quantity`, `item_price`).
  - **TikTok**: Exposes official `Search` event with `query = search_term`, `content_id`, `content_ids`, and official `contents` array (`content_id`, `content_type`, `content_name`, `content_category`, `quantity`, `price`).
  - **Pinterest**: Exposes official `Search` event with `search_query = search_term`, `product_ids`, `line_items` (Web Tag), and `contents` (Conversions API v5).
- **Strict Exclusion of Google Ads Dynamic Remarketing**:
  - `google_ads` is strictly excluded from `view_search_results` and `search` payloads per custom remarketing architecture.
  - Back Office routing matrix displays transparent non-editable "Excluded" badge for Google Ads on `view_search_results`.
- **Seamless Dual Search View Dispatching**:
  - Emits both `view_search_results` and `view_item_list` on search results pages, ensuring search discovery and list impression measurements operate simultaneously without collision.

---

## [1.13.1] - 2026-10-23

### Changed
- **Streamlined Item Arrays for Meta, TikTok & Pinterest**:
  - `meta.contents`: Strictly limited to official required/recommended fields (`id`, `quantity`, `item_price`). Extraneous properties (`title`, `item_name`, `brand`, `category`) removed.
  - `tiktok.contents`: Strictly aligned to official TikTok Pixel & Events API specifications (`content_id`, `content_type`, `content_name`, `content_category`, `quantity`, `price`). Extraneous `brand` removed.
  - `pinterest`: Added dedicated `contents` array (`id`, `item_name`, `item_price`, `quantity`, `item_category`, `item_brand`) matching official Pinterest Conversions API v5 inside `custom_data`, alongside `line_items` for the Pinterest Web Tag.
- **Removed `capi_event_name`**:
  - Removed `capi_event_name` across `meta`, `tiktok`, and `pinterest` schemas to keep the dataLayer lightweight and clean.

---

## [1.13.0] - 2026-10-23

### Added
- **Official Pinterest Ads Integration (Tag & Conversions API)**:
  - Full official Pinterest schema compliance for both Pinterest Tag (`pintrk`) and Pinterest Conversions API (`event_data` / `custom_data`).
  - Native `pinterest` payload object in dataLayer with `event_name`, `custom_event_name`, `value`, `currency`, `order_quantity`, `order_id`, `search_query`, `product_ids`, `line_items`, and `contents`.
  - Comprehensive item-level line items array containing `product_id`, `product_name`, `product_price`, `product_quantity`, `product_category`, `product_brand`, `product_variant`, and `product_variant_id`.
  - Exact catalog matching for Pinterest Dynamic Product Ads matching PrestaShop catalog feed strategies (`composite`, `reference_sku`, `id_product`).
  - Event deduplication support via matching `order_id` / `event_id` and event names within Pinterest's 48-hour deduplication window.
- **Back Office Controls for Pinterest Ads**:
  - Independent switch toggle for Pinterest (`PS_GTM_ADV_PINTEREST_ENABLED`).
  - Integrated into the interactive event routing matrix (`PS_GTM_ADV_PINTEREST_EVENTS`) with Quick Control buttons ("All Pinterest" / "None") and per-event checkboxes across all supported ecommerce events.
  - Interactive specification hover popovers with exact Pinterest Tag and CAPI payload schemas.
- **Universal PrestaShop Compatibility**:
  - PrestaShop 1.7.0.0 through 8.x and 9.x compatibility.
  - Zero DOM scraping: authoritative backend data model resolution for all Pinterest product IDs, variant parameters, and prices.

---

## [1.11.0] - 2026-10-23

### Fixed
- **Resolved Spurious `view_item` Event After `add_to_cart`**:
  - Eliminated race condition in PrestaShop core event bus where `updatedProduct` fired immediately following cart modal response.
  - Added cart update active lock (`isCartUpdating` cooldown 2500ms) and combination attribute ID verification (`lastViewedProductAttributeId`) ensuring combination events only fire when a variant has genuinely changed.
- **Fixed `view_item_list` on Empty Categories / Listings**:
  - `PayloadBuilder::buildViewItemListPayload` now evaluates `$items` and returns `null` when no products exist on the page, preventing empty dataLayer emissions (`items: []`, `value: 0`).
- **Complete Advertising Platform Namespaces across All 11 Ecommerce Events**:
  - Resolved missing advertising namespaces (`meta`, `tiktok`, `google_ads`) on client-side events (`add_to_cart`, `remove_from_cart`, `select_item`, `add_to_wishlist`). All 11 supported events now consistently provide catalog-accurate identifiers.

### Added
- **Advertising Platform Data Layer Settings Panel in Back Office**:
  - **Master Toggle**: Global switch (`PS_GTM_ADV_MASTER_ENABLED`) to enable or suppress all advertising namespaces (`meta`, `tiktok`, `google_ads`) in a single click.
  - **Platform-Level Toggles**: Independent switches for Meta Pixel / CAPI, TikTok Events API, and Google Ads Dynamic Remarketing.
  - **Interactive 11-Event Routing Matrix**: Full matrix table allowing granular activation of each advertising platform across all 11 supported events (`view_item_list`, `select_item`, `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase`, `add_to_wishlist`).
  - **Convenience Controls**: Quick action toolbar featuring "Select All" / "Deselect All" per platform, per event row, and global "Enable All / Disable All" toggles.
  - **Detailed Spec Tooltips**: Hover popovers providing exact parameter specifications for Meta, TikTok, and Google Ads per event.

---

## [1.10.3] - 2026-10-22

### Added
- **Multi-Option Interactive Strategy Cards with Individual Hover Tooltips**:
  - Displays all 3 catalog strategies (Composite Database ID, Base Product ID, Reference/SKU) simultaneously in a clean card layout.
  - Each option features its own info icon and hover popover displaying detailed descriptions and feed recommendations.
  - Clicking any card dynamically updates the dropdown selection with an active checkmark and styling.

---

## [1.10.2] - 2026-10-22

### Added
- **Dynamic Hover Tooltips for Setting Options**:
  - Replaced static text with an interactive info pill that dynamically adapts to whichever Catalog Identifier Strategy is selected.
  - Hovering on the info pill smoothly displays an in-depth guidance card explaining when and why to use the selected strategy.
- **Native Tooltips Across All Module Settings**:
  - Added native Back Office hover hints to GTM Container ID, Event Prefix, Server-Side GTM URL, and Google Consent Mode v2.

---

## [1.10.1] - 2026-10-22

### Added
- **Product Catalog Strategy In-Context Guidelines**:
  - Embedded rich instructional helper documentation directly under the `Product Catalog Identifier Strategy` dropdown in Back Office.
  - Explains exact use cases:
    - `Composite Database ID`: Recommended for 90% of stores using standard GMC, Meta, or TikTok feeds.
    - `Base Product ID`: For stores exporting only parent products without variant rows.
    - `Reference / SKU`: For stores integrated with ERP/PIM systems indexing products by barcode/SKU.
  - Added visual helper badge to guide merchants to the best practice default.

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
