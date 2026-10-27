const { describe, it, beforeEach } = require('node:test');
const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Snapchat Ads Pixel & Conversions API (CAPI) Specification Test Suite', function () {

  beforeEach(function () {
    global.window = {
      dataLayer: [],
      psGtmConfig: {
        currency: 'EUR',
        currencyPrecision: 2,
        catalogIdStrategy: 'composite',
        advertisingSettings: {
          master_enabled: true,
          meta_enabled: true,
          tiktok_enabled: true,
          gads_enabled: true,
          pinterest_enabled: true,
          microsoft_enabled: true,
          openai_enabled: true,
          snapchat_enabled: true,
          snapchat_events: [
            'view_item_list',
            'view_search_results',
            'view_item',
            'select_item',
            'add_to_wishlist',
            'add_to_cart',
            'remove_from_cart',
            'view_cart',
            'begin_checkout',
            'add_shipping_info',
            'add_payment_info',
            'purchase'
          ]
        }
      }
    };
    EDL.setEventPrefix('');
  });

  // 1. Dual Sub-Object Structure & Event Mapping Tests
  describe('1. Dual Sub-Object Architecture (pixel + dynamic_ads)', function () {
    const singleProduct = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'SN-68-299',
      item_name: 'Snap Spectacles 3',
      item_brand: 'Snap Inc',
      item_category: 'Smart Eyewear',
      price: 380.00,
      quantity: 1
    };

    it('should build VIEW_CONTENT for view_item (PDP) with dual sub-objects', function () {
      const norm = EDL.normalizeItems([singleProduct]);
      EDL.pushEvent('view_item', {
        currency: 'EUR',
        value: 380.00,
        items: norm
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat, 'snapchat namespace must be present');
      assert.strictEqual(payload.snapchat.event_name, 'VIEW_CONTENT');
      assert.strictEqual(payload.snapchat.action_source, 'WEB');

      // Check pixel sub-object
      assert.ok(payload.snapchat.pixel, 'pixel sub-object must exist');
      assert.strictEqual(payload.snapchat.pixel.currency, 'EUR');
      assert.strictEqual(payload.snapchat.pixel.price, 380.00);
      assert.deepStrictEqual(payload.snapchat.pixel.item_ids, ['68_299']);
      assert.strictEqual(payload.snapchat.pixel.number_items, 1);
      assert.strictEqual(payload.snapchat.pixel.item_category, 'Smart Eyewear');
      assert.strictEqual(payload.snapchat.pixel.description, 'Snap Spectacles 3');

      // Check dynamic_ads sub-object
      assert.ok(payload.snapchat.dynamic_ads, 'dynamic_ads sub-object must exist');
      assert.strictEqual(payload.snapchat.dynamic_ads.currency, 'EUR');
      assert.strictEqual(payload.snapchat.dynamic_ads.value, 380.00);
      assert.strictEqual(payload.snapchat.dynamic_ads.content_type, 'product');
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.content_ids, ['68_299']);
      assert.strictEqual(payload.snapchat.dynamic_ads.num_items, 1);
      assert.strictEqual(payload.snapchat.dynamic_ads.content_name, 'Snap Spectacles 3');
      assert.strictEqual(payload.snapchat.dynamic_ads.content_category, 'Smart Eyewear');
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.brands, ['Snap Inc']);
      assert.strictEqual(payload.snapchat.dynamic_ads.contents.length, 1);
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.contents[0], {
        id: '68_299',
        quantity: 1,
        item_price: 380.00,
        brand: 'Snap Inc'
      });
    });

    it('should build LIST_VIEW for view_item_list with product_group content_type', function () {
      const items = [
        { item_id: '10', variant_id: '0', item_name: 'Item 1', item_brand: 'BrandA', price: 25.00, quantity: 1 },
        { item_id: '20', variant_id: '15', item_name: 'Item 2', item_brand: 'BrandB', price: 50.00, quantity: 1 }
      ];
      EDL.pushEvent('view_item_list', {
        item_list_id: 'cat_glasses',
        item_list_name: 'Eyewear Collection',
        currency: 'EUR',
        value: 75.00,
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat);
      assert.strictEqual(payload.snapchat.event_name, 'LIST_VIEW');
      assert.deepStrictEqual(payload.snapchat.pixel.item_ids, ['10', '20']);
      assert.strictEqual(payload.snapchat.pixel.number_items, 2);
      assert.strictEqual(payload.snapchat.dynamic_ads.content_type, 'product_group');
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.content_ids, ['10', '20']);
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.brands, ['BrandA', 'BrandB']);
      assert.strictEqual(payload.snapchat.dynamic_ads.contents.length, 2);
    });

    it('should build SEARCH for view_search_results with search_string in both sub-objects', function () {
      const items = [{ item_id: '12', item_name: 'Filter Shades', price: 45.00, quantity: 1 }];
      EDL.pushEvent('view_search_results', {
        search_term: 'sunglasses polarized',
        currency: 'EUR',
        value: 45.00,
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat);
      assert.strictEqual(payload.snapchat.event_name, 'SEARCH');
      assert.strictEqual(payload.snapchat.pixel.search_string, 'sunglasses polarized');
      assert.strictEqual(payload.snapchat.dynamic_ads.search_string, 'sunglasses polarized');
      assert.strictEqual(payload.snapchat.dynamic_ads.content_type, 'product');
    });

    it('should build ADD_CART for add_to_cart', function () {
      const item = {
        item_id: '68',
        variant_id: '299',
        item_name: 'Snap Spectacles 3',
        item_brand: 'Snap Inc',
        price: 380.00,
        quantity: 2
      };
      EDL.pushEvent('add_to_cart', {
        currency: 'EUR',
        value: 760.00,
        items: [item]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat);
      assert.strictEqual(payload.snapchat.event_name, 'ADD_CART');
      assert.strictEqual(payload.snapchat.pixel.price, 760.00);
      assert.strictEqual(payload.snapchat.pixel.number_items, 2);
      assert.strictEqual(payload.snapchat.dynamic_ads.value, 760.00);
      assert.strictEqual(payload.snapchat.dynamic_ads.num_items, 2);
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.contents[0], {
        id: '68_299',
        quantity: 2,
        item_price: 380.00,
        brand: 'Snap Inc'
      });
    });

    it('should build ADD_TO_WISHLIST for add_to_wishlist', function () {
      const item = { item_id: '5', item_name: 'Wish Item', price: 19.99, quantity: 1 };
      EDL.pushEvent('add_to_wishlist', {
        currency: 'EUR',
        value: 19.99,
        items: [item]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat);
      assert.strictEqual(payload.snapchat.event_name, 'ADD_TO_WISHLIST');
      assert.strictEqual(payload.snapchat.pixel.price, 19.99);
      assert.strictEqual(payload.snapchat.dynamic_ads.value, 19.99);
    });

    it('should build START_CHECKOUT for begin_checkout and CUSTOM_EVENT_3 for add_shipping_info', function () {
      const items = [{ item_id: '68', variant_id: '299', item_name: 'Glasses', price: 380.00, quantity: 1 }];
      EDL.pushEvent('begin_checkout', {
        currency: 'EUR',
        value: 380.00,
        items: items
      });

      let payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(payload.snapchat.event_name, 'START_CHECKOUT');
      assert.strictEqual(payload.snapchat.pixel.number_items, 1);

      EDL.pushEvent('add_shipping_info', {
        currency: 'EUR',
        value: 380.00,
        shipping_tier: 'Express Delivery',
        items: items
      });

      payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(payload.snapchat.event_name, 'CUSTOM_EVENT_3');
    });

    it('should build ADD_BILLING for add_payment_info', function () {
      const items = [{ item_id: '68', variant_id: '299', item_name: 'Glasses', price: 380.00, quantity: 1 }];
      EDL.pushEvent('add_payment_info', {
        currency: 'EUR',
        value: 380.00,
        payment_type: 'Credit Card',
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat);
      assert.strictEqual(payload.snapchat.event_name, 'ADD_BILLING');
      assert.strictEqual(payload.snapchat.pixel.price, 380.00);
      assert.strictEqual(payload.snapchat.dynamic_ads.value, 380.00);
    });

    it('should build PURCHASE with transaction_id / order_id and user_data', function () {
      const items = [{ item_id: '68', variant_id: '299', item_name: 'Glasses', price: 380.00, quantity: 1 }];
      const snapchatUserData = {
        em: 'f660ab912ec121d1b1e928a0bb4bc61b15f5ad44d5efdc4e1c92a25e99b8e44a',
        ph: '4638da0bbb4923e32fb9961dbd15582fc89d538f5f6cecf909d3b4ad205ff9ff',
        fn: '5c6b0208d4104931bb46f713ad38130f31ff160a5e320d63a9c7fb660c6c167b',
        ln: 'd751f76b33d539f5f12db4e3c006e7f05821591487321469f847312936f2dd4a',
        ct: 'b7a3297a7a9163ef2693845b410f7d54fd5b25ee4b03577d612ec9f4ea92b45f',
        st: '3f79bb7b435b05321651daefd374cdc681dc06faa65e374e38337b88ca14539f',
        zp: '071a76f251e39a3f2d019318a666ee32c19208a8d052a5a0fb70beab0ab326f5',
        country: 'fr'
      };

      EDL.pushEvent('purchase', {
        transaction_id: 'ORDER-9988',
        currency: 'EUR',
        value: 380.00,
        tax: 60.00,
        shipping: 10.00,
        items: items
      }, null, {
        snapchat_user_data: snapchatUserData
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.snapchat);
      assert.strictEqual(payload.snapchat.event_name, 'PURCHASE');
      assert.strictEqual(payload.snapchat.pixel.transaction_id, 'ORDER-9988');
      assert.strictEqual(payload.snapchat.dynamic_ads.order_id, 'ORDER-9988');
      assert.strictEqual(payload.snapchat.pixel.price, 380.00);
      assert.strictEqual(payload.snapchat.dynamic_ads.value, 380.00);
      assert.deepStrictEqual(payload.snapchat.user_data, snapchatUserData, 'snapchat.user_data must be passed directly');
    });
  });

  // 2. Catalog ID Matching Strategy Tests
  describe('2. Catalog ID Matching Strategies', function () {
    const item = {
      item_id: '42',
      variant_id: '108',
      sku_id: 'SKU-SNAP-42',
      item_name: 'Smart Frame',
      price: 199.00,
      quantity: 1
    };

    it('should use composite strategy (${item_id}_${variant_id}) when configured', function () {
      global.window.psGtmConfig.catalogIdStrategy = 'composite';
      EDL.pushEvent('view_item', { currency: 'EUR', value: 199.00, items: [item] });
      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.deepStrictEqual(payload.snapchat.pixel.item_ids, ['42_108']);
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.content_ids, ['42_108']);
      assert.strictEqual(payload.snapchat.dynamic_ads.contents[0].id, '42_108');
    });

    it('should use id_product strategy (${item_id}) when configured', function () {
      global.window.psGtmConfig.catalogIdStrategy = 'id_product';
      EDL.pushEvent('view_item', { currency: 'EUR', value: 199.00, items: [item] });
      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.deepStrictEqual(payload.snapchat.pixel.item_ids, ['42']);
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.content_ids, ['42']);
      assert.strictEqual(payload.snapchat.dynamic_ads.contents[0].id, '42');
    });

    it('should use reference_sku strategy (${sku_id}) when configured', function () {
      global.window.psGtmConfig.catalogIdStrategy = 'reference_sku';
      EDL.pushEvent('view_item', { currency: 'EUR', value: 199.00, items: [item] });
      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.deepStrictEqual(payload.snapchat.pixel.item_ids, ['SKU-SNAP-42']);
      assert.deepStrictEqual(payload.snapchat.dynamic_ads.content_ids, ['SKU-SNAP-42']);
      assert.strictEqual(payload.snapchat.dynamic_ads.contents[0].id, 'SKU-SNAP-42');
    });
  });

  // 3. Selective Routing & Activation Tests
  describe('3. Selective Routing & Activation', function () {
    const item = { item_id: '1', item_name: 'Test', price: 10.00, quantity: 1 };

    it('should not push snapchat when master_enabled is false', function () {
      global.window.psGtmConfig.advertisingSettings.master_enabled = false;
      EDL.pushEvent('view_item', { currency: 'EUR', value: 10.00, items: [item] });
      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(payload.snapchat, undefined);
    });

    it('should not push snapchat when snapchat_enabled is false', function () {
      global.window.psGtmConfig.advertisingSettings.snapchat_enabled = false;
      EDL.pushEvent('view_item', { currency: 'EUR', value: 10.00, items: [item] });
      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(payload.snapchat, undefined);
    });

    it('should not push snapchat for events not included in snapchat_events', function () {
      global.window.psGtmConfig.advertisingSettings.snapchat_events = ['purchase'];
      EDL.pushEvent('view_item', { currency: 'EUR', value: 10.00, items: [item] });
      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(payload.snapchat, undefined);

      EDL.pushEvent('purchase', { transaction_id: 'T1', currency: 'EUR', value: 10.00, items: [item] });
      const purchasePayload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(purchasePayload.snapchat);
      assert.strictEqual(purchasePayload.snapchat.event_name, 'PURCHASE');
    });
  });

  // 4. Pre-Event State Purge Reset
  describe('4. Pre-Event State Purge Reset', function () {
    it('should ensure snapchat: null is present in the pre-event purge object', function () {
      const item = { item_id: '1', item_name: 'Test', price: 10.00, quantity: 1 };
      EDL.pushEvent('view_item', { currency: 'EUR', value: 10.00, items: [item] });

      assert.ok(global.window.dataLayer.length >= 2, 'Must have at least 2 pushes (purge + event)');
      const purge = global.window.dataLayer[global.window.dataLayer.length - 2];
      assert.strictEqual(purge.snapchat, null, 'snapchat must be purged with null before event push');
    });
  });
});
