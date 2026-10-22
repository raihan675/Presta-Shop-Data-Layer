const { describe, it, beforeEach } = require('node:test');
const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Universal Advertising Platform Catalog-Matching Specification Test Suite', function () {

  beforeEach(function () {
    global.window = {
      dataLayer: [],
      psGtmConfig: {
        currency: 'EUR',
        currencyPrecision: 2,
        catalogIdStrategy: 'composite'
      }
    };
    EDL.setEventPrefix('');
  });

  it('1. Product 1 (Trink und Snack Tasse): Exact view_item payload matching Meta, TikTok, and Google Ads', function () {
    EDL.setEventPrefix('raihan');

    const product1Variant = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Dooky',
      item_category: 'Trinkbecher',
      item_variant: 'Farbe: Stahlblau',
      price: 13.00,
      quantity: 1
    };

    const norm = EDL.normalizeItems([product1Variant]);
    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: 13.00,
      items: norm
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    // Event & GA4 eCommerce
    assert.strictEqual(payload.event, 'raihan_view_item');
    assert.strictEqual(payload.original_event, 'view_item');
    assert.strictEqual(payload.ecommerce.currency, 'EUR');
    assert.strictEqual(payload.ecommerce.value, 13.00);
    assert.strictEqual(payload.ecommerce.items[0].item_id, '68');
    assert.strictEqual(payload.ecommerce.items[0].variant_id, '299');

    // Meta Pixel & CAPI namespace
    assert.ok(payload.meta, 'meta namespace must exist');
    assert.strictEqual(payload.meta.content_type, 'product');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299'], 'Meta content_ids must match catalog item ID');
    assert.strictEqual(payload.meta.currency, 'EUR');
    assert.strictEqual(payload.meta.value, 13.00);
    assert.strictEqual(payload.meta.contents.length, 1);
    assert.strictEqual(payload.meta.contents[0].id, '68_299');
    assert.strictEqual(payload.meta.contents[0].quantity, 1);
    assert.strictEqual(payload.meta.contents[0].item_price, 13.00);

    // TikTok Pixel & Events API namespace
    assert.ok(payload.tiktok, 'tiktok namespace must exist');
    assert.strictEqual(payload.tiktok.content_type, 'product');
    assert.strictEqual(payload.tiktok.content_id, '68_299');
    assert.strictEqual(payload.tiktok.currency, 'EUR');
    assert.strictEqual(payload.tiktok.value, 13.00);
    assert.strictEqual(payload.tiktok.contents[0].content_id, '68_299');
    assert.strictEqual(payload.tiktok.contents[0].content_name, 'Trink und Snack Tasse');
    assert.strictEqual(payload.tiktok.contents[0].quantity, 1);
    assert.strictEqual(payload.tiktok.contents[0].price, 13.00);

    // Google Ads Dynamic Remarketing namespace
    assert.ok(payload.google_ads, 'google_ads namespace must exist');
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'product');
    assert.strictEqual(payload.google_ads.ecomm_prodid, '68_299', 'Single product ecomm_prodid must be string 68_299');
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, 13.00);
    assert.strictEqual(payload.google_ads.items[0].id, '68_299');
    assert.strictEqual(payload.google_ads.items[0].google_business_vertical, 'retail');
    assert.strictEqual(payload.google_ads.items[0].price, 13.00);
    assert.strictEqual(payload.google_ads.items[0].quantity, 1);
  });

  it('2. Product 2 (Sonnenblenden fürs Auto): Exact view_item payload for Simple Product without combinations', function () {
    const product2Simple = {
      item_id: '1033',
      variant_id: '',
      sku_id: '5128231',
      item_name: 'Sonnenblenden fürs Auto',
      item_brand: 'Dooky',
      item_category: 'Sonnenschutz',
      item_variant: '',
      price: 9.99,
      quantity: 1
    };

    const norm = EDL.normalizeItems([product2Simple]);
    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: 9.99,
      items: norm
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    // Meta
    assert.deepStrictEqual(payload.meta.content_ids, ['1033']);
    assert.strictEqual(payload.meta.contents[0].id, '1033');
    assert.strictEqual(payload.meta.value, 9.99);

    // TikTok
    assert.strictEqual(payload.tiktok.content_id, '1033');
    assert.strictEqual(payload.tiktok.contents[0].content_id, '1033');
    assert.strictEqual(payload.tiktok.contents[0].price, 9.99);

    // Google Ads
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'product');
    assert.strictEqual(payload.google_ads.ecomm_prodid, '1033');
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, 9.99);
    assert.strictEqual(payload.google_ads.items[0].id, '1033');
  });

  it('3. Multi-quantity add_to_cart: 2 units of Variant 299 (26.00 €) with cart pagetype', function () {
    const cartItem = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Dooky',
      item_category: 'Trinkbecher',
      item_variant: 'Farbe: Stahlblau',
      price: 13.00,
      quantity: 2
    };

    const norm = EDL.normalizeItems([cartItem]);
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 26.00,
      items: norm
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    assert.strictEqual(payload.ecommerce.value, 26.00);

    // Meta: content_ids contains variant catalog ID, quantity is 2, value is 26.00
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299']);
    assert.strictEqual(payload.meta.contents[0].quantity, 2);
    assert.strictEqual(payload.meta.contents[0].item_price, 13.00);
    assert.strictEqual(payload.meta.value, 26.00);

    // TikTok: quantity 2, price 13.00, value 26.00
    assert.strictEqual(payload.tiktok.contents[0].quantity, 2);
    assert.strictEqual(payload.tiktok.contents[0].price, 13.00);
    assert.strictEqual(payload.tiktok.value, 26.00);

    // Google Ads: cart pagetype, array of prodids, total value 26.00
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'cart');
    assert.deepStrictEqual(payload.google_ads.ecomm_prodid, ['68_299']);
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, 26.00);
  });

  it('4. begin_checkout: Multi-product cart with 2 Blue Cups + 1 Sunshade (Total 35.99 €)', function () {
    const checkoutItems = [
      {
        item_id: '68',
        variant_id: '299',
        sku_id: 'BHK125-B',
        item_name: 'Trink und Snack Tasse',
        price: 13.00,
        quantity: 2
      },
      {
        item_id: '1033',
        variant_id: '',
        sku_id: '5128231',
        item_name: 'Sonnenblenden fürs Auto',
        price: 9.99,
        quantity: 1
      }
    ];

    const norm = EDL.normalizeItems(checkoutItems);
    EDL.pushEvent('begin_checkout', {
      currency: 'EUR',
      value: 35.99,
      items: norm
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    // Meta: contains both catalog IDs and num_items = 3
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299', '1033']);
    assert.strictEqual(payload.meta.num_items, 3);
    assert.strictEqual(payload.meta.value, 35.99);

    // TikTok: contains both line items
    assert.strictEqual(payload.tiktok.contents.length, 2);
    assert.strictEqual(payload.tiktok.contents[0].content_id, '68_299');
    assert.strictEqual(payload.tiktok.contents[0].quantity, 2);
    assert.strictEqual(payload.tiktok.contents[1].content_id, '1033');
    assert.strictEqual(payload.tiktok.contents[1].quantity, 1);
    assert.strictEqual(payload.tiktok.value, 35.99);

    // Google Ads
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'cart');
    assert.deepStrictEqual(payload.google_ads.ecomm_prodid, ['68_299', '1033']);
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, 35.99);
  });

  it('5. add_shipping_info & add_payment_info checkout funnel stages', function () {
    const cartItems = [
      { item_id: '68', variant_id: '299', item_name: 'Trink und Snack Tasse', price: 13.00, quantity: 2 },
      { item_id: '1033', variant_id: '', item_name: 'Sonnenblenden fürs Auto', price: 9.99, quantity: 1 }
    ];
    const norm = EDL.normalizeItems(cartItems);

    // add_shipping_info
    EDL.pushEvent('add_shipping_info', {
      currency: 'EUR',
      value: 35.99,
      shipping_tier: 'DHL Paket Standard',
      items: norm
    });

    let payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.ecommerce.shipping_tier, 'DHL Paket Standard');
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'cart');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299', '1033']);

    // add_payment_info
    EDL.pushEvent('add_payment_info', {
      currency: 'EUR',
      value: 35.99,
      payment_type: 'PayPal Checkout',
      items: norm
    });

    payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.ecommerce.payment_type, 'PayPal Checkout');
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'cart');
    assert.strictEqual(payload.meta.value, 35.99);
  });

  it('6. purchase: Full transaction with advertising parameters, user_data, and financial breakdown', function () {
    const purchasedItems = [
      { item_id: '68', variant_id: '299', sku_id: 'BHK125-B', item_name: 'Trink und Snack Tasse', price: 13.00, quantity: 2 },
      { item_id: '1033', variant_id: '', sku_id: '5128231', item_name: 'Sonnenblenden fürs Auto', price: 9.99, quantity: 1 }
    ];
    const norm = EDL.normalizeItems(purchasedItems);

    const userData = {
      email: 'customer@example.de',
      phone_number: '+491701234567',
      address: {
        first_name: 'Max',
        last_name: 'Mustermann',
        city: 'Frankfurt',
        postal_code: '60311',
        country: 'DE'
      }
    };

    const orderTotals = {
      order_id: 1250,
      order_reference: 'HTAIWHXQL',
      product_subtotal: 35.99,
      shipping_total: 4.50,
      tax_total: 6.46,
      order_total: 40.49,
      currency: 'EUR'
    };

    EDL.pushEvent('purchase', {
      transaction_id: '1250',
      value: 35.99,
      tax: 6.46,
      shipping: 4.50,
      currency: 'EUR',
      items: norm
    }, userData, { order_totals: orderTotals });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    assert.strictEqual(payload.original_event, 'purchase');
    assert.strictEqual(payload.ecommerce.value, 35.99);
    assert.strictEqual(payload.ecommerce.transaction_id, '1250');

    // Meta
    assert.strictEqual(payload.meta.content_type, 'product');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299', '1033']);
    assert.strictEqual(payload.meta.num_items, 3);
    assert.strictEqual(payload.meta.value, 35.99);

    // TikTok
    assert.strictEqual(payload.tiktok.content_type, 'product');
    assert.strictEqual(payload.tiktok.contents.length, 2);
    assert.strictEqual(payload.tiktok.value, 35.99);

    // Google Ads
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'purchase');
    assert.deepStrictEqual(payload.google_ads.ecomm_prodid, ['68_299', '1033']);
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, 35.99);

    // User Data & Order Totals
    assert.strictEqual(payload.user_data.email, 'customer@example.de');
    assert.strictEqual(payload.order_totals.order_reference, 'HTAIWHXQL');
    assert.strictEqual(payload.order_totals.order_total, 40.49);
  });

  it('7. view_item_list (Category page): product_group content_type and parent base IDs for Meta & TikTok', function () {
    const listProducts = [
      { item_id: '68', variant_id: '', sku_id: 'BHK125', item_name: 'Trink und Snack Tasse', price: 13.00, quantity: 1, item_category: 'Auto & Unterwegs' },
      { item_id: '1033', variant_id: '', sku_id: '5128231', item_name: 'Sonnenblenden fürs Auto', price: 9.99, quantity: 1, item_category: 'Auto & Unterwegs' }
    ];

    const norm = EDL.normalizeItems(listProducts);
    EDL.pushEvent('view_item_list', {
      currency: 'EUR',
      value: 22.99,
      item_list_id: 'category_142',
      item_list_name: 'Auto & Unterwegs',
      items: norm
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    // Meta: Category views must use product_group with parent IDs
    assert.strictEqual(payload.meta.content_type, 'product_group');
    assert.deepStrictEqual(payload.meta.content_ids, ['68', '1033']);

    // TikTok: Category views use product_group
    assert.strictEqual(payload.tiktok.content_type, 'product_group');

    // Google Ads: pagetype category, ecomm_category preserved
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'category');
    assert.strictEqual(payload.google_ads.ecomm_category, 'Auto & Unterwegs');
    assert.deepStrictEqual(payload.google_ads.ecomm_prodid, ['68', '1033']);
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, 22.99);
  });

  it('8. add_to_wishlist: Dispatches with catalog-accurate advertising parameters', function () {
    const wishlistProduct = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 1
    };

    const norm = EDL.normalizeItems([wishlistProduct]);
    EDL.pushEvent('add_to_wishlist', {
      currency: 'EUR',
      value: 13.00,
      items: norm
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    assert.strictEqual(payload.original_event, 'add_to_wishlist');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299']);
    assert.strictEqual(payload.tiktok.content_id, '68_299');
    assert.strictEqual(payload.google_ads.ecomm_pagetype, 'product');
    assert.strictEqual(payload.google_ads.ecomm_prodid, '68_299');
  });

  it('9. Catalog ID Strategy Switching: composite vs reference_sku vs id_product', function () {
    const item = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B'
    };

    // 1. Composite (Default)
    assert.strictEqual(EDL.resolveCatalogItemId(item, 'composite'), '68_299');

    // 2. Reference / Physical SKU
    assert.strictEqual(EDL.resolveCatalogItemId(item, 'reference_sku'), 'BHK125-B');

    // 3. Base Product ID
    assert.strictEqual(EDL.resolveCatalogItemId(item, 'id_product'), '68');

    // Simple product without combination
    const simple = {
      item_id: '1033',
      variant_id: '',
      sku_id: '5128231'
    };
    assert.strictEqual(EDL.resolveCatalogItemId(simple, 'composite'), '1033');
    assert.strictEqual(EDL.resolveCatalogItemId(simple, 'reference_sku'), '5128231');
    assert.strictEqual(EDL.resolveCatalogItemId(simple, 'id_product'), '1033');
  });

});
