const { describe, it, beforeEach } = require('node:test');
const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('OpenAI / ChatGPT Ads Measurement Specification Test Suite', function () {

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
        }
      }
    };
    EDL.setEventPrefix('');
  });

  // 1. Universal ISO 4217 Minor Unit Conversion Tests
  describe('1. ISO 4217 Minor Unit Conversion Engine', function () {
    it('should convert standard 2-decimal currencies (EUR, USD, GBP, BDT) to minor units (*100)', function () {
      assert.strictEqual(EDL.toMinorUnits(25.99, 'USD'), 2599);
      assert.strictEqual(EDL.toMinorUnits(13.00, 'EUR'), 1300);
      assert.strictEqual(EDL.toMinorUnits('49.50', 'GBP'), 4950);
      assert.strictEqual(EDL.toMinorUnits(1500.75, 'BDT'), 150075);
    });

    it('should convert 0-decimal currencies (JPY, KRW, VND, CLP, PYG, UGX, RWF, BIF) without multiplier (*1)', function () {
      assert.strictEqual(EDL.toMinorUnits(125, 'JPY'), 125);
      assert.strictEqual(EDL.toMinorUnits(125.49, 'JPY'), 125);
      assert.strictEqual(EDL.toMinorUnits(125.75, 'JPY'), 126);
      assert.strictEqual(EDL.toMinorUnits(50000, 'KRW'), 50000);
      assert.strictEqual(EDL.toMinorUnits(1000000, 'VND'), 1000000);
      assert.strictEqual(EDL.toMinorUnits(25000, 'CLP'), 25000);
    });

    it('should convert 3-decimal currencies (KWD, BHD, JOD, OMR, TND) to minor units (*1000)', function () {
      assert.strictEqual(EDL.toMinorUnits(12.345, 'KWD'), 12345);
      assert.strictEqual(EDL.toMinorUnits(5.5, 'BHD'), 5500);
      assert.strictEqual(EDL.toMinorUnits('1.250', 'OMR'), 1250);
      assert.strictEqual(EDL.toMinorUnits(10.123, 'JOD'), 10123);
    });

    it('should convert 4-decimal currencies (CLF, UYW) to minor units (*10000)', function () {
      assert.strictEqual(EDL.toMinorUnits(1.2345, 'CLF'), 12345);
      assert.strictEqual(EDL.toMinorUnits(2.5, 'UYW'), 25000);
    });

    it('should respect custom precision overrides when specified', function () {
      assert.strictEqual(EDL.toMinorUnits(10.5, 'XYZ', 3), 10500);
      assert.strictEqual(EDL.toMinorUnits(10.5, 'XYZ', 0), 11);
    });
  });

  // 2. Standard Ecommerce Event Payload Formats
  describe('2. Standard Ecommerce Events & Schemas', function () {
    const productVariant = {
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

    it('should build contents_viewed for view_item (PDP) with catalog matching ID', function () {
      const norm = EDL.normalizeItems([productVariant]);
      EDL.pushEvent('view_item', {
        currency: 'EUR',
        value: 13.00,
        items: norm
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads, 'openai_ads namespace must be present');
      assert.strictEqual(payload.openai_ads.command, 'measure');
      assert.strictEqual(payload.openai_ads.event_name, 'contents_viewed');
      assert.strictEqual(payload.openai_ads.data.type, 'contents');
      assert.strictEqual(payload.openai_ads.data.amount, undefined, 'view_item should not include amount');
      assert.strictEqual(payload.openai_ads.data.contents.length, 1);
      assert.deepStrictEqual(payload.openai_ads.data.contents[0], {
        id: '68_299',
        name: 'Trink und Snack Tasse',
        content_type: 'product'
      });
      assert.strictEqual(payload.openai_ads.event_id, undefined, 'event_id must strictly NOT be present');
    });

    it('should build custom event for view_item_list with custom_event_name', function () {
      const items = [
        { item_id: '1', item_name: 'Product 1', price: 20.00, quantity: 1 },
        { item_id: '2', item_name: 'Product 2', price: 30.00, quantity: 1 }
      ];
      EDL.pushEvent('view_item_list', {
        item_list_id: 'category_10',
        item_list_name: 'Summer Deals',
        currency: 'USD',
        value: 50.00,
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'view_item_list' });
    });

    it('should build custom event for view_search_results with custom_event_name', function () {
      const items = [{ item_id: '15', item_name: 'Mug', price: 10.00, quantity: 1 }];
      EDL.pushEvent('view_search_results', {
        search_term: 'cup',
        currency: 'EUR',
        value: 10.00,
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'view_search_results' });
    });

    it('should build custom event for view_cart with custom_event_name', function () {
      const items = [
        { item_id: '68', variant_id: '299', item_name: 'Cup', price: 13.00, quantity: 2 }
      ];
      EDL.pushEvent('view_cart', {
        currency: 'EUR',
        value: 26.00,
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'view_cart' });
    });

    it('should build items_added for add_to_cart with minor unit amounts on line items and overall data', function () {
      const item = {
        item_id: '68',
        variant_id: '299',
        sku_id: 'BHK125-B',
        item_name: 'Trink und Snack Tasse',
        price: 13.00,
        quantity: 2
      };
      EDL.pushEvent('add_to_cart', {
        currency: 'EUR',
        value: 26.00,
        items: [item]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.command, 'measure');
      assert.strictEqual(payload.openai_ads.event_name, 'items_added');
      assert.strictEqual(payload.openai_ads.data.type, 'contents');
      assert.strictEqual(payload.openai_ads.data.amount, 2600, 'Total amount must be converted to minor units (26.00 EUR -> 2600)');
      assert.strictEqual(payload.openai_ads.data.currency, 'EUR');
      assert.strictEqual(payload.openai_ads.data.contents.length, 1);
      assert.deepStrictEqual(payload.openai_ads.data.contents[0], {
        id: '68_299',
        name: 'Trink und Snack Tasse',
        content_type: 'product',
        quantity: 2,
        amount: 1300,
        currency: 'EUR'
      }, 'Item price must be converted to minor units (13.00 EUR -> 1300)');
      assert.strictEqual(payload.openai_ads.event_id, undefined);
    });

    it('should build checkout_started for begin_checkout with total amount in minor units', function () {
      const items = [
        { item_id: '1', item_name: 'Shirt', price: 23.90, quantity: 1 },
        { item_id: '68', variant_id: '299', item_name: 'Cup', price: 13.00, quantity: 1 }
      ];
      EDL.pushEvent('begin_checkout', {
        currency: 'EUR',
        value: 36.90,
        items: items
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'checkout_started');
      assert.strictEqual(payload.openai_ads.data.type, 'contents');
      assert.strictEqual(payload.openai_ads.data.amount, 3690, 'Cart total must be converted to minor units (36.90 EUR -> 3690)');
      assert.strictEqual(payload.openai_ads.data.currency, 'EUR');
      assert.strictEqual(payload.openai_ads.data.contents.length, 2);
      assert.strictEqual(payload.openai_ads.data.contents[0].quantity, 1);
      assert.strictEqual(payload.openai_ads.data.contents[1].quantity, 1);
      assert.strictEqual(payload.openai_ads.event_id, undefined);
    });

    it('should build order_created for purchase with minor unit total and normalized advanced matching user data', function () {
      const items = [
        { item_id: '68', variant_id: '299', item_name: 'Cup', price: 13.00, quantity: 1 }
      ];
      const openAiUserData = {
        email_sha256: '9b71d224bd62f3785d96d46ad3ea3d73319bfbc2890caadae2dff72519673ca72',
        phone_number_sha256: '4638da0bbb4923e32fb9961dbd15582fc89d538f5f6cecf909d3b4ad205ff9ff',
        first_name_sha256: '5c6b0208d4104931bb46f713ad38130f31ff160a5e320d63a9c7fb660c6c167b',
        last_name_sha256: 'd751f76b33d539f5f12db4e3c006e7f05821591487321469f847312936f2dd4a',
        external_id_sha256: 'ef2d127de37b942baad06145e54b0c619a1f22327b2ebbcfbec78f5564afe39d',
        country: 'DE',
        city: 'Amsterdam',
        region: 'BE',
        postal_code: '84125'
      };

      EDL.pushEvent('purchase', {
        transaction_id: '1255',
        currency: 'EUR',
        value: 17.50,
        tax: 2.50,
        shipping: 2.00,
        items: items
      }, null, {
        openai_user_data: openAiUserData
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.command, 'measure');
      assert.strictEqual(payload.openai_ads.event_name, 'order_created');
      assert.strictEqual(payload.openai_ads.data.type, 'contents');
      assert.strictEqual(payload.openai_ads.data.amount, 1750, 'Order revenue in minor units (17.50 EUR -> 1750)');
      assert.strictEqual(payload.openai_ads.data.currency, 'EUR');
      assert.strictEqual(payload.openai_ads.data.contents.length, 1);
      assert.strictEqual(payload.openai_ads.data.contents[0].id, '68_299');
      assert.strictEqual(payload.openai_ads.data.contents[0].quantity, 1);
      assert.deepStrictEqual(payload.openai_ads.data.user, openAiUserData, 'Advanced matching user data must be passed cleanly in user object');
      assert.strictEqual(payload.openai_ads.event_id, undefined, 'event_id must strictly NOT be present on purchase');
    });
  });

  // 3. Custom Event Wrapping Tests
  describe('3. Custom Ecommerce Actions', function () {
    it('should wrap remove_from_cart as custom event with custom_event_name', function () {
      EDL.pushEvent('remove_from_cart', {
        currency: 'EUR',
        value: 13.00,
        items: [{ item_id: '68_299', price: 13.00, quantity: 1 }]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'remove_from_cart' });
    });

    it('should wrap add_to_wishlist as custom event with custom_event_name', function () {
      EDL.pushEvent('add_to_wishlist', {
        currency: 'EUR',
        value: 23.90,
        items: [{ item_id: '1_1', price: 23.90, quantity: 1 }]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'add_to_wishlist' });
    });

    it('should wrap add_shipping_info as custom event with custom_event_name', function () {
      EDL.pushEvent('add_shipping_info', {
        currency: 'EUR',
        value: 41.90,
        shipping_tier: 'DHL Express',
        items: [{ item_id: '1_1', price: 23.90, quantity: 1 }]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'add_shipping_info' });
    });

    it('should wrap add_payment_info as custom event with custom_event_name', function () {
      EDL.pushEvent('add_payment_info', {
        currency: 'EUR',
        value: 41.90,
        payment_type: 'PayPal',
        items: [{ item_id: '1_1', price: 23.90, quantity: 1 }]
      });

      const payload = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.ok(payload.openai_ads);
      assert.strictEqual(payload.openai_ads.event_name, 'custom');
      assert.strictEqual(payload.openai_ads.data.type, 'custom');
      assert.deepStrictEqual(payload.openai_ads.options, { custom_event_name: 'add_payment_info' });
    });
  });

  // 4. Catalog ID Strategy Parity
  describe('4. Catalog ID Strategy Parity', function () {
    const item = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Cup',
      price: 13.00,
      quantity: 1
    };

    it('should output composite ID {id_product}_{id_product_attribute} by default', function () {
      const namespaces = EDL.buildPlatformNamespaces('view_item', [item], 'EUR', 13.00, { catalogIdStrategy: 'composite' });
      assert.strictEqual(namespaces.openai_ads.data.contents[0].id, '68_299');
    });

    it('should output reference SKU when reference_sku strategy is configured', function () {
      const namespaces = EDL.buildPlatformNamespaces('view_item', [item], 'EUR', 13.00, { catalogIdStrategy: 'reference_sku' });
      assert.strictEqual(namespaces.openai_ads.data.contents[0].id, 'BHK125-B');
    });

    it('should output base product ID when id_product strategy is configured', function () {
      const namespaces = EDL.buildPlatformNamespaces('view_item', [item], 'EUR', 13.00, { catalogIdStrategy: 'id_product' });
      assert.strictEqual(namespaces.openai_ads.data.contents[0].id, '68');
    });
  });

  // 5. Back Office Routing and Master Switch Controls
  describe('5. Advertising Platform Routing & Enablement Matrix', function () {
    const item = { item_id: '68', variant_id: '299', item_name: 'Cup', price: 13.00, quantity: 1 };

    it('should suppress openai_ads when master_enabled is false', function () {
      global.window.psGtmConfig.advertisingSettings.master_enabled = false;
      const namespaces = EDL.buildPlatformNamespaces('view_item', [item], 'EUR', 13.00);
      assert.strictEqual(namespaces.openai_ads, undefined);
      assert.strictEqual(namespaces.meta, undefined);
    });

    it('should suppress openai_ads when openai_enabled is false while preserving others', function () {
      global.window.psGtmConfig.advertisingSettings.master_enabled = true;
      global.window.psGtmConfig.advertisingSettings.openai_enabled = false;
      const namespaces = EDL.buildPlatformNamespaces('view_item', [item], 'EUR', 13.00);
      assert.strictEqual(namespaces.openai_ads, undefined);
      assert.ok(namespaces.meta, 'meta must remain active');
      assert.ok(namespaces.google_ads, 'google_ads must remain active');
    });

    it('should respect granular event routing when openai_events whitelist is specified', function () {
      global.window.psGtmConfig.advertisingSettings.openai_enabled = true;
      global.window.psGtmConfig.advertisingSettings.openai_events = ['purchase', 'view_item'];

      // view_item is whitelisted
      const nsView = EDL.buildPlatformNamespaces('view_item', [item], 'EUR', 13.00);
      assert.ok(nsView.openai_ads);

      // add_to_cart is NOT whitelisted
      const nsCart = EDL.buildPlatformNamespaces('add_to_cart', [item], 'EUR', 13.00);
      assert.strictEqual(nsCart.openai_ads, undefined);
    });
  });

  // 6. GTM DataLayer Purge
  describe('6. DataLayer Purge & Property Isolation', function () {
    it('should push openai_ads: null in purge object to prevent GTM property pollution', function () {
      EDL.pushEvent('view_item', {
        currency: 'EUR',
        value: 13.00,
        items: [{ item_id: '68_299', price: 13.00, quantity: 1 }]
      });

      // DataLayer must contain at least 2 pushes: purge then event
      assert.ok(global.window.dataLayer.length >= 2);
      const purge = global.window.dataLayer[global.window.dataLayer.length - 2];
      assert.strictEqual(purge.ecommerce, null);
      assert.strictEqual(purge.meta, null);
      assert.strictEqual(purge.tiktok, null);
      assert.strictEqual(purge.google_ads, null);
      assert.strictEqual(purge.pinterest, null);
      assert.strictEqual(purge.microsoft_ads, null);
      assert.strictEqual(purge.openai_ads, null, 'openai_ads must be purged to null');
    });
  });

});
