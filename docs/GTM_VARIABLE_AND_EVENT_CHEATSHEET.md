# GTM Variables, Triggers & Event Payloads Cheat Sheet
### Quick Reference for PrestaShop DataLayer (`ps_gtm_datalayer`)

---

## 1. Google Tag Manager Variables (DLV Quick Copy)

Use these exact paths when creating **Data Layer Variables** in GTM:

### Core GA4 E-Commerce Variables
| GTM Variable Name | Data Layer Variable Path | Sample Value |
| :--- | :--- | :--- |
| `{{dlv - ecommerce.currency}}` | `ecommerce.currency` | `"EUR"` |
| `{{dlv - ecommerce.value}}` | `ecommerce.value` | `26.00` |
| `{{dlv - ecommerce.items}}` | `ecommerce.items` | `[{"item_id":"68_299", ...}]` |
| `{{dlv - ecommerce.transaction_id}}` | `ecommerce.transaction_id` | `"1248"` |
| `{{dlv - ecommerce.coupon}}` | `ecommerce.coupon` | `"SUMMER10"` |
| `{{dlv - ecommerce.shipping}}` | `ecommerce.shipping` | `4.99` |
| `{{dlv - ecommerce.tax}}` | `ecommerce.tax` | `5.20` |
| `{{dlv - ecommerce.search_term}}` | `ecommerce.search_term` | `"tasse"` |
| `{{dlv - ecommerce.item_list_id}}` | `ecommerce.item_list_id` | `"category_12"` |
| `{{dlv - ecommerce.item_list_name}}` | `ecommerce.item_list_name` | `"Trinkbecher"` |

---

### Meta (Facebook & Instagram Pixel) Variables
| GTM Variable Name | Data Layer Variable Path | Sample Value |
| :--- | :--- | :--- |
| `{{dlv - meta.event_name}}` | `meta.event_name` | `"ViewContent"`, `"AddToCart"` |
| `{{dlv - meta.content_type}}` | `meta.content_type` | `"product"`, `"product_group"` |
| `{{dlv - meta.content_ids}}` | `meta.content_ids` | `["68_299"]` |
| `{{dlv - meta.contents}}` | `meta.contents` | `[{"id":"68_299","quantity":2,"item_price":13.00}]` |
| `{{dlv - meta.num_items}}` | `meta.num_items` | `2` |
| `{{dlv - meta.order_id}}` | `meta.order_id` | `"RGXUHEBWD"` |
| `{{dlv - meta.value}}` | `meta.value` | `26.00` |
| `{{dlv - meta.currency}}` | `meta.currency` | `"EUR"` |
| `{{dlv - meta.user_data}}` | `meta.user_data` | `{"em":"...","ph":"...","fn":"..."}` |

---

### Google Ads Variables (Cart Data & Remarketing)
| GTM Variable Name | Data Layer Variable Path | Target Usage |
| :--- | :--- | :--- |
| `{{dlv - google_ads.dynamic_remarketing.ecomm_pagetype}}` | `google_ads.dynamic_remarketing.ecomm_pagetype` | Remarketing Pagetype (`product`, `cart`) |
| `{{dlv - google_ads.dynamic_remarketing.ecomm_prodid}}` | `google_ads.dynamic_remarketing.ecomm_prodid` | Remarketing GMC IDs |
| `{{dlv - google_ads.dynamic_remarketing.ecomm_totalvalue}}` | `google_ads.dynamic_remarketing.ecomm_totalvalue` | Remarketing Page Value |
| `{{dlv - google_ads.dynamic_remarketing.items}}` | `google_ads.dynamic_remarketing.items` | Remarketing Items Array |
| `{{dlv - google_ads.cart_data.items}}` | `google_ads.cart_data.items` | Cart Data Items Array |
| `{{dlv - google_ads.cart_data.aw_merchant_id}}` | `google_ads.cart_data.aw_merchant_id` | Merchant Center Account ID |
| `{{dlv - google_ads.cart_data.aw_feed_country}}` | `google_ads.cart_data.aw_feed_country` | Feed Country Code (`"DE"`, `"US"`) |
| `{{dlv - google_ads.cart_data.aw_feed_language}}` | `google_ads.cart_data.aw_feed_language` | Feed Language Code (`"de"`, `"en"`) |
| `{{dlv - google_ads.cart_data.discount}}` | `google_ads.cart_data.discount` | Total Order Discount |
| `{{dlv - google_ads.cart_data.new_customer}}` | `google_ads.cart_data.new_customer` | New Customer Acquisition (`true`/`false`) |
| `{{dlv - google_ads.cart_data.customer_lifetime_value}}` | `google_ads.cart_data.customer_lifetime_value` | Cumulative Customer Spend (`245.80`) |
| `{{dlv - user_data}}` | `user_data` | Enhanced Conversions Object |

---

### Pinterest Ads Variables
| GTM Variable Name | Data Layer Variable Path | Target Usage |
| :--- | :--- | :--- |
| `{{dlv - pinterest.event_name}}` | `pinterest.event_name` | Event Name (`PageVisit`, `AddToCart`) |
| `{{dlv - pinterest.line_items}}` | `pinterest.line_items` | Official Pinterest Items Array |
| `{{dlv - pinterest.product_ids}}` | `pinterest.product_ids` | Product IDs Array |
| `{{dlv - pinterest.order_id}}` | `pinterest.order_id` | Order Reference on Checkout |
| `{{dlv - pinterest.user_data}}` | `pinterest.user_data` | Enhanced Matching Object |

---

### TikTok Ads Variables
| GTM Variable Name | Data Layer Variable Path | Target Usage |
| :--- | :--- | :--- |
| `{{dlv - tiktok.event_name}}` | `tiktok.event_name` | Event Name (`ViewContent`, `AddToCart`) |
| `{{dlv - tiktok.contents}}` | `tiktok.contents` | TikTok Contents Array |
| `{{dlv - tiktok.content_ids}}` | `tiktok.content_ids` | Content IDs Array |
| `{{dlv - tiktok.order_id}}` | `tiktok.order_id` | Order Reference on CompletePayment |
| `{{dlv - tiktok.user_data.email}}` | `tiktok.user_data.email` | Advanced Matching Email Hash |
| `{{dlv - tiktok.user_data.phone_number}}` | `tiktok.user_data.phone_number` | Advanced Matching Phone Hash |

---

### Microsoft Advertising (Bing Ads / UET) Variables
| GTM Variable Name | Data Layer Variable Path | Target Usage |
| :--- | :--- | :--- |
| `{{dlv - microsoft_ads.event_action}}` | `microsoft_ads.event_action` | Action Name (`view_item`, `purchase`) |
| `{{dlv - microsoft_ads.revenue_value}}` | `microsoft_ads.revenue_value` | Normalized Revenue Value |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_prodid}}` | `microsoft_ads.dynamic_remarketing.ecomm_prodid` | Array of Product IDs |
| `{{dlv - microsoft_ads.dynamic_remarketing.ecomm_pagetype}}` | `microsoft_ads.dynamic_remarketing.ecomm_pagetype` | Pagetype (`product`, `cart`) |
| `{{dlv - microsoft_ads.pid}}` | `microsoft_ads.pid` | Enhanced Conversions Object |

---

## 2. GTM Custom Event Triggers Matrix

Create these **Custom Event Triggers** in GTM under **Triggers > New > Custom Event**:

| Trigger Name | Event Name Field | Use Regex? | When Fired by Module |
| :--- | :--- | :--- | :--- |
| `ce - view_item_list` | `view_item_list` | No | Category, Brand, New Products, Best Sellers listings |
| `ce - view_search_results` | `view_search_results` | No | Search results page (`/search?s=...`) |
| `ce - view_item` | `view_item` | No | Product page load & combination variant switches |
| `ce - select_item` | `select_item` | No | Clicking product item from listing |
| `ce - add_to_wishlist` | `add_to_wishlist` | No | Adding product to wishlist |
| `ce - add_to_cart` | `add_to_cart` | No | Adding item to cart or incrementing quantity |
| `ce - remove_from_cart` | `remove_from_cart` | No | Deleting item from cart or decrementing quantity |
| `ce - view_cart` | `view_cart` | No | Loading cart summary page (`/cart`) |
| `ce - begin_checkout` | `begin_checkout` | No | Entering checkout Step 1 (`/order`) |
| `ce - add_shipping_info` | `add_shipping_info` | No | Selecting and confirming shipping carrier |
| `ce - add_payment_info` | `add_payment_info` | No | Selecting payment method / gateway |
| `ce - purchase` | `purchase` | No | Order confirmation page (`order-confirmation`) |

> [!TIP]
> **Universal Regex Trigger with Prefix Support**:
> If an event prefix is configured in module settings (e.g. `shop_purchase`), use this trigger setting:
> * **Event Name**: `.*(view_item_list|view_search_results|view_item|add_to_cart|remove_from_cart|view_cart|begin_checkout|add_shipping_info|add_payment_info|purchase)`
> * **Check**: `Use regex matching`

---

## 3. Quick Console Verification Snippet

Paste into DevTools Console (`F12`) on any page to audit the dataLayer:

```javascript
(function() {
  var events = window.dataLayer.filter(function(e) {
    return e && e.event && !e.event.startsWith('gtm.');
  });
  console.group('%cPrestaShop DataLayer Live Audit', 'color: #00bcd4; font-size: 14px; font-weight: bold;');
  events.forEach(function(e, i) {
    console.log(
      '%c[' + (i + 1) + '] ' + e.event + ' %c(Original: ' + (e.original_event || e.event) + ')',
      'color: #4caf50; font-weight: bold;',
      'color: #9e9e9e;'
    );
    if (e.ecommerce) console.log('  GA4 Ecommerce:', e.ecommerce);
    if (e.google_ads) console.log('  Google Ads:', e.google_ads);
    if (e.meta) console.log('  Meta:', e.meta);
    if (e.pinterest) console.log('  Pinterest:', e.pinterest);
    if (e.tiktok) console.log('  TikTok:', e.tiktok);
    if (e.microsoft_ads) console.log('  Microsoft Ads:', e.microsoft_ads);
  });
  console.groupEnd();
})();
```
