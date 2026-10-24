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
    assert.strictEqual(payload.meta.event_name, 'ViewContent');
    assert.strictEqual(payload.meta.capi_event_name, undefined, 'capi_event_name must be removed');
    assert.strictEqual(payload.meta.content_type, 'product');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299'], 'Meta content_ids must match catalog item ID');
    assert.strictEqual(payload.meta.content_name, 'Trink und Snack Tasse');
    assert.strictEqual(payload.meta.content_category, 'Trinkbecher');
    assert.strictEqual(payload.meta.currency, 'EUR');
    assert.strictEqual(payload.meta.value, 13.00);
    assert.strictEqual(payload.meta.num_items, 1);
    assert.strictEqual(payload.meta.contents.length, 1);
    assert.deepStrictEqual(payload.meta.contents[0], {
      id: '68_299',
      quantity: 1,
      item_price: 13.00
    }, 'Meta contents must contain only official necessary parameters: id, quantity, item_price');

    // TikTok Pixel & Events API namespace
    assert.ok(payload.tiktok, 'tiktok namespace must exist');
    assert.strictEqual(payload.tiktok.event_name, 'ViewContent');
    assert.strictEqual(payload.tiktok.capi_event_name, undefined, 'capi_event_name must be removed');
    assert.strictEqual(payload.tiktok.content_type, 'product');
    assert.strictEqual(payload.tiktok.content_id, '68_299');
    assert.deepStrictEqual(payload.tiktok.content_ids, ['68_299']);
    assert.strictEqual(payload.tiktok.content_name, 'Trink und Snack Tasse');
    assert.strictEqual(payload.tiktok.content_category, 'Trinkbecher');
    assert.strictEqual(payload.tiktok.currency, 'EUR');
    assert.strictEqual(payload.tiktok.value, 13.00);
    assert.strictEqual(payload.tiktok.quantity, 1);
    assert.deepStrictEqual(payload.tiktok.contents[0], {
      content_id: '68_299',
      content_type: 'product',
      content_name: 'Trink und Snack Tasse',
      content_category: 'Trinkbecher',
      quantity: 1,
      price: 13.00
    }, 'TikTok contents must contain only official necessary parameters: content_id, content_type, content_name, content_category, quantity, price');

    // Google Ads Dynamic Remarketing namespace
    assert.ok(payload.google_ads, 'google_ads namespace must exist');
    assert.strictEqual(payload.google_ads.event_name, 'view_item');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.event_name, 'view_item');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'product');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, '68_299', 'Single product ecomm_prodid must be string 68_299');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 13.00);
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.items, [
      { id: '68_299', google_business_vertical: 'retail' }
    ]);
    assert.strictEqual(payload.google_ads.conversion_data, undefined, 'conversion_data must be removed in favor of cart_data');
    assert.deepStrictEqual(payload.google_ads.cart_data.items, [
      { id: '68_299', quantity: 1, price: 13.00 }
    ]);
    // Root duplicate parameters must not exist
    assert.strictEqual(payload.google_ads.ecomm_pagetype, undefined);
    assert.strictEqual(payload.google_ads.ecomm_prodid, undefined);
    assert.strictEqual(payload.google_ads.ecomm_totalvalue, undefined);
    assert.strictEqual(payload.google_ads.items, undefined);

    // Pinterest Tag & Conversions API namespace
    assert.ok(payload.pinterest, 'pinterest namespace must exist');
    assert.strictEqual(payload.pinterest.event_name, 'PageVisit');
    assert.strictEqual(payload.pinterest.capi_event_name, undefined, 'capi_event_name must be removed');
    assert.strictEqual(payload.pinterest.currency, 'EUR');
    assert.strictEqual(payload.pinterest.value, 13.00);
    assert.strictEqual(payload.pinterest.order_quantity, 1);
    assert.deepStrictEqual(payload.pinterest.product_ids, ['68_299']);
    assert.strictEqual(payload.pinterest.line_items.length, 1);
    assert.deepStrictEqual(payload.pinterest.line_items[0], {
      product_id: '68_299',
      product_name: 'Trink und Snack Tasse',
      product_price: 13.00,
      product_quantity: 1,
      product_category: 'Trinkbecher',
      product_brand: 'Dooky',
      product_variant: 'Farbe: Stahlblau',
      product_variant_id: '299'
    });
    assert.strictEqual(payload.pinterest.contents, undefined, 'Pinterest contents must be omitted in favor of official line_items');
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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'product');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, '1033');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 9.99);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.items[0].id, '1033');
    assert.strictEqual(payload.google_ads.ecomm_pagetype, undefined);
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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'cart');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['68_299']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 26.00);
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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'cart');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['68_299', '1033']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 35.99);
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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'cart');
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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'cart');
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
      new_customer: true,
      customer_lifetime_value: 35.99,
      items: norm
    }, userData, { order_totals: orderTotals });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    assert.strictEqual(payload.original_event, 'purchase');
    assert.strictEqual(payload.new_customer, true);
    assert.strictEqual(payload.customer_lifetime_value, 35.99);
    assert.strictEqual(payload.ecommerce.value, 35.99);
    assert.strictEqual(payload.ecommerce.transaction_id, '1250');

    // Meta
    assert.strictEqual(payload.meta.event_name, 'Purchase');
    assert.strictEqual(payload.meta.capi_event_name, undefined);
    assert.strictEqual(payload.meta.order_id, '1250');
    assert.strictEqual(payload.meta.content_type, 'product');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299', '1033']);
    assert.strictEqual(payload.meta.num_items, 3);
    assert.strictEqual(payload.meta.value, 35.99);

    // TikTok
    assert.strictEqual(payload.tiktok.event_name, 'CompletePayment');
    assert.strictEqual(payload.tiktok.capi_event_name, undefined);
    assert.strictEqual(payload.tiktok.order_id, '1250');
    assert.strictEqual(payload.tiktok.content_type, 'product');
    assert.strictEqual(payload.tiktok.quantity, 3);
    assert.strictEqual(payload.tiktok.contents.length, 2);
    assert.strictEqual(payload.tiktok.value, 35.99);

    // Google Ads Dynamic Remarketing
    assert.strictEqual(payload.google_ads.dynamic_remarketing.event_name, 'purchase');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'purchase');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['68_299', '1033']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 35.99);
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.items, [
      { id: '68_299', google_business_vertical: 'retail' },
      { id: '1033', google_business_vertical: 'retail' }
    ]);

    // Google Ads Cart Data
    assert.strictEqual(payload.google_ads.conversion_data, undefined, 'conversion_data must be removed in favor of cart_data');
    assert.ok(payload.google_ads.cart_data, 'cart_data must exist on purchase');
    assert.strictEqual(payload.google_ads.cart_data.transaction_id, '1250');
    assert.strictEqual(payload.google_ads.cart_data.value, 35.99);
    assert.strictEqual(payload.google_ads.cart_data.currency, 'EUR');
    assert.strictEqual(payload.google_ads.cart_data.new_customer, true);
    assert.strictEqual(payload.google_ads.cart_data.customer_lifetime_value, 35.99);
    assert.deepStrictEqual(payload.google_ads.cart_data.items, [
      { id: '68_299', quantity: 2, price: 13.00 },
      { id: '1033', quantity: 1, price: 9.99 }
    ]);

    // Google Ads Dynamic Remarketing
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'purchase');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['68_299', '1033']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 35.99);
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.items, [
      { id: '68_299', google_business_vertical: 'retail' },
      { id: '1033', google_business_vertical: 'retail' }
    ]);
    assert.strictEqual(payload.google_ads.ecomm_pagetype, undefined);

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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'category');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_category, 'Auto & Unterwegs');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['68', '1033']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 22.99);
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
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'product');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, '68_299');
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

  it('10. Advertising Settings: Master toggle completely suppresses advertising namespaces', function () {
    global.window.psGtmConfig.advertisingSettings = {
      master_enabled: false,
      meta_enabled: true,
      tiktok_enabled: true,
      gads_enabled: true,
      pinterest_enabled: true
    };

    const item = {
      item_id: '68',
      variant_id: '298',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 1
    };

    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: 13.00,
      items: [item]
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.original_event, 'view_item');
    assert.strictEqual(payload.meta, undefined);
    assert.strictEqual(payload.tiktok, undefined);
    assert.strictEqual(payload.google_ads, undefined);
    assert.strictEqual(payload.pinterest, undefined);
  });

  it('11. Advertising Settings: Individual platform switches toggle specific namespaces', function () {
    global.window.psGtmConfig.advertisingSettings = {
      master_enabled: true,
      meta_enabled: true,
      tiktok_enabled: false,
      gads_enabled: false,
      pinterest_enabled: true
    };

    const item = {
      item_id: '68',
      variant_id: '298',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 1
    };

    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
      items: [item]
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.original_event, 'add_to_cart');
    assert.ok(payload.meta !== undefined);
    assert.strictEqual(payload.tiktok, undefined);
    assert.strictEqual(payload.google_ads, undefined);
    assert.ok(payload.pinterest !== undefined);
  });

  it('12. Advertising Settings: Event routing matrix activates platforms selectively per event', function () {
    global.window.psGtmConfig.advertisingSettings = {
      master_enabled: true,
      meta_enabled: true,
      tiktok_enabled: true,
      gads_enabled: true,
      pinterest_enabled: true,
      meta_events: ['purchase', 'add_to_cart'],
      tiktok_events: ['purchase'],
      gads_events: ['view_item', 'purchase'],
      pinterest_events: ['add_to_cart', 'purchase']
    };

    const item = {
      item_id: '68',
      variant_id: '298',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 1
    };

    // On view_item: only google_ads should be included
    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: 13.00,
      items: [item]
    });
    let payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.meta, undefined);
    assert.strictEqual(payload.tiktok, undefined);
    assert.ok(payload.google_ads !== undefined);
    assert.strictEqual(payload.pinterest, undefined);

    // On add_to_cart: meta and pinterest should be included
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
      items: [item]
    });
    payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(payload.meta !== undefined);
    assert.strictEqual(payload.tiktok, undefined);
    assert.strictEqual(payload.google_ads, undefined);
    assert.ok(payload.pinterest !== undefined);

    // On purchase: all four should be included
    EDL.pushEvent('purchase', {
      currency: 'EUR',
      value: 13.00,
      items: [item]
    });
    payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(payload.meta !== undefined);
    assert.ok(payload.tiktok !== undefined);
    assert.ok(payload.google_ads !== undefined);
    assert.ok(payload.pinterest !== undefined);
  });

  it('13. Pinterest Ads: Full schema verification across standard and custom ecommerce events', function () {
    const item1 = {
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

    // 1. view_item -> PageVisit
    EDL.pushEvent('view_item', { currency: 'EUR', value: 26.00, items: [item1] });
    let p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'PageVisit');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.currency, 'EUR');
    assert.strictEqual(p.value, 26.00);
    assert.strictEqual(p.order_quantity, 2);
    assert.deepStrictEqual(p.product_ids, ['68_299']);

    // 2. view_item_list -> ViewCategory
    EDL.pushEvent('view_item_list', { currency: 'EUR', value: 26.00, item_list_name: 'Trinkbecher', items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'ViewCategory');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.category_name, 'Trinkbecher');

    // 3. add_to_cart -> AddToCart
    EDL.pushEvent('add_to_cart', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'AddToCart');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.order_quantity, 2);

    // 4. remove_from_cart -> Custom (RemoveFromCart)
    EDL.pushEvent('remove_from_cart', { currency: 'EUR', value: 13.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'Custom');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.custom_event_name, 'RemoveFromCart');

    // 4b. view_cart -> Custom (ViewCart)
    EDL.pushEvent('view_cart', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'Custom');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.custom_event_name, 'ViewCart');
    assert.strictEqual(p.order_quantity, 2);

    // 5. begin_checkout -> InitiateCheckout
    EDL.pushEvent('begin_checkout', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'InitiateCheckout');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.custom_event_name, undefined);

    // 6. add_shipping_info -> Custom (AddShippingInfo) with property
    EDL.pushEvent('add_shipping_info', { currency: 'EUR', value: 26.00, shipping_tier: 'DHL Express', items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'Custom');
    assert.strictEqual(p.custom_event_name, 'AddShippingInfo');
    assert.strictEqual(p.property, 'DHL Express');

    // 7. add_payment_info -> AddPaymentInfo with property
    EDL.pushEvent('add_payment_info', { currency: 'EUR', value: 26.00, payment_type: 'Credit Card', items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'AddPaymentInfo');
    assert.strictEqual(p.custom_event_name, undefined);
    assert.strictEqual(p.property, 'Credit Card');

    // 8. add_to_wishlist -> AddToWishList
    EDL.pushEvent('add_to_wishlist', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'AddToWishList');
    assert.strictEqual(p.custom_event_name, undefined);

    // 9. purchase -> Checkout with order_id
    EDL.pushEvent('purchase', { transaction_id: 'ORD-9988', currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.strictEqual(p.event_name, 'Checkout');
    assert.strictEqual(p.capi_event_name, undefined);
    assert.strictEqual(p.order_id, 'ORD-9988');
    assert.strictEqual(p.order_quantity, 2);
    assert.strictEqual(p.line_items[0].product_id, '68_299');
    assert.strictEqual(p.line_items[0].product_name, 'Trink und Snack Tasse');
    assert.strictEqual(p.line_items[0].product_variant, 'Farbe: Stahlblau');
    assert.strictEqual(p.contents, undefined, 'Pinterest contents must be omitted');
  });

  it('14. Pinterest Ads: Catalog ID Strategy parity (composite vs reference_sku vs id_product)', function () {
    const item = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 1
    };

    // composite strategy
    global.window.psGtmConfig.catalogIdStrategy = 'composite';
    EDL.pushEvent('view_item', { currency: 'EUR', value: 13.00, items: [item] });
    let p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.deepStrictEqual(p.product_ids, ['68_299']);
    assert.strictEqual(p.line_items[0].product_id, '68_299');

    // reference_sku strategy
    global.window.psGtmConfig.catalogIdStrategy = 'reference_sku';
    EDL.pushEvent('view_item', { currency: 'EUR', value: 13.00, items: [item] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.deepStrictEqual(p.product_ids, ['BHK125-B']);
    assert.strictEqual(p.line_items[0].product_id, 'BHK125-B');

    // id_product strategy
    global.window.psGtmConfig.catalogIdStrategy = 'id_product';
    EDL.pushEvent('view_item', { currency: 'EUR', value: 13.00, items: [item] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1].pinterest;
    assert.deepStrictEqual(p.product_ids, ['68']);
    assert.strictEqual(p.line_items[0].product_id, '68');
  });

  it('15. Pinterest Ads: DataLayer purge resets pinterest object between consecutive events', function () {
    const item = { item_id: '1033', item_name: 'Sonnenblenden fürs Auto', price: 9.99, quantity: 1 };
    
    // Push an event
    EDL.pushEvent('view_item', { currency: 'EUR', value: 9.99, items: [item] });
    
    // Inspect dataLayer history: previous push before final payload must be purge push with pinterest: null
    const len = global.window.dataLayer.length;
    const purgePush = global.window.dataLayer[len - 2];
    assert.strictEqual(purgePush.ecommerce, null);
    assert.strictEqual(purgePush.meta, null);
    assert.strictEqual(purgePush.tiktok, null);
    assert.strictEqual(purgePush.google_ads, null);
    assert.strictEqual(purgePush.pinterest, null);
  });

  it('16. Meta & TikTok: Full schema verification across all standard and custom ecommerce events', function () {
    const item1 = {
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

    // 1. view_item -> Meta: ViewContent, TikTok: ViewContent
    EDL.pushEvent('view_item', { currency: 'EUR', value: 26.00, items: [item1] });
    let p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'ViewContent');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'ViewContent');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 2. view_item_list -> Meta: ViewCategory, TikTok: ViewContent
    EDL.pushEvent('view_item_list', { currency: 'EUR', value: 26.00, item_list_name: 'Trinkbecher', items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'ViewCategory');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'ViewContent');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 3. add_to_cart -> Meta: AddToCart, TikTok: AddToCart
    EDL.pushEvent('add_to_cart', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'AddToCart');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'AddToCart');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 4. remove_from_cart -> Meta: Custom (RemoveFromCart), TikTok: Custom (RemoveFromCart)
    EDL.pushEvent('remove_from_cart', { currency: 'EUR', value: 13.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'Custom');
    assert.strictEqual(p.meta.custom_event_name, 'RemoveFromCart');
    assert.strictEqual(p.tiktok.event_name, 'Custom');
    assert.strictEqual(p.tiktok.custom_event_name, 'RemoveFromCart');

    // 4b. view_cart -> Meta: Custom (ViewCart), TikTok: ViewContent
    EDL.pushEvent('view_cart', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'Custom');
    assert.strictEqual(p.meta.custom_event_name, 'ViewCart');
    assert.strictEqual(p.tiktok.event_name, 'ViewContent');

    // 5. begin_checkout -> Meta: InitiateCheckout, TikTok: InitiateCheckout
    EDL.pushEvent('begin_checkout', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'InitiateCheckout');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'InitiateCheckout');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 6. add_shipping_info -> Meta: AddShippingInfo, TikTok: AddShippingInfo
    EDL.pushEvent('add_shipping_info', { currency: 'EUR', value: 26.00, shipping_tier: 'DHL Express', items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'AddShippingInfo');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'AddShippingInfo');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 7. add_payment_info -> Meta: AddPaymentInfo, TikTok: AddPaymentInfo
    EDL.pushEvent('add_payment_info', { currency: 'EUR', value: 26.00, payment_type: 'Credit Card', items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'AddPaymentInfo');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'AddPaymentInfo');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 8. add_to_wishlist -> Meta: AddToWishlist, TikTok: AddToWishlist
    EDL.pushEvent('add_to_wishlist', { currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'AddToWishlist');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.event_name, 'AddToWishlist');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);

    // 9. purchase -> Meta: Purchase, TikTok: CompletePayment
    EDL.pushEvent('purchase', { transaction_id: 'ORD-5544', currency: 'EUR', value: 26.00, items: [item1] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.meta.event_name, 'Purchase');
    assert.strictEqual(p.meta.capi_event_name, undefined);
    assert.strictEqual(p.meta.order_id, 'ORD-5544');
    assert.strictEqual(p.tiktok.event_name, 'CompletePayment');
    assert.strictEqual(p.tiktok.capi_event_name, undefined);
    assert.strictEqual(p.tiktok.order_id, 'ORD-5544');
  });

  it('17. view_search_results: GA4 search schema, Meta Search (search_string), TikTok Search (query), Pinterest Search (search_query), and Google Ads Dynamic Remarketing', function () {
    EDL.setEventPrefix('raihan');

    const searchItem = {
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

    EDL.pushEvent('view_search_results', {
      search_term: 'Trinkbecher blau',
      search_results_count: 5,
      currency: 'EUR',
      value: 13.00,
      items: [searchItem]
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    // GA4 Core Schema & Prefixing
    assert.strictEqual(payload.event, 'raihan_view_search_results');
    assert.strictEqual(payload.original_event, 'view_search_results');
    assert.strictEqual(payload.search_term, 'Trinkbecher blau');
    assert.strictEqual(payload.search_results_count, 5);
    assert.strictEqual(payload.ecommerce.search_term, 'Trinkbecher blau');
    assert.strictEqual(payload.ecommerce.currency, 'EUR');
    assert.strictEqual(payload.ecommerce.value, 13.00);
    assert.strictEqual(payload.ecommerce.items.length, 1);
    assert.strictEqual(payload.ecommerce.items[0].item_id, '68');

    // Meta Pixel & CAPI namespace
    assert.ok(payload.meta, 'meta namespace must exist on view_search_results');
    assert.strictEqual(payload.meta.event_name, 'Search');
    assert.strictEqual(payload.meta.search_string, 'Trinkbecher blau');
    assert.deepStrictEqual(payload.meta.content_ids, ['68_299']);
    assert.strictEqual(payload.meta.currency, 'EUR');
    assert.strictEqual(payload.meta.value, 13.00);
    assert.strictEqual(payload.meta.contents.length, 1);
    assert.deepStrictEqual(payload.meta.contents[0], {
      id: '68_299',
      quantity: 1,
      item_price: 13.00
    });

    // TikTok Events API namespace
    assert.ok(payload.tiktok, 'tiktok namespace must exist on view_search_results');
    assert.strictEqual(payload.tiktok.event_name, 'Search');
    assert.strictEqual(payload.tiktok.search_string, 'Trinkbecher blau');
    assert.strictEqual(payload.tiktok.query, 'Trinkbecher blau');
    assert.strictEqual(payload.tiktok.content_id, '68_299');
    assert.deepStrictEqual(payload.tiktok.content_ids, ['68_299']);
    assert.strictEqual(payload.tiktok.currency, 'EUR');
    assert.strictEqual(payload.tiktok.value, 13.00);
    assert.strictEqual(payload.tiktok.contents.length, 1);
    assert.strictEqual(payload.tiktok.contents[0].content_id, '68_299');

    // Pinterest Tag & Conversions API namespace
    assert.ok(payload.pinterest, 'pinterest namespace must exist on view_search_results');
    assert.strictEqual(payload.pinterest.event_name, 'Search');
    assert.strictEqual(payload.pinterest.search_query, 'Trinkbecher blau');
    assert.deepStrictEqual(payload.pinterest.product_ids, ['68_299']);
    assert.strictEqual(payload.pinterest.line_items.length, 1);
    assert.strictEqual(payload.pinterest.line_items[0].product_id, '68_299');
    assert.strictEqual(payload.pinterest.contents, undefined, 'Pinterest contents must be omitted');

    // Google Ads namespace on view_search_results
    assert.ok(payload.google_ads, 'google_ads namespace must exist on view_search_results');
    assert.strictEqual(payload.google_ads.event_name, 'view_search_results');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.event_name, 'view_search_results');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'searchresults');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['68_299']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.search_term, 'Trinkbecher blau');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.items, [
      { id: '68_299', google_business_vertical: 'retail' }
    ]);
    assert.strictEqual(payload.google_ads.conversion_data, undefined, 'conversion_data must be removed in favor of cart_data');
    assert.deepStrictEqual(payload.google_ads.cart_data.items, [
      { id: '68_299', quantity: 1, price: 13.00 }
    ]);
  });

  it('18. Third-party theme search controllers (Warehouse/iqitsearch, Leo Search): Emits view_search_results with proper prefix and search parameters', function () {
    EDL.setEventPrefix('ps');

    const searchItems = [
      {
        item_id: '1066',
        item_name: 'Chicco Natural Feeling Glas-Babyflasche, 150 ml',
        item_brand: 'Chicco',
        item_category: 'Babyflasche',
        price: 10.92,
        quantity: 1
      },
      {
        item_id: '1186',
        item_name: 'Miffy – Baby-Gehörschutz Weiß',
        item_brand: 'Dooky',
        item_category: 'Gehörschutz',
        price: 21.00,
        quantity: 1
      }
    ];

    EDL.pushEvent('view_search_results', {
      search_term: 'baby',
      search_results_count: 139,
      currency: 'EUR',
      value: 31.92,
      items: searchItems
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    assert.strictEqual(payload.event, 'ps_view_search_results');
    assert.strictEqual(payload.original_event, 'view_search_results');
    assert.strictEqual(payload.search_term, 'baby');
    assert.strictEqual(payload.search_results_count, 139);
    assert.strictEqual(payload.ecommerce.items.length, 2);
    assert.strictEqual(payload.meta.event_name, 'Search');
    assert.strictEqual(payload.meta.search_string, 'baby');
    assert.strictEqual(payload.tiktok.event_name, 'Search');
    assert.strictEqual(payload.tiktok.search_string, 'baby');
    assert.strictEqual(payload.tiktok.query, 'baby');
    assert.strictEqual(payload.pinterest.event_name, 'Search');
    assert.strictEqual(payload.pinterest.search_query, 'baby');
    assert.ok(payload.google_ads, 'google_ads namespace must exist on view_search_results');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'searchresults');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['1066', '1186']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 31.92);
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.items, [
      { id: '1066', google_business_vertical: 'retail' },
      { id: '1186', google_business_vertical: 'retail' }
    ]);
    assert.strictEqual(payload.google_ads.ecomm_pagetype, undefined);
  });

  it('19. Search Results Listing view_item_list isolation: Emits Google Ads Dynamic Remarketing (ecomm_pagetype: searchresults) and strictly excludes false ViewCategory / ViewContent on Meta, TikTok, and Pinterest', function () {
    EDL.setEventPrefix('ps');

    const searchListingItems = [
      {
        item_id: '1066',
        variant_id: '',
        sku_id: '00081211300000',
        item_name: 'Chicco Natural Feeling Glas-Babyflasche',
        item_brand: 'Chicco',
        item_category: 'Babyflasche',
        price: 12.99,
        quantity: 1,
        index: 1,
        item_list_id: 'search_results',
        item_list_name: 'Search: baby'
      },
      {
        item_id: '1186',
        variant_id: '',
        sku_id: '6501101',
        item_name: 'Miffy – Baby-Gehörschutz Weiß',
        item_brand: 'Dooky',
        item_category: 'Gehörschutz',
        price: 24.99,
        quantity: 1,
        index: 2,
        item_list_id: 'search_results',
        item_list_name: 'Search: baby'
      }
    ];

    EDL.pushEvent('view_item_list', {
      item_list_id: 'search_results',
      item_list_name: 'Search: baby',
      currency: 'EUR',
      value: 37.98,
      items: searchListingItems
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];

    assert.strictEqual(payload.event, 'ps_view_item_list');
    assert.strictEqual(payload.original_event, 'view_item_list');
    assert.strictEqual(payload.ecommerce.item_list_id, 'search_results');
    assert.strictEqual(payload.ecommerce.item_list_name, 'Search: baby');
    assert.strictEqual(payload.ecommerce.items.length, 2);

    // Google Ads Remarketing for search results
    assert.ok(payload.google_ads, 'Google Ads namespace must exist on search listing view_item_list');
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_pagetype, 'searchresults');
    assert.deepStrictEqual(payload.google_ads.dynamic_remarketing.ecomm_prodid, ['1066', '1186']);
    assert.strictEqual(payload.google_ads.dynamic_remarketing.ecomm_totalvalue, 37.98);
    assert.strictEqual(payload.google_ads.ecomm_pagetype, undefined);

    // Strict Advertising Isolation: Meta, TikTok, Pinterest MUST NOT fire false ViewCategory/ViewContent on search listings
    assert.strictEqual(payload.meta, undefined, 'Meta ViewCategory must be strictly suppressed on search results listing');
    assert.strictEqual(payload.tiktok, undefined, 'TikTok ViewContent must be strictly suppressed on search results listing');
    assert.strictEqual(payload.pinterest, undefined, 'Pinterest ViewCategory must be strictly suppressed on search results listing');
  });

  it('20. Event-Level Platform Routing Matrix: Platform-Based Expandable Interface Structure & Toggle Integrity', function () {
    const fs = require('fs');
    const path = require('path');
    const phpCode = fs.readFileSync(path.join(__dirname, '..', 'ps_gtm_datalayer.php'), 'utf8').replace(/\r\n/g, '\n');

    // 1. Verify getAdvertisingConfigForm replaces old table with new expandable matrix
    assert.ok(phpCode.includes("'name' => 'PS_GTM_ADV_MATRIX_TABLE'"), 'PS_GTM_ADV_MATRIX_TABLE free field must be defined');
    assert.ok(!phpCode.includes("'type' => 'switch',\n                        'label' => $this->l('Meta Pixel & Conversions API (CAPI)')"), 'Redundant separate switches must be integrated directly into platform panels');

    // 2. Verify all 4 supported platforms exist in renderAdvertisingMatrixTable
    const supportedPlatforms = ['meta', 'gads', 'tiktok', 'pinterest'];
    for (const p of supportedPlatforms) {
      assert.ok(phpCode.includes(`'key' => '${p}'`), `Platform ${p} must be defined in platforms matrix`);
    }

    assert.ok(phpCode.includes("psGtmPlatformSelectAll(\\'' . $pKey . '\\', true)"), 'Enable all events button must exist');
    assert.ok(phpCode.includes("psGtmPlatformSelectAll(\\'' . $pKey . '\\', false)"), 'Disable all events button must exist');
    assert.ok(phpCode.includes('id="card_\' . $pKey . \'"'), 'card_$pKey ID must exist');
    assert.ok(phpCode.includes('id="body_\' . $pKey . \'"'), 'body_$pKey accordion body must exist');
    assert.ok(phpCode.includes('id="counter_\' . $pKey . \'"'), 'counter_$pKey active events counter must exist');
    assert.ok(phpCode.includes('id="badge_\' . $pKey . \'"'), 'badge_$pKey status badge must exist');
    assert.ok(phpCode.includes('id="master_\' . $pKey . \'"'), 'master_$pKey master toggle switch must exist');
    assert.ok(phpCode.includes('id="chevron_\' . $pKey . \'"'), 'chevron_$pKey accordion chevron must exist');

    // 3. Verify Google Ads Dynamic Remarketing on view_search_results has active specification
    assert.ok(phpCode.includes("'gads_spec' => 'event: view_search_results, ecomm_pagetype: searchresults, ecomm_prodid: [ids], search_term, items'"), 'Google Ads on view_search_results must have active specification');

    // 4. Verify interactive JavaScript functions are defined
    assert.ok(phpCode.includes('function psGtmToggleAccordion(platformKey)'), 'psGtmToggleAccordion function must be present');
    assert.ok(phpCode.includes('function psGtmExpandAllPanels(expand)'), 'psGtmExpandAllPanels function must be present');
    assert.ok(phpCode.includes('function psGtmOnMasterToggle(platformKey, isChecked)'), 'psGtmOnMasterToggle function must be present');
    assert.ok(phpCode.includes('function psGtmPlatformSelectAll(platformKey, state)'), 'psGtmPlatformSelectAll function must be present');
    assert.ok(phpCode.includes('function psGtmOnEventToggle(platformKey)'), 'psGtmOnEventToggle function must be present');
    assert.ok(phpCode.includes('function psGtmUpdateCounter(platformKey)'), 'psGtmUpdateCounter function must be present');
    assert.ok(phpCode.includes('function psGtmToggleAllPlatforms(state)'), 'psGtmToggleAllPlatforms function must be present');

    // 5. Verify accordion bodies are collapsed by default
    assert.ok(phpCode.includes('.ps-gtm-card-body {\n                display: none;'), 'Platform accordion bodies must be collapsed (display: none) by default');
  });

  it('22. Pinterest Ads: Enhanced Matching user_data mapping and official standard events parity', function () {
    const crypto = require('crypto');
    function sha256(val) {
      return crypto.createHash('sha256').update(val).digest('hex');
    }

    const item = {
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

    const mockPinterestUserData = {
      em: sha256('customer@example.com'),
      ph: sha256('14155552671'),
      fn: sha256('john'),
      ln: sha256('doe'),
      ct: sha256('sanfrancisco'),
      st: sha256('ca'),
      zp: sha256('94105'),
      country: sha256('us')
    };

    // Test passing pinterestUserData via window.psGtmConfig
    global.window.psGtmConfig.pinterestUserData = mockPinterestUserData;

    EDL.pushEvent('purchase', {
      transaction_id: 'ORD-7744',
      currency: 'USD',
      value: 13.00,
      items: [item]
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(payload.pinterest, 'pinterest namespace must exist on purchase');
    assert.strictEqual(payload.pinterest.event_name, 'Checkout');
    assert.strictEqual(payload.pinterest.order_id, 'ORD-7744');
    assert.strictEqual(payload.pinterest.currency, 'USD');
    assert.strictEqual(payload.pinterest.value, 13.00);
    assert.strictEqual(payload.pinterest.contents, undefined, 'contents array must not be present');
    assert.strictEqual(payload.pinterest.event_id, undefined, 'event_id must not be present');
    assert.strictEqual(payload.pinterest.capi_event_name, undefined, 'capi_event_name must not be present');

    // Advanced Matching user_data
    assert.ok(payload.pinterest.user_data, 'pinterest.user_data must be present for Enhanced Matching');
    assert.strictEqual(payload.pinterest.user_data.em, mockPinterestUserData.em);
    assert.strictEqual(payload.pinterest.user_data.ph, mockPinterestUserData.ph);
    assert.strictEqual(payload.pinterest.user_data.fn, mockPinterestUserData.fn);
    assert.strictEqual(payload.pinterest.user_data.ln, mockPinterestUserData.ln);
    assert.strictEqual(payload.pinterest.user_data.ct, mockPinterestUserData.ct);
    assert.strictEqual(payload.pinterest.user_data.st, mockPinterestUserData.st);
    assert.strictEqual(payload.pinterest.user_data.zp, mockPinterestUserData.zp);
    assert.strictEqual(payload.pinterest.user_data.country, mockPinterestUserData.country);

    // Verify CustomerDataEngine PHP backend code implements the static helpers
    const fs = require('fs');
    const path = require('path');
    const engineCode = fs.readFileSync(path.join(__dirname, '..', 'classes', 'CustomerDataEngine.php'), 'utf8');
    assert.ok(engineCode.includes('public static function buildPinterestUserData'), 'buildPinterestUserData method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function hashPinterestUserData'), 'hashPinterestUserData method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function hashPinterestField'), 'hashPinterestField method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes("hash('sha256', $clean)"), 'SHA-256 hashing must be performed in hashPinterestField');
  });

  it('23. TikTok Ads: Advanced Matching user_data mapping (email, phone_number, strictly omitting external_id)', function () {
    const crypto = require('crypto');
    function sha256(val) {
      return crypto.createHash('sha256').update(val).digest('hex');
    }

    const item = {
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

    const mockTikTokUserData = {
      email: sha256('customer@example.com'),
      phone_number: sha256('+14155552671')
    };

    // Test passing tiktokUserData via window.psGtmConfig
    global.window.psGtmConfig.tiktokUserData = mockTikTokUserData;

    EDL.pushEvent('purchase', {
      transaction_id: 'ORD-7755',
      currency: 'USD',
      value: 13.00,
      items: [item]
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(payload.tiktok, 'tiktok namespace must exist on purchase');
    assert.strictEqual(payload.tiktok.event_name, 'CompletePayment');
    assert.strictEqual(payload.tiktok.order_id, 'ORD-7755');
    assert.strictEqual(payload.tiktok.currency, 'USD');
    assert.strictEqual(payload.tiktok.value, 13.00);

    // Advanced Matching user_data
    assert.ok(payload.tiktok.user_data, 'tiktok.user_data must be present for Advanced Matching');
    assert.strictEqual(payload.tiktok.user_data.email, mockTikTokUserData.email);
    assert.strictEqual(payload.tiktok.user_data.phone_number, mockTikTokUserData.phone_number);
    assert.strictEqual(payload.tiktok.user_data.external_id, undefined, 'external_id must be strictly omitted per configuration');

    // Verify CustomerDataEngine PHP backend code implements the TikTok static helpers
    const fs = require('fs');
    const path = require('path');
    const engineCode = fs.readFileSync(path.join(__dirname, '..', 'classes', 'CustomerDataEngine.php'), 'utf8');
    assert.ok(engineCode.includes('public static function buildTikTokUserData'), 'buildTikTokUserData method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function hashTikTokUserData'), 'hashTikTokUserData method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function normalizeE164Phone'), 'normalizeE164Phone method must exist in CustomerDataEngine');
  });

  it('24. Meta Ads: Advanced Matching user_data mapping (em, ph, fn, ln, ct, st, zp, country, strictly omitting external_id and cookies)', function () {
    const crypto = require('crypto');
    function sha256(val) {
      return crypto.createHash('sha256').update(val).digest('hex');
    }

    const item = {
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

    const mockMetaUserData = {
      em: sha256('customer@example.com'),
      ph: sha256('14155552671'),
      fn: sha256('john'),
      ln: sha256('doe'),
      ct: sha256('sanfrancisco'),
      st: sha256('ca'),
      zp: sha256('94105'),
      country: sha256('us')
    };

    // Test passing metaUserData via window.psGtmConfig
    global.window.psGtmConfig.metaUserData = mockMetaUserData;

    EDL.pushEvent('purchase', {
      transaction_id: 'ORD-7766',
      currency: 'USD',
      value: 13.00,
      items: [item]
    });

    const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(payload.meta, 'meta namespace must exist on purchase');
    assert.strictEqual(payload.meta.event_name, 'Purchase');
    assert.strictEqual(payload.meta.order_id, 'ORD-7766');
    assert.strictEqual(payload.meta.currency, 'USD');
    assert.strictEqual(payload.meta.value, 13.00);

    // Advanced Matching user_data
    assert.ok(payload.meta.user_data, 'meta.user_data must be present for Advanced Matching');
    assert.strictEqual(payload.meta.user_data.em, mockMetaUserData.em);
    assert.strictEqual(payload.meta.user_data.ph, mockMetaUserData.ph);
    assert.strictEqual(payload.meta.user_data.fn, mockMetaUserData.fn);
    assert.strictEqual(payload.meta.user_data.ln, mockMetaUserData.ln);
    assert.strictEqual(payload.meta.user_data.ct, mockMetaUserData.ct);
    assert.strictEqual(payload.meta.user_data.st, mockMetaUserData.st);
    assert.strictEqual(payload.meta.user_data.zp, mockMetaUserData.zp);
    assert.strictEqual(payload.meta.user_data.country, mockMetaUserData.country);
    assert.strictEqual(payload.meta.user_data.external_id, undefined, 'external_id must be strictly omitted per instruction');
    assert.strictEqual(payload.meta.user_data.fbp, undefined, 'fbp must be strictly omitted per instruction');
    assert.strictEqual(payload.meta.user_data.fbc, undefined, 'fbc must be strictly omitted per instruction');

    // Verify CustomerDataEngine PHP backend code implements the Meta static helpers
    const fs = require('fs');
    const path = require('path');
    const engineCode = fs.readFileSync(path.join(__dirname, '..', 'classes', 'CustomerDataEngine.php'), 'utf8');
    assert.ok(engineCode.includes('public static function buildMetaUserData'), 'buildMetaUserData method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function hashMetaUserData'), 'hashMetaUserData method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function hashMetaField'), 'hashMetaField method must exist in CustomerDataEngine');
    assert.ok(engineCode.includes('public static function normalizeMetaPhone'), 'normalizeMetaPhone method must exist in CustomerDataEngine');
  });

  it('25. Search vs Product-List Event Separation: view_search_results fires strictly for search without view_item_list, and view_item_list fires strictly for genuine listings', function () {
    const fs = require('fs');
    const path = require('path');

    // 1. Reset dataLayer state
    global.window.dataLayer = [];
    EDL.setEventPrefix('ps');

    const searchProduct = {
      item_id: '1066',
      sku_id: '00081211300000',
      item_name: 'Chicco Natural Feeling Glas-Babyflasche',
      item_brand: 'Chicco',
      item_category: 'Babyflasche',
      price: 10.92,
      quantity: 1,
      index: 1
    };

    // Scenario 1: User performs a search for "baby"
    EDL.pushEvent('view_search_results', {
      search_term: 'baby',
      search_results_count: 5,
      currency: 'EUR',
      value: 10.92,
      items: [searchProduct]
    });

    const pushedEvents = global.window.dataLayer.filter(p => p && p.event);
    assert.strictEqual(pushedEvents.length, 1, 'Exactly one ecommerce event must be pushed to dataLayer on search');
    const searchPayload = pushedEvents[0];

    assert.strictEqual(searchPayload.event, 'ps_view_search_results');
    assert.strictEqual(searchPayload.original_event, 'view_search_results');
    assert.strictEqual(searchPayload.search_term, 'baby');
    assert.strictEqual(searchPayload.search_results_count, 5);
    assert.strictEqual(searchPayload.ecommerce.search_term, 'baby');
    assert.strictEqual(searchPayload.ecommerce.items.length, 1);
    assert.strictEqual(searchPayload.ecommerce.items[0].item_list_id, undefined, 'Search result items must not have synthetic item_list_id');
    assert.strictEqual(searchPayload.ecommerce.items[0].item_list_name, undefined, 'Search result items must not have synthetic item_list_name');

    // Scenario 2: User opens a category listing page
    global.window.dataLayer = [];
    const categoryProduct = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Dooky',
      item_category: 'Trinkbecher',
      price: 13.00,
      quantity: 1,
      index: 1,
      item_list_id: 'category_12',
      item_list_name: 'Trinkbecher'
    };

    EDL.pushEvent('view_item_list', {
      item_list_id: 'category_12',
      item_list_name: 'Trinkbecher',
      currency: 'EUR',
      value: 13.00,
      items: [categoryProduct]
    });

    const categoryEvents = global.window.dataLayer.filter(p => p && p.event);
    assert.strictEqual(categoryEvents.length, 1, 'Exactly one ecommerce event must be pushed on category page');
    const listPayload = categoryEvents[0];

    assert.strictEqual(listPayload.event, 'ps_view_item_list');
    assert.strictEqual(listPayload.original_event, 'view_item_list');
    assert.strictEqual(listPayload.ecommerce.item_list_id, 'category_12');
    assert.strictEqual(listPayload.ecommerce.item_list_name, 'Trinkbecher');
    assert.strictEqual(listPayload.meta.event_name, 'ViewCategory');
    assert.strictEqual(listPayload.tiktok.event_name, 'ViewContent');
    assert.strictEqual(listPayload.pinterest.event_name, 'ViewCategory');
    assert.strictEqual(listPayload.google_ads.dynamic_remarketing.ecomm_pagetype, 'category');

    // Scenario 3: Verify backend source architecture
    const payloadBuilderCode = fs.readFileSync(path.join(__dirname, '..', 'classes', 'PayloadBuilder.php'), 'utf8');
    const moduleMainCode = fs.readFileSync(path.join(__dirname, '..', 'ps_gtm_datalayer.php'), 'utf8');

    // PayloadBuilder must route search directly to buildViewSearchResultsPayload and not buildViewItemListPayload
    assert.ok(payloadBuilderCode.includes("if (self::isSearchController($controller, $this->context)) {\n            $query = self::getSearchQuery();\n            return $this->buildViewSearchResultsPayload($query, null, [], true);\n        }"), 'buildPagePayload must directly return buildViewSearchResultsPayload on search controllers');
    assert.ok(!payloadBuilderCode.includes("$listId = 'search_results';\n                $listName = 'Search: ' . $query;"), 'buildViewItemListPayload must not contain searchresults branches');

    // ps_gtm_datalayer.php must not push both search and list payloads
    assert.ok(!moduleMainCode.includes("$initialPayloads[] = $searchPayload;\n                    }\n                }\n            }\n            if ($serverPayload) {\n                $initialPayloads[] = $serverPayload;"), 'ps_gtm_datalayer.php must not push both searchPayload and serverPayload');
    assert.ok(!moduleMainCode.includes("$pageContext['list_id'] = 'search_results';"), 'ps_gtm_datalayer.php must not assign list_id: search_results');
  });

  it('26. Google Ads Data Layer: Conversions with Cart Data & Retail Dynamic Remarketing Separate Items Schema', function () {
    global.window.dataLayer = [];
    global.window.psGtmConfig = global.window.psGtmConfig || {};
    global.window.psGtmConfig.advertisingSettings = {
      master_enabled: true,
      gads_enabled: true,
      gads_cart_data: true,
      gads_merchant_id: '123456789'
    };

    const testProducts = [
      {
        item_id: '1149',
        variant_id: '299',
        sku_id: '2301701',
        item_name: 'Sicherheitsset – Starterpaket für Zuhause',
        item_brand: 'Dooky',
        item_category: 'Sicherheit',
        price: 19.99,
        quantity: 2
      },
      {
        item_id: '1033',
        variant_id: '',
        sku_id: '5128231',
        item_name: 'Sonnenblenden fürs Auto',
        item_brand: 'Dooky',
        item_category: 'Auto & Unterwegs',
        price: 9.99,
        quantity: 1
      }
    ];

    // 1. view_item: BOTH dynamic_remarketing items AND cart_data items present; zero conversion_data and zero duplicate root properties
    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: 19.99,
      items: [testProducts[0]]
    });

    let lastEvent = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(lastEvent.google_ads, 'google_ads must exist on view_item');
    assert.strictEqual(lastEvent.google_ads.event_name, 'view_item');
    assert.strictEqual(lastEvent.google_ads.conversion_data, undefined, 'conversion_data must be removed');
    assert.strictEqual(lastEvent.google_ads.ecomm_pagetype, undefined, 'duplicate root ecomm_pagetype must be removed');
    assert.strictEqual(lastEvent.google_ads.ecomm_prodid, undefined, 'duplicate root ecomm_prodid must be removed');
    assert.strictEqual(lastEvent.google_ads.ecomm_totalvalue, undefined, 'duplicate root ecomm_totalvalue must be removed');
    assert.strictEqual(lastEvent.google_ads.items, undefined, 'duplicate root items must be removed');
    assert.strictEqual(lastEvent.google_ads.dynamic_remarketing.event_name, 'view_item');
    assert.strictEqual(lastEvent.google_ads.dynamic_remarketing.value, 19.99);
    assert.strictEqual(lastEvent.google_ads.dynamic_remarketing.currency, 'EUR');
    assert.deepStrictEqual(lastEvent.google_ads.dynamic_remarketing.items, [
      { id: '1149_299', google_business_vertical: 'retail' }
    ]);
    assert.deepStrictEqual(lastEvent.google_ads.cart_data.items, [
      { id: '1149_299', quantity: 2, price: 19.99 }
    ]);

    // 2. add_to_cart: BOTH dynamic_remarketing items AND cart_data items present
    global.window.dataLayer = [];
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 39.98,
      items: [testProducts[0]]
    });
    lastEvent = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(lastEvent.google_ads.event_name, 'add_to_cart');
    assert.strictEqual(lastEvent.google_ads.conversion_data, undefined);
    assert.strictEqual(lastEvent.google_ads.ecomm_pagetype, undefined);
    assert.deepStrictEqual(lastEvent.google_ads.dynamic_remarketing.items, [
      { id: '1149_299', google_business_vertical: 'retail' }
    ]);
    assert.deepStrictEqual(lastEvent.google_ads.cart_data.items, [
      { id: '1149_299', quantity: 2, price: 19.99 }
    ]);

    // 3. purchase: BOTH dynamic_remarketing items AND cart_data items, kept strictly separate
    global.window.dataLayer = [];
    EDL.pushEvent('purchase', {
      transaction_id: '1042',
      value: 49.97,
      currency: 'EUR',
      discount: 5.00,
      aw_feed_country: 'DE',
      aw_feed_language: 'de',
      new_customer: false,
      customer_lifetime_value: 129.50,
      items: testProducts
    });
    lastEvent = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(lastEvent.google_ads, 'google_ads must exist on purchase');
    assert.strictEqual(lastEvent.google_ads.event_name, 'purchase');
    assert.strictEqual(lastEvent.new_customer, false);
    assert.strictEqual(lastEvent.customer_lifetime_value, 129.50);
    assert.strictEqual(lastEvent.google_ads.conversion_data, undefined);
    assert.strictEqual(lastEvent.google_ads.ecomm_pagetype, undefined);

    // Verify dynamic_remarketing items schema
    assert.deepStrictEqual(lastEvent.google_ads.dynamic_remarketing.items, [
      { id: '1149_299', google_business_vertical: 'retail' },
      { id: '1033', google_business_vertical: 'retail' }
    ]);

    // Verify cart_data items schema
    assert.ok(lastEvent.google_ads.cart_data, 'cart_data must exist on purchase');
    assert.strictEqual(lastEvent.google_ads.cart_data.transaction_id, '1042');
    assert.strictEqual(lastEvent.google_ads.cart_data.value, 49.97);
    assert.strictEqual(lastEvent.google_ads.cart_data.currency, 'EUR');
    assert.strictEqual(lastEvent.google_ads.cart_data.discount, 5.00);
    assert.strictEqual(lastEvent.google_ads.cart_data.aw_merchant_id, '123456789');
    assert.strictEqual(lastEvent.google_ads.cart_data.aw_feed_country, 'DE');
    assert.strictEqual(lastEvent.google_ads.cart_data.aw_feed_language, 'de');
    assert.strictEqual(lastEvent.google_ads.cart_data.new_customer, false);
    assert.strictEqual(lastEvent.google_ads.cart_data.customer_lifetime_value, 129.50);
    assert.deepStrictEqual(lastEvent.google_ads.cart_data.items, [
      { id: '1149_299', quantity: 2, price: 19.99 },
      { id: '1033', quantity: 1, price: 9.99 }
    ]);
  });

  it('27. Google Ads New Customer Acquisition & Customer Lifetime Value: Emits new_customer boolean and customer_lifetime_value numeric parameters on purchase', function () {
    global.window.dataLayer = [];
    EDL.setEventPrefix('ps');

    // Scenario A: First-time purchaser (New Customer)
    EDL.pushEvent('purchase', {
      transaction_id: '2001',
      value: 65.00,
      currency: 'EUR',
      new_customer: true,
      customer_lifetime_value: 65.00,
      items: [
        {
          item_id: '1066',
          item_name: 'Chicco Natural Feeling Glas-Babyflasche',
          price: 65.00,
          quantity: 1
        }
      ]
    }, {
      email: 'newbuyer@example.com',
      new_customer: true,
      customer_lifetime_value: 65.00,
      orders_count: 1
    });

    const newBuyerPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(newBuyerPayload.event, 'ps_purchase');
    assert.strictEqual(newBuyerPayload.new_customer, true);
    assert.strictEqual(newBuyerPayload.customer_lifetime_value, 65.00);
    assert.strictEqual(newBuyerPayload.google_ads.cart_data.new_customer, true);
    assert.strictEqual(newBuyerPayload.google_ads.cart_data.customer_lifetime_value, 65.00);
    assert.strictEqual(newBuyerPayload.user_data.new_customer, true);
    assert.strictEqual(newBuyerPayload.user_data.customer_lifetime_value, 65.00);
    assert.strictEqual(newBuyerPayload.user_data.orders_count, 1);

    // Scenario B: Returning customer (Existing repeat buyer)
    global.window.dataLayer = [];
    EDL.pushEvent('purchase', {
      transaction_id: '2002',
      value: 40.00,
      currency: 'EUR',
      new_customer: false,
      customer_lifetime_value: 215.50,
      items: [
        {
          item_id: '1186',
          item_name: 'Miffy – Baby-Gehörschutz Weiß',
          price: 40.00,
          quantity: 1
        }
      ]
    }, {
      email: 'repeat@example.com',
      new_customer: false,
      customer_lifetime_value: 215.50,
      orders_count: 4
    });

    const repeatBuyerPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(repeatBuyerPayload.event, 'ps_purchase');
    assert.strictEqual(repeatBuyerPayload.new_customer, false);
    assert.strictEqual(repeatBuyerPayload.customer_lifetime_value, 215.50);
    assert.strictEqual(repeatBuyerPayload.google_ads.cart_data.new_customer, false);
    assert.strictEqual(repeatBuyerPayload.google_ads.cart_data.customer_lifetime_value, 215.50);
    assert.strictEqual(repeatBuyerPayload.user_data.new_customer, false);
    assert.strictEqual(repeatBuyerPayload.user_data.customer_lifetime_value, 215.50);
    assert.strictEqual(repeatBuyerPayload.user_data.orders_count, 4);
  });

  it('28. Microsoft Advertising (Bing Ads & UET): Full schema verification across standard and dynamic remarketing events', function () {
    // 1. view_item
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: 13.00,
      items: [
        {
          item_id: '68',
          variant_id: '299',
          sku_id: 'BHK125-B',
          item_name: 'Trink und Snack Tasse',
          item_brand: 'Dooky',
          item_category: 'Trinkbecher',
          item_variant: 'Farbe: Stahlblau',
          price: 13.00,
          quantity: 1
        }
      ]
    });

    const viewItemPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(viewItemPayload.microsoft_ads, 'microsoft_ads namespace must exist on view_item');
    assert.strictEqual(viewItemPayload.microsoft_ads.event_action, 'view_item');
    assert.strictEqual(viewItemPayload.microsoft_ads.revenue_value, 13.00);
    assert.strictEqual(viewItemPayload.microsoft_ads.currency, 'EUR');
    assert.strictEqual(viewItemPayload.microsoft_ads.items.length, 1);
    assert.strictEqual(viewItemPayload.microsoft_ads.items[0].id, '68_299');
    assert.strictEqual(viewItemPayload.microsoft_ads.items[0].name, 'Trink und Snack Tasse');
    assert.strictEqual(viewItemPayload.microsoft_ads.dynamic_remarketing.ecomm_pagetype, 'product');
    assert.deepStrictEqual(viewItemPayload.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68_299']);
    assert.strictEqual(viewItemPayload.microsoft_ads.dynamic_remarketing.ecomm_totalvalue, 13.00);

    // 2. add_to_cart
    global.window.dataLayer = [];
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 26.00,
      items: [
        {
          item_id: '68',
          variant_id: '299',
          price: 13.00,
          quantity: 2
        }
      ]
    });

    const addToCartPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(addToCartPayload.microsoft_ads.event_action, 'add_to_cart');
    assert.strictEqual(addToCartPayload.microsoft_ads.revenue_value, 26.00);
    assert.strictEqual(addToCartPayload.microsoft_ads.dynamic_remarketing.ecomm_pagetype, 'cart');
    assert.deepStrictEqual(addToCartPayload.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68_299']);

    // 3. begin_checkout
    global.window.dataLayer = [];
    EDL.pushEvent('begin_checkout', {
      currency: 'EUR',
      value: 35.99,
      items: [
        { item_id: '68', variant_id: '299', price: 13.00, quantity: 2 },
        { item_id: '15', variant_id: '', price: 9.99, quantity: 1 }
      ]
    });

    const checkoutPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(checkoutPayload.microsoft_ads.event_action, 'begin_checkout');
    assert.strictEqual(checkoutPayload.microsoft_ads.checkout_step, 1);
    assert.strictEqual(checkoutPayload.microsoft_ads.revenue_value, 35.99);
    assert.deepStrictEqual(checkoutPayload.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68_299', '15']);

    // 4. view_item_list
    global.window.dataLayer = [];
    EDL.pushEvent('view_item_list', {
      item_list_id: 'category_5',
      item_list_name: 'Trinkbecher',
      items: [
        { item_id: '68', variant_id: '299', price: 13.00 },
        { item_id: '72', variant_id: '', price: 15.00 }
      ]
    });

    const listPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(listPayload.microsoft_ads.event_action, 'view_item_list');
    assert.strictEqual(listPayload.microsoft_ads.dynamic_remarketing.ecomm_pagetype, 'category');
    assert.deepStrictEqual(listPayload.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68_299', '72']);

    // 5. view_search_results
    global.window.dataLayer = [];
    EDL.pushEvent('view_search_results', {
      search_term: 'baby',
      items: [
        { item_id: '68', variant_id: '299', price: 13.00 }
      ]
    });

    const searchPayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(searchPayload.microsoft_ads.event_action, 'view_search_results');
    assert.strictEqual(searchPayload.microsoft_ads.search_term, 'baby');
    assert.strictEqual(searchPayload.microsoft_ads.dynamic_remarketing.ecomm_pagetype, 'searchresults');
    assert.strictEqual(searchPayload.microsoft_ads.dynamic_remarketing.ecomm_query, 'baby');
    assert.deepStrictEqual(searchPayload.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68_299']);

    // 6. purchase
    global.window.dataLayer = [];
    EDL.pushEvent('purchase', {
      transaction_id: '1246',
      value: 19.20,
      tax: 3.79,
      shipping: 4.50,
      currency: 'EUR',
      coupon: 'SUMMER2026',
      items: [
        { item_id: '153', variant_id: '', sku_id: 'FIT09', price: 19.20, quantity: 1 }
      ]
    }, {
      email: 'ahamedr675@gmail.com'
    }, {
      microsoft_ads: {
        pid: {
          email: '21e179aa2c9bc86c844a7dafc32e9c89b5585f7db00b39bf2f734e4cef42cead'
        }
      }
    });

    const purchasePayload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(purchasePayload.microsoft_ads.event_action, 'purchase');
    assert.strictEqual(purchasePayload.microsoft_ads.transaction_id, '1246');
    assert.strictEqual(purchasePayload.microsoft_ads.revenue_value, 19.20);
    assert.strictEqual(purchasePayload.microsoft_ads.tax, 3.79);
    assert.strictEqual(purchasePayload.microsoft_ads.shipping, 4.50);
    assert.strictEqual(purchasePayload.microsoft_ads.coupon, 'SUMMER2026');
    assert.strictEqual(purchasePayload.microsoft_ads.dynamic_remarketing.ecomm_pagetype, 'purchase');
    assert.deepStrictEqual(purchasePayload.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['153']);
    assert.ok(purchasePayload.microsoft_ads.pid, 'pid should exist');
  });

  it('29. Microsoft Advertising: Catalog ID Strategy parity (composite vs reference_sku vs id_product)', function () {
    const item = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 1
    };

    // composite
    global.window.psGtmConfig.catalogIdStrategy = 'composite';
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', { currency: 'EUR', value: 13.00, items: [item] });
    let p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.microsoft_ads.items[0].id, '68_299');
    assert.deepStrictEqual(p.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68_299']);

    // reference_sku
    global.window.psGtmConfig.catalogIdStrategy = 'reference_sku';
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', { currency: 'EUR', value: 13.00, items: [item] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.microsoft_ads.items[0].id, 'BHK125-B');
    assert.deepStrictEqual(p.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['BHK125-B']);

    // id_product
    global.window.psGtmConfig.catalogIdStrategy = 'id_product';
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', { currency: 'EUR', value: 13.00, items: [item] });
    p = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(p.microsoft_ads.items[0].id, '68');
    assert.deepStrictEqual(p.microsoft_ads.dynamic_remarketing.ecomm_prodid, ['68']);
  });

  it('30. Microsoft Advertising: Master switch and routing matrix toggles & dataLayer purge reset', function () {
    const item = { item_id: '153', price: 19.20, quantity: 1 };

    // 1. Purge reset check
    global.window.psGtmConfig.catalogIdStrategy = 'composite';
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', { currency: 'EUR', value: 19.20, items: [item] });
    const resetPush = global.window.dataLayer[global.window.dataLayer.length - 2];
    assert.strictEqual(resetPush.microsoft_ads, null, 'Reset push must contain microsoft_ads: null');

    // 2. Individual platform toggle
    global.window.psGtmConfig.advertisingSettings = {
      master_enabled: true,
      microsoft_enabled: false,
      microsoft_events: ['view_item', 'purchase']
    };
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', { currency: 'EUR', value: 19.20, items: [item] });
    let payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.microsoft_ads, undefined, 'microsoft_ads should be suppressed when microsoft_enabled is false');

    // 3. Routing matrix toggle
    global.window.psGtmConfig.advertisingSettings = {
      master_enabled: true,
      microsoft_enabled: true,
      microsoft_events: ['purchase'] // view_item not enabled
    };
    global.window.dataLayer = [];
    EDL.pushEvent('view_item', { currency: 'EUR', value: 19.20, items: [item] });
    payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(payload.microsoft_ads, undefined, 'microsoft_ads should be omitted when view_item is not in microsoft_events');

    // But purchase is enabled
    global.window.dataLayer = [];
    EDL.pushEvent('purchase', { transaction_id: '999', value: 19.20, items: [item] });
    payload = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.ok(payload.microsoft_ads, 'microsoft_ads should be pushed on purchase when present in microsoft_events');
    assert.strictEqual(payload.microsoft_ads.event_action, 'purchase');
  });

});
