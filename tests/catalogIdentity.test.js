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
        order_id: 1221,
        order_reference: 'HTAIWHXQL',
        product_subtotal: 57.60,
        shipping_total: 4.50,
        tax_total: 9.92,
        order_total: 62.10,
        currency: 'EUR'
      },
      user_data: rawUserData
    };

    assert.strictEqual(purchasePayload.ecommerce.transaction_id, 'HTAIWHXQL');
    assert.strictEqual(purchasePayload.order_totals.order_id, 1221);
    assert.strictEqual(purchasePayload.order_totals.order_reference, 'HTAIWHXQL');

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

  it('9. Should strictly isolate simple products (Product 15 & Product 1010) from variant product 68', function () {
    // Simple Product 15: Milchpumpe mit Saugfuss 100ml
    const p15 = EDL.normalizeItem({
      item_id: '15',
      variant_id: '',
      sku_id: 'MHK011',
      item_name: 'Milchpumpe mit Saugfuss 100ml',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Milchpumpen',
      price: 19.99,
      quantity: 1
    });
    assert.strictEqual(p15.item_id, '15', 'Product 15 must retain item_id 15');
    assert.strictEqual(p15.variant_id, '', 'Simple product 15 must have empty variant_id');
    assert.strictEqual(p15.sku_id, 'MHK011');
    assert.strictEqual(p15.price, 19.99);

    // Simple Product 1010: Shampoobürste - Erbsengrün
    const p1010 = EDL.normalizeItem({
      item_id: '1010',
      variant_id: '',
      sku_id: 'SGE050-PG',
      item_name: 'Shampoobürste - Erbsengrün',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Babypflege',
      price: 12.29,
      quantity: 1
    });
    assert.strictEqual(p1010.item_id, '1010', 'Product 1010 must retain item_id 1010');
    assert.strictEqual(p1010.variant_id, '', 'Simple product 1010 must have empty variant_id');
    assert.strictEqual(p1010.sku_id, 'SGE050-PG');
    assert.strictEqual(p1010.price, 12.29);

    // Variant Product 68: Trink und Snack Tasse (Variant 299 Stahlblau)
    const p68_299 = EDL.normalizeItem({
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Baby Geschirr',
      item_variant: 'Farbe: Stahlblau',
      price: 12.99,
      quantity: 1
    });
    assert.strictEqual(p68_299.item_id, '68');
    assert.strictEqual(p68_299.variant_id, '299');
    assert.strictEqual(p68_299.sku_id, 'BHK125-B');
    assert.strictEqual(p68_299.price, 12.99);
  });

  it('10. Should sanitize numeric zero ("0" or 0) variant IDs to empty string', function () {
    const rawZeroString = {
      item_id: '15',
      variant_id: '0',
      item_name: 'Milchpumpe mit Saugfuss 100ml',
      price: 19.99,
      quantity: 1
    };
    const norm1 = EDL.normalizeItem(rawZeroString);
    assert.strictEqual(norm1.variant_id, '', 'String "0" must normalize to empty string');

    const rawZeroInt = {
      item_id: '15',
      variant_id: 0,
      item_name: 'Milchpumpe mit Saugfuss 100ml',
      price: 19.99,
      quantity: 1
    };
    const norm2 = EDL.normalizeItem(rawZeroInt);
    assert.strictEqual(norm2.variant_id, '', 'Integer 0 must normalize to empty string');

    const rawAttrZero = {
      item_id: '15',
      id_product_attribute: '0',
      item_name: 'Milchpumpe mit Saugfuss 100ml',
      price: 19.99,
      quantity: 1
    };
    const norm3 = EDL.normalizeItem(rawAttrZero);
    assert.strictEqual(norm3.variant_id, '', 'id_product_attribute "0" must normalize to empty string');
  });

  it('11. Should maintain absolute state isolation during consecutive add_to_cart actions', function () {
    EDL.setEventPrefix('raihan');

    // 1. Add Product 15
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 19.99,
      items: [
        EDL.normalizeItem({
          item_id: '15',
          variant_id: '',
          sku_id: 'MHK011',
          item_name: 'Milchpumpe mit Saugfuss 100ml',
          price: 19.99,
          quantity: 1
        })
      ]
    });

    // 2. Add Product 68 (Variant 299)
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
      items: [
        EDL.normalizeItem({
          item_id: '68',
          variant_id: '299',
          sku_id: 'BHK125-B',
          item_name: 'Trink und Snack Tasse',
          item_variant: 'Stahlblau',
          price: 13.00,
          quantity: 1
        })
      ]
    });

    // 3. Add Product 1010
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 12.29,
      items: [
        EDL.normalizeItem({
          item_id: '1010',
          variant_id: '',
          sku_id: 'SGE050-PG',
          item_name: 'Shampoobürste - Erbsengrün',
          price: 12.29,
          quantity: 1
        })
      ]
    });

    // 4. Add Product 68 (Variant 298)
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
      items: [
        EDL.normalizeItem({
          item_id: '68',
          variant_id: '298',
          sku_id: 'BHK125-R',
          item_name: 'Trink und Snack Tasse',
          item_variant: 'Rostrot',
          price: 13.00,
          quantity: 1
        })
      ]
    });

    const addEvents = global.window.dataLayer.filter(e => e.event === 'raihan_add_to_cart');
    assert.strictEqual(addEvents.length, 4);

    // Event 1: Product 15
    assert.strictEqual(addEvents[0].ecommerce.items[0].item_id, '15');
    assert.strictEqual(addEvents[0].ecommerce.items[0].variant_id, '');
    assert.strictEqual(addEvents[0].ecommerce.items[0].sku_id, 'MHK011');
    assert.strictEqual(addEvents[0].ecommerce.items[0].price, 19.99);

    // Event 2: Product 68 Variant 299
    assert.strictEqual(addEvents[1].ecommerce.items[0].item_id, '68');
    assert.strictEqual(addEvents[1].ecommerce.items[0].variant_id, '299');
    assert.strictEqual(addEvents[1].ecommerce.items[0].sku_id, 'BHK125-B');
    assert.strictEqual(addEvents[1].ecommerce.items[0].price, 13.00);

    // Event 3: Product 1010
    assert.strictEqual(addEvents[2].ecommerce.items[0].item_id, '1010');
    assert.strictEqual(addEvents[2].ecommerce.items[0].variant_id, '');
    assert.strictEqual(addEvents[2].ecommerce.items[0].sku_id, 'SGE050-PG');
    assert.strictEqual(addEvents[2].ecommerce.items[0].price, 12.29);

    // Event 4: Product 68 Variant 298
    assert.strictEqual(addEvents[3].ecommerce.items[0].item_id, '68');
    assert.strictEqual(addEvents[3].ecommerce.items[0].variant_id, '298');
    assert.strictEqual(addEvents[3].ecommerce.items[0].sku_id, 'BHK125-R');
    assert.strictEqual(addEvents[3].ecommerce.items[0].price, 13.00);
  });

  it('12. Exact commercial ROUND_HALF_UP decimal rounding tests across discount rates', function () {
    // 50% discount on 25.99 € yields exactly 12.995 €, which rounds up to 13.00 €
    assert.strictEqual(EDL.roundHalfUp(25.99 * 0.50, 2), 13.00, '25.99 * 0.5 must round up to 13.00');
    assert.strictEqual(EDL.roundHalfUp(12.995, 2), 13.00, '12.995 must round up to 13.00');

    // Values strictly below .005 round down
    assert.strictEqual(EDL.roundHalfUp(12.994, 2), 12.99, '12.994 must round down to 12.99');
    assert.strictEqual(EDL.roundHalfUp(12.991, 2), 12.99, '12.991 must round down to 12.99');

    // 20% discount on 19.99 € = 15.992 €, rounds down to 15.99 €
    assert.strictEqual(EDL.roundHalfUp(19.99 * 0.80, 2), 15.99, '19.99 * 0.8 must round down to 15.99');

    // 25% discount on 19.90 € = 14.925 €, rounds up to 14.93 €
    assert.strictEqual(EDL.roundHalfUp(19.90 * 0.75, 2), 14.93, '19.90 * 0.75 must round up to 14.93');

    // 15% discount on 29.99 € = 25.4915 €, rounds down to 25.49 €
    assert.strictEqual(EDL.roundHalfUp(29.99 * 0.85, 2), 25.49, '29.99 * 0.85 must round down to 25.49');
  });

  it('13. Trink und Snack Tasse customer-facing price parity in raihan_view_item (13.00 €)', function () {
    EDL.setEventPrefix('raihan');
    const regularGross = 25.99;
    const discount = 0.50;
    const customerPrice = EDL.roundHalfUp(regularGross * (1 - discount), 2);
    assert.strictEqual(customerPrice, 13.00);

    const item = EDL.normalizeItem({
      item_id: '68',
      variant_id: '298',
      sku_id: 'BHK125-R',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Baby Geschirr',
      item_variant: 'Farbe1: Rostrot',
      price: customerPrice,
      quantity: 1
    });

    EDL.pushEvent('view_item', {
      currency: 'EUR',
      value: item.price,
      items: [item]
    });

    const event = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(event.event, 'raihan_view_item');
    assert.strictEqual(event.ecommerce.value, 13.00, 'ecommerce.value must be 13.00');
    assert.strictEqual(event.ecommerce.currency, 'EUR');
    assert.strictEqual(event.ecommerce.items[0].price, 13.00, 'items[0].price must be 13.00');
    assert.strictEqual(event.ecommerce.items[0].item_id, '68');
    assert.strictEqual(event.ecommerce.items[0].variant_id, '298');
  });

  it('14. Multi-quantity cart calculation with ROUND_HALF_UP commercial pricing', function () {
    const item = EDL.normalizeItem({
      item_id: '68',
      variant_id: '298',
      sku_id: 'BHK125-R',
      item_name: 'Trink und Snack Tasse',
      price: 13.00,
      quantity: 2
    });

    const items = [item];
    const totalValue = EDL.calculateCartValue(items);
    assert.strictEqual(totalValue, 26.00, 'Cart value for 2 x 13.00 must be exactly 26.00');

    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: totalValue,
      items: items
    });

    const event = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(event.ecommerce.value, 26.00);
    assert.strictEqual(event.ecommerce.items[0].price, 13.00);
    assert.strictEqual(event.ecommerce.items[0].quantity, 2);
  });
});
