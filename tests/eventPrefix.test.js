const { describe, it, beforeEach } = require('node:test');
const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Dynamic Data Layer Event Prefix Test Suite', function () {

  beforeEach(function () {
    global.window = { dataLayer: [] };
    EDL.setEventPrefix('');
  });

  it('1. Should return standard GA4 event names when prefix is empty', function () {
    assert.strictEqual(EDL.formatEventName('view_item'), 'view_item');
    assert.strictEqual(EDL.formatEventName('add_to_cart'), 'add_to_cart');
    assert.strictEqual(EDL.formatEventName('purchase'), 'purchase');
  });

  it('2. Should dynamically prefix events with any user identifier', function () {
    EDL.setEventPrefix('raihan');
    assert.strictEqual(EDL.formatEventName('view_item'), 'raihan_view_item');
    assert.strictEqual(EDL.formatEventName('add_to_cart'), 'raihan_add_to_cart');
    assert.strictEqual(EDL.formatEventName('purchase'), 'raihan_purchase');

    EDL.setEventPrefix('masum');
    assert.strictEqual(EDL.formatEventName('view_item'), 'masum_view_item');
    assert.strictEqual(EDL.formatEventName('begin_checkout'), 'masum_begin_checkout');

    EDL.setEventPrefix('client_a');
    assert.strictEqual(EDL.formatEventName('purchase'), 'client_a_purchase');
  });

  it('3. Should normalize whitespaces, dots, and hyphens into clean underscores', function () {
    assert.strictEqual(EDL.normalizePrefix(' my brand '), 'my_brand');
    assert.strictEqual(EDL.normalizePrefix('client-a.demo'), 'client_a_demo');
    assert.strictEqual(EDL.normalizePrefix('__test_brand__'), 'test_brand');
  });

  it('4. Should apply the prefix exactly once and avoid double prefixing', function () {
    EDL.setEventPrefix('raihan');
    assert.strictEqual(EDL.formatEventName('raihan_view_item'), 'raihan_view_item');
    assert.strictEqual(EDL.formatEventName('view_item'), 'raihan_view_item');
  });

  it('5. Should preserve canonical GA4 event name under original_event in dataLayer payload', function () {
    EDL.setEventPrefix('shop_123');
    EDL.pushEvent('add_to_cart', { currency: 'EUR', value: 29.99, items: [] });

    // Expect 2 pushes: 1st is reset of ecommerce and advertising namespaces, 2nd is the event payload
    assert.strictEqual(global.window.dataLayer.length, 2);
    assert.deepStrictEqual(global.window.dataLayer[0], {
      ecommerce: null,
      meta: null,
      tiktok: null,
      google_ads: null,
      pinterest: null,
      microsoft_ads: null,
      openai_ads: null
    });

    const payload = global.window.dataLayer[1];
    assert.strictEqual(payload.event, 'shop_123_add_to_cart');
    assert.strictEqual(payload.original_event, 'add_to_cart');
    assert.strictEqual(payload.ecommerce.value, 29.99);
  });

  it('6. Should dynamically adapt when prefix changes at runtime', function () {
    EDL.setEventPrefix('brand_one');
    EDL.pushEvent('view_item', {});
    assert.strictEqual(global.window.dataLayer[1].event, 'brand_one_view_item');

    EDL.setEventPrefix('brand_two');
    EDL.pushEvent('view_item', {});
    assert.strictEqual(global.window.dataLayer[3].event, 'brand_two_view_item');
  });
});
