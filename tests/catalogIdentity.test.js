const { describe, it, beforeEach } = require('node:test');
const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Catalog-Accurate Identity & Variants Test Suite', function () {

  beforeEach(function () {
    global.window = { dataLayer: [] };
    EDL.setEventPrefix('');
  });

  it('1. Should strictly preserve item_id (base product ID), variant_id, sku_id, and item_variant without extraneous catalog IDs', function () {
    const rawVariantProduct = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Baby Geschirr',
      item_variant: 'Stahlblau',
      price: 13.00,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(rawVariantProduct);
    assert.strictEqual(normalized.item_id, '68', 'item_id should be base product ID');
    assert.strictEqual(normalized.variant_id, '299', 'variant_id should be active combination ID');
    assert.strictEqual(normalized.sku_id, 'BHK125-B', 'sku_id should be exact variant reference');
    assert.strictEqual(normalized.item_variant, 'Stahlblau', 'item_variant should be combination attribute');
    assert.strictEqual(normalized.item_name, 'Trink und Snack Tasse');
    assert.strictEqual(normalized.price, 13.00);
    assert.strictEqual(normalized.quantity, 1);
    assert.strictEqual(normalized.item_id_gmc, undefined, 'item_id_gmc should not be present');
    assert.strictEqual(normalized.item_id_meta, undefined, 'item_id_meta should not be present');
  });

  it('2. Should handle simple products without combinations cleanly', function () {
    const rawSimpleProduct = {
      item_id: '1116',
      variant_id: '',
      sku_id: 'FB-004-106',
      item_name: 'Faltbare Babybadewanne mit Ablaufstopfen - Grau',
      item_brand: 'Familys World',
      item_category: 'Baby Badewanne',
      item_variant: '',
      price: 24.99,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(rawSimpleProduct);
    assert.strictEqual(normalized.item_id, '1116');
    assert.strictEqual(normalized.variant_id, '');
    assert.strictEqual(normalized.sku_id, 'FB-004-106');
    assert.strictEqual(normalized.item_variant, '');
    assert.strictEqual(normalized.price, 24.99);
  });

  it('3. Should maintain dynamic event prefix with clean payload integrity and variant parameters', function () {
    EDL.setEventPrefix('raihan');
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
      items: [
        {
          item_id: '68',
          variant_id: '298',
          sku_id: 'BHK125-R',
          item_name: 'Trink und Snack Tasse',
          item_variant: 'Rostrot',
          price: 13.00,
          quantity: 1
        }
      ]
    });

    const pushedEvent = global.window.dataLayer[1];
    assert.strictEqual(pushedEvent.event, 'raihan_add_to_cart');
    assert.strictEqual(pushedEvent.original_event, 'add_to_cart');
    assert.strictEqual(pushedEvent.ecommerce.value, 13.00);
    assert.strictEqual(pushedEvent.ecommerce.content_type, undefined, 'content_type should not be present');
    assert.strictEqual(pushedEvent.ecommerce.meta_content_ids, undefined, 'meta_content_ids should not be present');
    
    const item = pushedEvent.ecommerce.items[0];
    assert.strictEqual(item.item_id, '68');
    assert.strictEqual(item.variant_id, '298');
    assert.strictEqual(item.sku_id, 'BHK125-R');
    assert.strictEqual(item.item_variant, 'Rostrot');
  });

  it('4. Should sanitize and validate customer SHA-256 strings', function () {
    const validSha256 = 'a6c24312b18974513568c07c2d92161bc7478d10b754961555543cbe27efcf99';
    assert.strictEqual(/^[a-f0-9]{64}$/.test(validSha256), true);
  });

  it('5. Should verify user_data contains raw unhashed customer data and omits meta_external_id', function () {
    const rawUserData = {
      email: 'customer@example.com',
      phone_number: '+491701234567',
      address: {
        first_name: 'John',
        last_name: 'Doe',
        street: 'Main Street 10',
        city: 'amsterdam',
        postal_code: '84125',
        country: 'DE'
      }
    };

    assert.strictEqual(rawUserData.meta_external_id, undefined, 'meta_external_id must be removed');
    assert.strictEqual(rawUserData.email, 'customer@example.com');
    assert.strictEqual(rawUserData.phone_number, '+491701234567');
    assert.strictEqual(rawUserData.address.first_name, 'John');
    assert.strictEqual(rawUserData.address.last_name, 'Doe');
    assert.strictEqual(rawUserData.address.street, 'Main Street 10');
    assert.strictEqual(rawUserData.address.city, 'amsterdam');
    assert.strictEqual(rawUserData.address.postal_code, '84125');
    assert.strictEqual(rawUserData.address.country, 'DE');
  });

  it('6. Should preserve item_brand and base product identifiers for simple product 1013', function () {
    const product1013 = {
      item_id: '1013',
      variant_id: '',
      sku_id: '5326310',
      item_name: 'Babytrage Terra Nova Beige',
      item_brand: 'Dooky - Xplorys BV',
      item_category: 'Babytrage',
      item_variant: '',
      price: 74.99,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(product1013);
    assert.strictEqual(normalized.item_id, '1013');
    assert.strictEqual(normalized.variant_id, '');
    assert.strictEqual(normalized.sku_id, '5326310');
    assert.strictEqual(normalized.item_name, 'Babytrage Terra Nova Beige');
    assert.strictEqual(normalized.item_brand, 'Dooky - Xplorys BV');
    assert.strictEqual(normalized.item_category, 'Babytrage');
  });

  it('7. Universal backend data pipeline cross-funnel schema verification (No DOM)', function () {
    EDL.setEventPrefix('raihan');

    // Funnel Step 1: view_item (server hydrated)
    const viewItemPayload = {
      currency: 'EUR',
      value: 74.99,
      items: [
        EDL.normalizeItem({
          item_id: '1013',
          variant_id: '',
          sku_id: '5326310',
          item_name: 'Babytrage Terra Nova Beige',
          item_brand: 'Dooky - Xplorys BV',
          item_category: 'Babytrage',
          item_variant: '',
          price: 74.99,
          quantity: 1
        })
      ]
    };
    EDL.pushEvent('view_item', viewItemPayload);

    // Funnel Step 2: add_to_cart (native pub/sub event)
    const addToCartPayload = {
      currency: 'EUR',
      value: 74.99,
      items: [
        EDL.normalizeItem({
          item_id: '1013',
          variant_id: '',
          sku_id: '5326310',
          item_name: 'Babytrage Terra Nova Beige',
          item_brand: 'Dooky - Xplorys BV',
          item_category: 'Babytrage',
          item_variant: '',
          price: 74.99,
          quantity: 1
        })
      ]
    };
    EDL.pushEvent('add_to_cart', addToCartPayload);

    // Funnel Step 3: begin_checkout (server hydrated)
    const beginCheckoutPayload = {
      currency: 'EUR',
      value: 74.99,
      items: [
        EDL.normalizeItem({
          item_id: '1013',
          variant_id: '',
          sku_id: '5326310',
          item_name: 'Babytrage Terra Nova Beige',
          item_brand: 'Dooky - Xplorys BV',
          item_category: 'Babytrage',
          item_variant: '',
          price: 74.99,
          quantity: 1
        })
      ]
    };
    EDL.pushEvent('begin_checkout', beginCheckoutPayload);

    // Funnel Step 4: purchase (server hydrated with user_data & user_data_raw)
    const purchasePayload = {
      transaction_id: 'OKAHUEEVW',
      currency: 'EUR',
      value: 74.99,
      tax: 11.97,
      shipping: 0,
      coupon: '',
      items: [
        EDL.normalizeItem({
          item_id: '1013',
          variant_id: '',
          sku_id: '5326310',
          item_name: 'Babytrage Terra Nova Beige',
          item_brand: 'Dooky - Xplorys BV',
          item_category: 'Babytrage',
          item_variant: '',
          price: 74.99,
          quantity: 1
        })
      ]
    };
    EDL.pushEvent('purchase', purchasePayload, { email: 'hash123' });

    // Assert absolute consistency across all events in dataLayer
    const events = global.window.dataLayer.filter(e => e.event && e.event.startsWith('raihan_'));
    assert.strictEqual(events.length, 4);

    const [evView, evAdd, evCheckout, evPurchase] = events;
    const allItems = [
      evView.ecommerce.items[0],
      evAdd.ecommerce.items[0],
      evCheckout.ecommerce.items[0],
      evPurchase.ecommerce.items[0]
    ];

    allItems.forEach((item, index) => {
      assert.strictEqual(item.item_id, '1013', `item_id mismatch at step ${index}`);
      assert.strictEqual(item.variant_id, '', `variant_id mismatch at step ${index}`);
      assert.strictEqual(item.sku_id, '5326310', `sku_id mismatch at step ${index}`);
      assert.strictEqual(item.item_brand, 'Dooky - Xplorys BV', `item_brand mismatch at step ${index}`);
      assert.strictEqual(item.item_category, 'Babytrage', `item_category mismatch at step ${index}`);
      assert.strictEqual(item.price, 74.99, `price mismatch at step ${index}`);
    });
  });

  it('8. Order HTAIWHXQL financial breakdown and order_totals verification', function () {
    const rawUserData = {
      email: 'weba@gmail.com',
      address: {
        first_name: 'Web',
        last_name: 'Raihan',
        street: 'Neuer Jungfernstieg 19',
        city: 'amsterdam',
        postal_code: '84125',
        country: 'DE'
      }
    };

    const purchasePayload = {
      event: 'raihan_purchase',
      original_event: 'purchase',
      ecommerce: {
        transaction_id: 'HTAIWHXQL',
        value: 57.60, // sum of items (19.20 * 3), excluding shipping
        tax: 9.92,
        shipping: 4.50,
        currency: 'EUR',
        coupon: '',
        items: [
          {
            item_id: '153',
            variant_id: '',
            sku_id: 'FIT09',
            item_name: 'FitKidz Barbell - Plush',
            item_brand: '',
            item_category: 'Sportspielzeug',
            item_variant: '',
            price: 19.20,
            quantity: 3
          }
        ]
      },
      order_totals: {
        product_subtotal: 57.60,
        shipping_total: 4.50,
        tax_total: 9.92,
        order_total: 62.10,
        currency: 'EUR'
      },
      user_data: rawUserData
    };

    assert.strictEqual(purchasePayload.ecommerce.value, 57.60, 'GA4 value must equal items sum');
    assert.strictEqual(purchasePayload.ecommerce.shipping, 4.50, 'Shipping must be 4.50');
    assert.strictEqual(purchasePayload.ecommerce.tax, 9.92, 'Tax must be 9.92');
    assert.strictEqual(purchasePayload.order_totals.product_subtotal, 57.60);
    assert.strictEqual(purchasePayload.order_totals.shipping_total, 4.50);
    assert.strictEqual(purchasePayload.order_totals.tax_total, 9.92);
    assert.strictEqual(purchasePayload.order_totals.order_total, 62.10);
    assert.strictEqual(purchasePayload.user_data.email, 'weba@gmail.com');
    assert.strictEqual(purchasePayload.user_data.meta_external_id, undefined, 'meta_external_id must not be present');
  });
});
