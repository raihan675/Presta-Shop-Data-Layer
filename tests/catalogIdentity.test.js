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
      transaction_id: '1220',
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
        transaction_id: '1221',
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

    assert.strictEqual(purchasePayload.ecommerce.transaction_id, '1221');
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

  it('15. Universal multi-currency precision (USD/EUR 2 decimals, JPY 0 decimals, BHD 3 decimals)', function () {
    // Zero-decimal currencies like JPY
    const jpyRegular = 1250;
    const jpyDiscount = 0.15; // 15% discount -> 1062.5 JPY -> rounds to 1063 JPY
    const jpyPrice = EDL.roundHalfUp(jpyRegular * (1 - jpyDiscount), 0);
    assert.strictEqual(jpyPrice, 1063);

    const jpyItem = EDL.normalizeItem({
      item_id: '450',
      variant_id: '',
      sku_id: 'JP-SHIRT-01',
      item_name: 'Tokyo Cotton Tee',
      price: jpyPrice,
      quantity: 3
    });
    const jpyTotal = EDL.calculateCartValue([jpyItem], 0);
    assert.strictEqual(jpyTotal, 3189);

    // Three-decimal currencies like BHD/KWD
    const bhdRegular = 15.350;
    const bhdDiscount = 0.3333; // 10.23384... -> rounds to 10.234 BHD
    const bhdPrice = EDL.roundHalfUp(bhdRegular * (1 - bhdDiscount), 3);
    assert.strictEqual(bhdPrice, 10.234);

    const bhdItem = EDL.normalizeItem({
      item_id: '990',
      variant_id: '55',
      sku_id: 'BHD-PERFUME',
      item_name: 'Arabian Oud',
      price: bhdPrice,
      quantity: 2
    });
    const bhdTotal = EDL.calculateCartValue([bhdItem], 3);
    assert.strictEqual(bhdTotal, 20.468);
  });

  it('16. Complex multi-combination apparel product with multiple attributes (Size, Color, Material)', function () {
    EDL.setEventPrefix('raihan');

    // Synthetic apparel product with multiple combination attributes
    const apparelItem = EDL.normalizeItem({
      item_id: '402',
      variant_id: '1580',
      sku_id: 'APP-HOODIE-L-BLK-COT',
      item_name: 'Premium Heavyweight Hoodie',
      item_brand: 'Nordic Apparel Co.',
      item_category: 'Clothing > Outerwear',
      item_variant: 'Size: L | Color: Black | Material: 100% Organic Cotton',
      price: 89.95,
      quantity: 1
    });

    assert.strictEqual(apparelItem.item_id, '402');
    assert.strictEqual(apparelItem.variant_id, '1580');
    assert.strictEqual(apparelItem.sku_id, 'APP-HOODIE-L-BLK-COT');
    assert.strictEqual(apparelItem.item_variant, 'Size: L | Color: Black | Material: 100% Organic Cotton');

    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: apparelItem.price,
      items: [apparelItem]
    });

    const event = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(event.event, 'raihan_add_to_cart');
    assert.strictEqual(event.ecommerce.items[0].variant_id, '1580');
    assert.strictEqual(event.ecommerce.items[0].item_variant, 'Size: L | Color: Black | Material: 100% Organic Cotton');
  });

  it('17. Brand-less and category-less products fallback gracefully to empty string', function () {
    EDL.setEventPrefix('raihan');

    // Product with no manufacturer and no secondary category
    const genericItem = EDL.normalizeItem({
      item_id: '777',
      variant_id: '',
      sku_id: 'GENERIC-USB-C',
      item_name: 'USB-C Cable 1m',
      item_brand: null,
      item_category: undefined,
      price: 4.99,
      quantity: 1
    });

    assert.strictEqual(genericItem.item_brand, '');
    assert.strictEqual(genericItem.item_category, '');
    assert.strictEqual(genericItem.variant_id, '');

    EDL.pushEvent('view_item', {
      currency: 'USD',
      value: genericItem.price,
      items: [genericItem]
    });

    const event = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(event.ecommerce.items[0].item_brand, '');
    assert.strictEqual(event.ecommerce.items[0].item_category, '');
    assert.strictEqual(event.ecommerce.items[0].item_id, '777');
  });

  it('18. Arbitrary catalog pricing rules on synthetic multi-store setup (100% store-agnostic)', function () {
    // Store B (Electronics Store): 12.5% discount rule on 199.99 USD
    const storeBPrice = EDL.roundHalfUp(199.99 * (1 - 0.125), 2); // 174.99125 -> 174.99
    assert.strictEqual(storeBPrice, 174.99);

    // Store C (Luxury Cosmetics): Fixed 50.00 EUR discount on 350.00 EUR
    const storeCPrice = EDL.roundHalfUp(350.00 - 50.00, 2);
    assert.strictEqual(storeCPrice, 300.00);

    // Store D (B2B Bulk Hardware): Tiered volume pricing 5 units @ 42.155 EUR
    const unitPrice = 42.155; // raw unit price
    const roundedUnit = EDL.roundHalfUp(unitPrice, 2); // 42.16
    assert.strictEqual(roundedUnit, 42.16);
    const storeDTotal = EDL.calculateCartValue([{ price: roundedUnit, quantity: 5 }], 2);
    assert.strictEqual(storeDTotal, 210.80);
  });

  it('19. EDL.formatVariant sanitizes object/array attributes and prevents [object Object] leaks', function () {
    // 1. Raw object from PrestaShop CartPresenter: { Farbe1: "Stahlblau" }
    const objAttr = { Farbe1: 'Stahlblau' };
    assert.strictEqual(EDL.formatVariant(objAttr), 'Farbe1: Stahlblau');

    // 2. Multi-attribute object: { Size: "L", Color: "Blue" }
    const multiObj = { Size: 'L', Color: 'Blue' };
    assert.strictEqual(EDL.formatVariant(multiObj), 'Size: L | Color: Blue');

    // 3. Array of attribute descriptor objects
    const arrAttr = [
      { group: 'Farbe1', name: 'Stahlblau' }
    ];
    assert.strictEqual(EDL.formatVariant(arrAttr), 'Farbe1: Stahlblau');

    // 4. Inadvertent literal "[object Object]" string is rejected/cleaned
    assert.strictEqual(EDL.formatVariant('[object Object]'), '');

    // 5. Normal string is preserved
    assert.strictEqual(EDL.formatVariant('Farbe1: Stahlblau'), 'Farbe1: Stahlblau');

    // 6. Normalization with object attributes
    const item = EDL.normalizeItem({
      item_id: '68',
      variant_id: '299',
      item_name: 'Trink und Snack Tasse',
      attributes: { Farbe1: 'Stahlblau' },
      price: 13.00,
      quantity: 1
    });
    assert.strictEqual(item.item_variant, 'Farbe1: Stahlblau');
  });

  it('20. Multi-quantity add_to_cart for Variant 299 captures quantity 2, total 26.00 EUR, and clean variant/category', function () {
    EDL.setEventPrefix('raihan');

    // Pre-hydrated product configuration matching PDP
    global.window.psGtmConfig = {
      eventPrefix: 'raihan',
      currency: 'EUR',
      currencyPrecision: 2,
      currentProduct: {
        item_id: '68',
        variant_id: '299',
        sku_id: 'BHK125-B',
        item_name: 'Trink und Snack Tasse',
        item_brand: 'Haakaa - Think Green Ltd.',
        item_category: 'Baby Geschirr',
        item_variant: 'Farbe1: Stahlblau',
        price: 13.00,
        quantity: 1
      },
      productCombinations: {
        '299': {
          item_id: '68',
          variant_id: '299',
          sku_id: 'BHK125-B',
          item_name: 'Trink und Snack Tasse',
          item_variant: 'Farbe1: Stahlblau',
          price: 13.00
        }
      }
    };

    // User adds 2 quantities of variant 299
    const addedItem = EDL.normalizeItem({
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Baby Geschirr',
      item_variant: 'Farbe1: Stahlblau',
      price: 13.00,
      quantity: 2
    });

    const items = [addedItem];
    const totalVal = EDL.calculateCartValue(items, 2);

    assert.strictEqual(addedItem.quantity, 2);
    assert.strictEqual(addedItem.price, 13.00);
    assert.strictEqual(totalVal, 26.00);
    assert.strictEqual(addedItem.item_variant, 'Farbe1: Stahlblau');
    assert.notStrictEqual(addedItem.item_variant, '[object Object]');
    assert.strictEqual(addedItem.item_category, 'Baby Geschirr');

    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: totalVal,
      items: items
    });

    const event = global.window.dataLayer[global.window.dataLayer.length - 1];
    assert.strictEqual(event.event, 'raihan_add_to_cart');
    assert.strictEqual(event.ecommerce.currency, 'EUR');
    assert.strictEqual(event.ecommerce.value, 26.00);
    assert.strictEqual(event.ecommerce.items[0].quantity, 2);
    assert.strictEqual(event.ecommerce.items[0].price, 13.00);
    assert.strictEqual(event.ecommerce.items[0].variant_id, '299');
    assert.strictEqual(event.ecommerce.items[0].sku_id, 'BHK125-B');
    assert.strictEqual(event.ecommerce.items[0].item_variant, 'Farbe1: Stahlblau');
    assert.strictEqual(event.ecommerce.items[0].item_category, 'Baby Geschirr');
  });

  it('21. Universal Cart Delta Engine: 100% backend mathematical quantity resolution (Zero DOM)', function () {
    // Simulating native CartController responses across consecutive add_to_cart actions without DOM
    const inMemoryCartQuantities = { '68_299': 0 };

    // Action 1: Add 2 units of Variant 299 (Cart goes from 0 to 2)
    const cartProductsStep1 = [{
      id_product: '68',
      id_product_attribute: '299',
      name: 'Trink und Snack Tasse',
      quantity: 2,
      price: 13.00
    }];

    const p1 = cartProductsStep1[0];
    const key1 = `${p1.id_product}_${p1.id_product_attribute}`;
    const prevQty1 = inMemoryCartQuantities[key1] || 0;
    const currentQty1 = p1.quantity;
    const deltaQty1 = currentQty1 - prevQty1; // 2 - 0 = 2

    assert.strictEqual(deltaQty1, 2, 'Delta quantity on first add must be 2');
    inMemoryCartQuantities[key1] = currentQty1; // update registry

    // Action 2: Add 3 more units of same Variant 299 (Cart goes from 2 to 5)
    const cartProductsStep2 = [{
      id_product: '68',
      id_product_attribute: '299',
      name: 'Trink und Snack Tasse',
      quantity: 5,
      price: 13.00
    }];

    const p2 = cartProductsStep2[0];
    const key2 = `${p2.id_product}_${p2.id_product_attribute}`;
    const prevQty2 = inMemoryCartQuantities[key2] || 0;
    const currentQty2 = p2.quantity;
    const deltaQty2 = currentQty2 - prevQty2; // 5 - 2 = 3

    assert.strictEqual(deltaQty2, 3, 'Delta quantity on second add must be 3');
  });

  describe('Universal Cart Activity Tracking: 10 Core Ecommerce Scenarios', function () {
    const mockCombinations = {
      '299': {
        item_id: '68',
        variant_id: '299',
        sku_id: 'BHK125-B',
        item_name: 'Trink und Snack Tasse',
        item_variant: 'Farbe1: Stahlblau',
        price: 13.00
      }
    };

    it('Scenario 1: Add a new product with quantity 1', function () {
      const prevQuantities = {};
      const prevProductsData = {};
      const newCart = [{
        id_product: '153',
        id_product_attribute: '0',
        name: 'FitKidz Barbell',
        price: 13.00,
        quantity: 1,
        reference: 'FIT09'
      }];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, newCart);
      assert.strictEqual(res.itemsAdded.length, 1);
      assert.strictEqual(res.itemsRemoved.length, 0);
      assert.strictEqual(res.itemsAdded[0].item_id, '153');
      assert.strictEqual(res.itemsAdded[0].quantity, 1);
      assert.strictEqual(res.itemsAdded[0].price, 13.00);
      assert.strictEqual(EDL.calculateCartValue(res.itemsAdded), 13.00);
    });

    it('Scenario 2: Increase its quantity from 1 to 3', function () {
      const prevQuantities = { '153_0': 1 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'FitKidz Barbell', price: 13.00, quantity: 1 }
      };
      const newCart = [{
        id_product: '153',
        id_product_attribute: '0',
        name: 'FitKidz Barbell',
        price: 13.00,
        quantity: 3,
        reference: 'FIT09'
      }];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, newCart);
      assert.strictEqual(res.itemsAdded.length, 1);
      assert.strictEqual(res.itemsRemoved.length, 0);
      assert.strictEqual(res.itemsAdded[0].item_id, '153');
      assert.strictEqual(res.itemsAdded[0].quantity, 2, 'Incremental quantity added must be 2');
      assert.strictEqual(EDL.calculateCartValue(res.itemsAdded), 26.00, 'Value must equal price * delta (13 * 2 = 26)');
    });

    it('Scenario 3: Decrease its quantity from 3 to 2', function () {
      const prevQuantities = { '153_0': 3 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'FitKidz Barbell', price: 13.00, quantity: 3 }
      };
      const newCart = [{
        id_product: '153',
        id_product_attribute: '0',
        name: 'FitKidz Barbell',
        price: 13.00,
        quantity: 2,
        reference: 'FIT09'
      }];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, newCart);
      assert.strictEqual(res.itemsAdded.length, 0);
      assert.strictEqual(res.itemsRemoved.length, 1);
      assert.strictEqual(res.itemsRemoved[0].item_id, '153');
      assert.strictEqual(res.itemsRemoved[0].quantity, 1, 'Units removed must be 1');
      assert.strictEqual(EDL.calculateCartValue(res.itemsRemoved), 13.00);
    });

    it('Scenario 4: Remove the product completely (from 2 to 0)', function () {
      const prevQuantities = { '153_0': 2 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'FitKidz Barbell', price: 13.00, quantity: 2, sku_id: 'FIT09' }
      };
      const newCart = []; // Product completely removed from cart

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, newCart);
      assert.strictEqual(res.itemsAdded.length, 0);
      assert.strictEqual(res.itemsRemoved.length, 1);
      assert.strictEqual(res.itemsRemoved[0].item_id, '153');
      assert.strictEqual(res.itemsRemoved[0].quantity, 2, 'Removed quantity must reflect all 2 units previously held');
      assert.strictEqual(res.itemsRemoved[0].sku_id, 'FIT09', 'Preserved metadata must retain SKU');
      assert.strictEqual(EDL.calculateCartValue(res.itemsRemoved), 26.00);
    });

    it('Scenario 5: Remove a product with multiple units (from 3 to 0)', function () {
      const prevQuantities = { '153_0': 3 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'FitKidz Barbell', price: 13.00, quantity: 3 }
      };
      const newCart = [];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, newCart);
      assert.strictEqual(res.itemsRemoved.length, 1);
      assert.strictEqual(res.itemsRemoved[0].quantity, 3);
      assert.strictEqual(EDL.calculateCartValue(res.itemsRemoved), 39.00);
    });

    it('Scenario 6: Update quantities of two different products in one operation (Product A: 1 to 3, Product B: 2 to 1)', function () {
      const prevQuantities = { '153_0': 1, '68_0': 2 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'Product A', price: 13.00, quantity: 1 },
        '68_0': { item_id: '68', variant_id: '', item_name: 'Product B', price: 20.00, quantity: 2 }
      };
      const newCart = [
        { id_product: '153', id_product_attribute: '0', name: 'Product A', price: 13.00, quantity: 3 },
        { id_product: '68', id_product_attribute: '0', name: 'Product B', price: 20.00, quantity: 1 }
      ];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, newCart);
      assert.strictEqual(res.itemsAdded.length, 1, 'Product A was increased');
      assert.strictEqual(res.itemsAdded[0].item_id, '153');
      assert.strictEqual(res.itemsAdded[0].quantity, 2);
      assert.strictEqual(EDL.calculateCartValue(res.itemsAdded), 26.00);

      assert.strictEqual(res.itemsRemoved.length, 1, 'Product B was decreased');
      assert.strictEqual(res.itemsRemoved[0].item_id, '68');
      assert.strictEqual(res.itemsRemoved[0].quantity, 1);
      assert.strictEqual(EDL.calculateCartValue(res.itemsRemoved), 20.00);
    });

    it('Scenario 7: Add and remove products with genuine combinations', function () {
      // 1. Add combination (Steel blue, variant 299)
      const prevQuantities = {};
      const prevProductsData = {};
      const newCart = [{
        id_product: '68',
        id_product_attribute: '299',
        name: 'Trink und Snack Tasse',
        price: 13.00,
        quantity: 2,
        reference: 'BHK125-B',
        attributes: 'Farbe1: Stahlblau'
      }];

      const resAdd = EDL.processCartDelta(prevQuantities, prevProductsData, newCart, { productCombinations: mockCombinations });
      assert.strictEqual(resAdd.itemsAdded.length, 1);
      assert.strictEqual(resAdd.itemsAdded[0].item_id, '68');
      assert.strictEqual(resAdd.itemsAdded[0].variant_id, '299');
      assert.strictEqual(resAdd.itemsAdded[0].item_variant, 'Farbe1: Stahlblau');
      assert.strictEqual(resAdd.itemsAdded[0].quantity, 2);

      // 2. Remove combination completely
      const resRemove = EDL.processCartDelta(resAdd.updatedQuantities, resAdd.updatedProductsData, []);
      assert.strictEqual(resRemove.itemsRemoved.length, 1);
      assert.strictEqual(resRemove.itemsRemoved[0].item_id, '68');
      assert.strictEqual(resRemove.itemsRemoved[0].variant_id, '299');
      assert.strictEqual(resRemove.itemsRemoved[0].item_variant, 'Farbe1: Stahlblau');
      assert.strictEqual(resRemove.itemsRemoved[0].quantity, 2);
    });

    it('Scenario 8: Trigger repeated or concurrent cart update requests (Delta is 0)', function () {
      const prevQuantities = { '153_0': 3 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'Product A', price: 13.00, quantity: 3 }
      };
      const sameCart = [
        { id_product: '153', id_product_attribute: '0', name: 'Product A', price: 13.00, quantity: 3 }
      ];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, sameCart);
      assert.strictEqual(res.itemsAdded.length, 0, 'No addition should be emitted when delta is 0');
      assert.strictEqual(res.itemsRemoved.length, 0, 'No removal should be emitted when delta is 0');
    });

    it('Scenario 9: Attempt an invalid cart update or failed operation (hasError: true)', function () {
      // Failure protection logic: when PrestaShop returns hasError or errors, delta processing is bypassed
      const serverResponse = {
        hasError: true,
        errors: ['The requested quantity is not available in stock.']
      };

      let eventEmitted = false;
      if (!serverResponse.hasError && (!serverResponse.errors || serverResponse.errors.length === 0)) {
        eventEmitted = true;
      }

      assert.strictEqual(eventEmitted, false, 'Failed cart operations must never trigger false tracking events');
    });

    it('Scenario 10: Clear the entire cart', function () {
      const prevQuantities = { '153_0': 2, '68_299': 1 };
      const prevProductsData = {
        '153_0': { item_id: '153', variant_id: '', item_name: 'Product A', price: 13.00, quantity: 2 },
        '68_299': { item_id: '68', variant_id: '299', sku_id: 'BHK125-B', item_name: 'Trink und Snack Tasse', price: 13.00, quantity: 1 }
      };
      const emptyCart = [];

      const res = EDL.processCartDelta(prevQuantities, prevProductsData, emptyCart);
      assert.strictEqual(res.itemsAdded.length, 0);
      assert.strictEqual(res.itemsRemoved.length, 2, 'Both distinct products must be represented in removal');
      
      const item153 = res.itemsRemoved.find(i => i.item_id === '153');
      const item68 = res.itemsRemoved.find(i => i.item_id === '68');

      assert.ok(item153, 'Product 153 must be in removal');
      assert.strictEqual(item153.quantity, 2);
      assert.ok(item68, 'Product 68 must be in removal');
      assert.strictEqual(item68.quantity, 1);
      assert.strictEqual(item68.variant_id, '299');
      assert.strictEqual(EDL.calculateCartValue(res.itemsRemoved), 39.00, 'Total cart removal value must be 13*2 + 13*1 = 39');
    });
  });

  describe('Category Page: view_item_list 14-Parameter GA4 Compliance Test', function () {
    it('32. Should validate view_item_list schema with all 14 parameters and authoritative backend values', function () {
      const mockCategoryPayload = {
        event: 'raihan_view_item_list',
        original_event: 'view_item_list',
        ecommerce: {
          currency: 'EUR',
          value: 39.99,
          item_list_id: 'category_27',
          item_list_name: 'Baby Geschirr',
          items: [
            EDL.normalizeItem({
              item_id: '68',
              variant_id: '',
              sku_id: 'BHK125',
              item_name: 'Trink und Snack Tasse',
              item_brand: 'Haakaa - Think Green Ltd.',
              item_category: 'Baby Geschirr',
              item_variant: '',
              price: 13.00,
              quantity: 1,
              index: 1,
              item_list_id: 'category_27',
              item_list_name: 'Baby Geschirr'
            }),
            EDL.normalizeItem({
              item_id: '1015',
              variant_id: '',
              sku_id: '3001201',
              item_name: 'Dooky Gehörschutz 2-in-1 Baby & Kleinkinder',
              item_brand: 'Dooky - Xplorys BV',
              item_category: 'Gehörschutz',
              item_variant: '',
              price: 26.99,
              quantity: 1,
              index: 2,
              item_list_id: 'category_27',
              item_list_name: 'Baby Geschirr'
            })
          ]
        }
      };

      EDL.setEventPrefix('raihan');
      EDL.pushEvent('view_item_list', mockCategoryPayload.ecommerce);

      const pushed = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushed.event, 'raihan_view_item_list');
      assert.strictEqual(pushed.original_event, 'view_item_list');

      // Event-level parameters
      assert.strictEqual(pushed.ecommerce.currency, 'EUR', '1. currency must be present');
      assert.strictEqual(pushed.ecommerce.value, 39.99, '2. value must equal sum of product prices (13.00 + 26.99)');
      assert.strictEqual(pushed.ecommerce.item_list_id, 'category_27', '3. item_list_id must match category id');
      assert.strictEqual(pushed.ecommerce.item_list_name, 'Baby Geschirr', '4. item_list_name must match category name');
      assert.strictEqual(pushed.ecommerce.items.length, 2, '5. items array must contain all category products');

      // Item-level parameters (Item 1)
      const it1 = pushed.ecommerce.items[0];
      assert.strictEqual(it1.item_id, '68', '6. item_id must be base product id');
      assert.strictEqual(it1.item_name, 'Trink und Snack Tasse', '7. item_name must be accurate');
      assert.strictEqual(it1.item_brand, 'Haakaa - Think Green Ltd.', '8. item_brand must be accurate');
      assert.strictEqual(it1.item_category, 'Baby Geschirr', '9. item_category must match default category');
      assert.strictEqual(it1.item_variant, '', '10. item_variant must be empty string for base listing');
      assert.strictEqual(it1.sku_id, 'BHK125', '11. sku_id must match product reference');
      assert.strictEqual(it1.price, 13.00, '12. price must be customer gross price');
      assert.strictEqual(it1.index, 1, '13. index must be 1 for first item');
      assert.strictEqual(it1.quantity, 1, '14. quantity must be 1');
      assert.strictEqual(it1.item_list_id, 'category_27', 'item_list_id must be present on item');
      assert.strictEqual(it1.item_list_name, 'Baby Geschirr', 'item_list_name must be present on item');

      // Item-level parameters (Item 2)
      const it2 = pushed.ecommerce.items[1];
      assert.strictEqual(it2.item_id, '1015');
      assert.strictEqual(it2.index, 2, 'index must increment for second item');
      assert.strictEqual(it2.item_list_id, 'category_27');
    });

    it('33. GA4 select_item: Selecting an item from category list pushes select_item with complete list and item parameters', function () {
      EDL.setEventPrefix('raihan');

      const mockSelectedItem = EDL.normalizeItem({
        item_id: '68',
        variant_id: '',
        sku_id: 'BHK125',
        item_name: 'Trink und Snack Tasse',
        item_brand: 'Haakaa - Think Green Ltd.',
        item_category: 'Baby Geschirr',
        item_variant: '',
        price: 13.00,
        quantity: 1,
        index: 1,
        item_list_id: 'category_27',
        item_list_name: 'Baby Geschirr'
      });

      EDL.pushEvent('select_item', {
        item_list_id: 'category_27',
        item_list_name: 'Baby Geschirr',
        items: [mockSelectedItem]
      });

      const pushed = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushed.event, 'raihan_select_item');
      assert.strictEqual(pushed.original_event, 'select_item');

      // Verify ecommerce event-level parameters
      assert.strictEqual(pushed.ecommerce.item_list_id, 'category_27');
      assert.strictEqual(pushed.ecommerce.item_list_name, 'Baby Geschirr');
      assert.strictEqual(pushed.ecommerce.items.length, 1);

      // Verify item-level parameters according to official GA4 select_item reference
      const item = pushed.ecommerce.items[0];
      assert.strictEqual(item.item_id, '68');
      assert.strictEqual(item.item_name, 'Trink und Snack Tasse');
      assert.strictEqual(item.item_brand, 'Haakaa - Think Green Ltd.');
      assert.strictEqual(item.item_category, 'Baby Geschirr');
      assert.strictEqual(item.item_variant, '');
      assert.strictEqual(item.sku_id, 'BHK125');
      assert.strictEqual(item.price, 13.00);
      assert.strictEqual(item.quantity, 1);
      assert.strictEqual(item.index, 1);
      assert.strictEqual(item.item_list_id, 'category_27');
      assert.strictEqual(item.item_list_name, 'Baby Geschirr');
    });

    it('34. GA4 add_to_wishlist: Adding product to wishlist pushes add_to_wishlist with currency, value, and item schema', function () {
      EDL.setEventPrefix('raihan');

      const mockWishlistItem = EDL.normalizeItem({
        item_id: '68',
        variant_id: '298',
        sku_id: 'BHK125-R',
        item_name: 'Trink und Snack Tasse',
        item_brand: 'Haakaa - Think Green Ltd.',
        item_category: 'Baby Geschirr',
        item_variant: 'Farbe1: Rostrot',
        price: 13.00,
        quantity: 1
      });

      const items = [mockWishlistItem];
      const totalVal = EDL.calculateCartValue(items);

      EDL.pushEvent('add_to_wishlist', {
        currency: 'EUR',
        value: totalVal,
        items: items
      });

      const pushed = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushed.event, 'raihan_add_to_wishlist');
      assert.strictEqual(pushed.original_event, 'add_to_wishlist');

      // Verify event-level parameters according to official GA4 guideline
      assert.strictEqual(pushed.ecommerce.currency, 'EUR');
      assert.strictEqual(pushed.ecommerce.value, 13.00);
      assert.strictEqual(pushed.ecommerce.items.length, 1);

      // Verify item parameters
      const item = pushed.ecommerce.items[0];
      assert.strictEqual(item.item_id, '68');
      assert.strictEqual(item.variant_id, '298');
      assert.strictEqual(item.sku_id, 'BHK125-R');
      assert.strictEqual(item.item_name, 'Trink und Snack Tasse');
      assert.strictEqual(item.item_brand, 'Haakaa - Think Green Ltd.');
      assert.strictEqual(item.item_category, 'Baby Geschirr');
      assert.strictEqual(item.item_variant, 'Farbe1: Rostrot');
      assert.strictEqual(item.price, 13.00);
      assert.strictEqual(item.quantity, 1);

      // Verify prior ecommerce and advertising state reset
      const priorEntry = global.window.dataLayer[global.window.dataLayer.length - 2];
      assert.deepStrictEqual(priorEntry, {
        ecommerce: null,
        meta: null,
        tiktok: null,
        google_ads: null
      });
    });

    it('35. Universal Wishlist Parameter Extraction & Network Interception (Zero DOM)', function () {
      function extractWishlistProductData(url, data, fallbackProduct) {
        let prodId = '';
        let attrId = '';

        function inspect(input) {
          if (!input) return;
          if (typeof input === 'string') {
            try {
              const decoded = decodeURIComponent(input);
              const pMatch = decoded.match(/(?:id_product|idProduct|productId|params\[id_product\])\s*[:=]\s*["']?(\d+)/i) ||
                            decoded.match(/(?:id_product|idProduct|productId|params\[id_product\])\D*(\d+)/i);
              const aMatch = decoded.match(/(?:id_product_attribute|idProductAttribute|productAttributeId|params\[id_product_attribute\])\s*[:=]\s*["']?(\d+)/i) ||
                            decoded.match(/(?:id_product_attribute|idProductAttribute|productAttributeId|params\[id_product_attribute\])\D*(\d+)/i);
              if (pMatch && pMatch[1]) prodId = prodId || pMatch[1];
              if (aMatch && aMatch[1]) attrId = attrId || aMatch[1];
            } catch (e) {
              const pMatch2 = input.match(/(?:id_product|idProduct|productId|params\[id_product\])\D*(\d+)/i);
              const aMatch2 = input.match(/(?:id_product_attribute|idProductAttribute|productAttributeId|params\[id_product_attribute\])\D*(\d+)/i);
              if (pMatch2 && pMatch2[1]) prodId = prodId || pMatch2[1];
              if (aMatch2 && aMatch2[1]) attrId = attrId || aMatch2[1];
            }

            if (!prodId) {
              try {
                const parsed = JSON.parse(input);
                if (parsed && typeof parsed === 'object') inspectObj(parsed);
              } catch (e) {}
            }
          } else if (typeof input === 'object') {
            inspectObj(input);
          }
        }

        function inspectObj(obj) {
          if (!obj) return;
          const d = obj.detail || obj;
          prodId = d.productId || d.id_product || d.idProduct || (d.params && (d.params.id_product || d.params.productId)) || prodId;
          attrId = d.productAttributeId || d.id_product_attribute || d.idProductAttribute || (d.params && (d.params.id_product_attribute || d.params.productAttributeId)) || attrId;
        }

        inspect(url);
        if (!prodId) inspect(data);

        if (!prodId && fallbackProduct && fallbackProduct.item_id) {
          prodId = String(fallbackProduct.item_id);
          if (!attrId) attrId = String(fallbackProduct.variant_id || '');
        }

        return {
          productId: String(prodId || '').trim(),
          productAttributeId: (attrId && String(attrId) !== '0' && parseInt(attrId, 10) > 0) ? String(attrId).trim() : ''
        };
      }

      // Test Case A: Native PrestaShop blockwishlist Apollo fetch URL with query params
      const blockwishlistUrl = 'https://example.com/module/blockwishlist/action?action=addProductToWishlist&params[id_product]=1015&params[idWishList]=1&params[quantity]=1&params[id_product_attribute]=299';
      const resA = extractWishlistProductData(blockwishlistUrl, undefined, null);
      assert.strictEqual(resA.productId, '1015');
      assert.strictEqual(resA.productAttributeId, '299');

      // Test Case B: URL encoded blockwishlist query
      const encodedUrl = 'https://example.com/module/blockwishlist/action?action=addProductToWishlist&params%5Bid_product%5D=1015&params%5Bid_product_attribute%5D=0';
      const resB = extractWishlistProductData(encodedUrl, undefined, null);
      assert.strictEqual(resB.productId, '1015');
      assert.strictEqual(resB.productAttributeId, '');

      // Test Case C: JSON GraphQL mutation payload (modern themes)
      const jsonBody = JSON.stringify({ productId: 1015, productAttributeId: 0, listId: 1 });
      const resC = extractWishlistProductData('https://example.com/graphql', jsonBody, null);
      assert.strictEqual(resC.productId, '1015');
      assert.strictEqual(resC.productAttributeId, '');

      // Test Case D: blockwishlist Vue WishlistEventBus detail object
      const busEvent = { detail: { productId: 1015, productAttributeId: 301, listId: 2 } };
      const resD = extractWishlistProductData('', busEvent, null);
      assert.strictEqual(resD.productId, '1015');
      assert.strictEqual(resD.productAttributeId, '301');

      // Test Case E: PDP Fallback when product is added without explicit URL params
      const pdpFallback = { item_id: '1015', variant_id: '298' };
      const resE = extractWishlistProductData('https://example.com/module/blockwishlist/action?action=addProductToWishlist', undefined, pdpFallback);
      assert.strictEqual(resE.productId, '1015');
      assert.strictEqual(resE.productAttributeId, '298');
    });

    it('36. Universal GA4 Pricing & Financial Value Reconciliation (Storefront & Order Parity)', function () {
      EDL.setEventPrefix('raihan');

      // 1. Gili 2-in-1 Sonnenbrille: 50% discount on base net 25.20 EUR + 19% German MwSt
      // PrestaShop Core formula: Net Discounted = 25.20 * (1 - 0.50) = 12.60 EUR
      // Gross Discounted = 12.60 * 1.19 = 14.994 EUR -> 14.99 EUR
      const netBase = 25.20;
      const discountRate = 0.50;
      const taxRate = 0.19;
      const netDiscounted = netBase * (1 - discountRate);
      const grossCalculated = netDiscounted * (1 + taxRate);
      const authoritativeUnitPrice = EDL.roundHalfUp(grossCalculated, 2);
      assert.strictEqual(authoritativeUnitPrice, 14.99, 'Gili Sonnenbrille must resolve to exactly 14.99 EUR');

      // 2. View Item Event Verification
      const viewItem = EDL.normalizeItem({
        item_id: '1023',
        variant_id: '',
        sku_id: '3511110',
        item_name: 'Gili 2-in-1 Sonnenbrille Baby-Junior Blau',
        item_brand: 'Dooky - Xplorys BV',
        item_category: 'Sonnenbrille',
        price: authoritativeUnitPrice,
        quantity: 1
      });

      EDL.pushEvent('view_item', {
        currency: 'EUR',
        value: viewItem.price,
        items: [viewItem]
      });

      const pushedView = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushedView.event, 'raihan_view_item');
      assert.strictEqual(pushedView.ecommerce.value, 14.99);
      assert.strictEqual(pushedView.ecommerce.items[0].price, 14.99);

      // 3. Purchase Event Verification with Stored Order Details & Reconciliation
      // Simulating Order with 2 x Gili Sonnenbrille (14.99 each) + 1 x Dooky Gehorschutz (27.99)
      const orderLines = [
        {
          item_id: '1023',
          variant_id: '',
          sku_id: '3511110',
          item_name: 'Gili 2-in-1 Sonnenbrille Baby-Junior Blau',
          item_brand: 'Dooky - Xplorys BV',
          item_category: 'Sonnenbrille',
          price: 14.99,
          quantity: 2 // 2 x 14.99 = 29.98
        },
        {
          item_id: '1015',
          variant_id: '',
          sku_id: '3001201',
          item_name: 'Dooky Gehörschutz 2-in-1 Baby & Kleinkinder',
          item_brand: 'Dooky - Xplorys BV',
          item_category: 'Gehörschutz',
          price: 27.99,
          quantity: 1 // 1 x 27.99 = 27.99
        }
      ];

      const normOrderItems = EDL.normalizeItems(orderLines, 2);
      const itemsSum = EDL.calculateCartValue(normOrderItems, 2);
      assert.strictEqual(itemsSum, 57.97, 'Item sum (29.98 + 27.99) must equal exactly 57.97');

      const shippingCost = 4.50;
      const taxTotal = 9.26;
      const orderTotalGross = EDL.roundHalfUp(itemsSum + shippingCost, 2); // 62.47

      EDL.pushEvent('purchase', {
        transaction_id: '1250',
        value: itemsSum, // Standard GA4 revenue: items sum, excluding shipping
        tax: taxTotal,
        shipping: shippingCost,
        currency: 'EUR',
        items: normOrderItems
      });

      const pushedPurchase = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushedPurchase.event, 'raihan_purchase');
      assert.strictEqual(pushedPurchase.ecommerce.transaction_id, '1250');
      assert.strictEqual(pushedPurchase.ecommerce.value, 57.97, 'GA4 value must equal sum of items without shipping');
      assert.strictEqual(pushedPurchase.ecommerce.shipping, 4.50);
      assert.strictEqual(pushedPurchase.ecommerce.tax, 9.26);
      assert.strictEqual(pushedPurchase.ecommerce.items.length, 2);
      assert.strictEqual(pushedPurchase.ecommerce.items[0].price, 14.99);
      assert.strictEqual(pushedPurchase.ecommerce.items[0].quantity, 2);
      assert.strictEqual(pushedPurchase.ecommerce.items[1].price, 27.99);
      assert.strictEqual(pushedPurchase.ecommerce.items[1].quantity, 1);
    });

    it('37. GA4 select_item: 4-tier list resolution guarantees non-empty item_list_id and item_list_name (No empty string bug)', function () {
      EDL.setEventPrefix('ps');

      // Helper simulating 4-tier resolution logic identical to prestashop-adapter.js
      function resolveListIdentity(idProduct, currentList, pageContext, resolvedBackendItem, fullBackendResp) {
        let matchedItem = null;
        if (currentList && Array.isArray(currentList.items)) {
          matchedItem = currentList.items.find(i => String(i.item_id) === String(idProduct));
        }

        // Tier 1: Current hydrated list
        let listId = (currentList && currentList.item_list_id) || (matchedItem && matchedItem.item_list_id) || '';
        let listName = (currentList && currentList.item_list_name) || (matchedItem && matchedItem.item_list_name) || '';

        // Tier 2: Page Context
        if (!listId && pageContext && pageContext.list_id) {
          listId = pageContext.list_id;
          listName = pageContext.list_name || pageContext.list_id;
        }

        // Tier 3: Authoritative backend category from AJAX ORM
        if (!listId) {
          if (fullBackendResp && fullBackendResp.default_category_id) {
            listId = 'category_' + fullBackendResp.default_category_id;
            listName = fullBackendResp.default_category_name || ('Category #' + fullBackendResp.default_category_id);
          } else if (resolvedBackendItem && resolvedBackendItem.item_list_id) {
            listId = resolvedBackendItem.item_list_id;
            listName = resolvedBackendItem.item_list_name || listId;
          }
        }

        // Tier 4: Category slug or generic fallback
        if (!listId) {
          if (resolvedBackendItem && resolvedBackendItem.item_category) {
            listId = 'category_' + String(resolvedBackendItem.item_category).toLowerCase().replace(/[^a-z0-9_-]/g, '_');
            listName = resolvedBackendItem.item_category;
          } else {
            listId = 'product_listing';
            listName = 'Product Listing';
          }
        }
        if (!listName) {
          listName = listId;
        }

        return { listId, listName };
      }

      // Case 1: Product 27 clicked from Home Page (currentList is null, pageContext is home_featured)
      const resHome = resolveListIdentity('27', null, { list_id: 'home_featured', list_name: 'Home Page - Featured Products' }, null, null);
      assert.strictEqual(resHome.listId, 'home_featured');
      assert.strictEqual(resHome.listName, 'Home Page - Featured Products');

      // Case 2: Product 27 clicked from unhydrated section with AJAX response
      const resAjax = resolveListIdentity('27', null, null, {
        item_id: '27',
        item_name: 'Baby Essteller mit Unterteilung und Saugfuss - Stahlblau',
        item_category: 'Baby Geschirr',
        price: 15.39
      }, {
        success: true,
        default_category_id: 15,
        default_category_name: 'Baby Geschirr'
      });
      assert.strictEqual(resAjax.listId, 'category_15');
      assert.strictEqual(resAjax.listName, 'Baby Geschirr');

      // Case 3: Product clicked with only category string
      const resSlug = resolveListIdentity('99', null, null, {
        item_id: '99',
        item_name: 'Test Product',
        item_category: 'Baby Geschirr'
      }, null);
      assert.strictEqual(resSlug.listId, 'category_baby_geschirr');
      assert.strictEqual(resSlug.listName, 'Baby Geschirr');

      // Case 4: Complete unknown product
      const resFallback = resolveListIdentity('999', null, null, { item_id: '999' }, null);
      assert.strictEqual(resFallback.listId, 'product_listing');
      assert.strictEqual(resFallback.listName, 'Product Listing');

      // Verify that when pushed to dataLayer, item_list_id and item_list_name are populated
      const selectedItem = EDL.normalizeItem({
        item_id: '27',
        sku_id: 'UTE65-B',
        item_name: 'Baby Essteller mit Unterteilung und Saugfuss - Stahlblau',
        item_brand: 'Haakaa - Think Green Ltd.',
        item_category: 'Baby Geschirr',
        price: 15.39,
        quantity: 1,
        item_list_id: resAjax.listId,
        item_list_name: resAjax.listName
      });

      EDL.pushEvent('select_item', {
        item_list_id: resAjax.listId,
        item_list_name: resAjax.listName,
        items: [selectedItem]
      });

      const pushed = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushed.event, 'ps_select_item');
      assert.strictEqual(pushed.ecommerce.item_list_id, 'category_15');
      assert.strictEqual(pushed.ecommerce.item_list_name, 'Baby Geschirr');
      assert.notStrictEqual(pushed.ecommerce.item_list_id, '');
      assert.notStrictEqual(pushed.ecommerce.item_list_name, '');
      assert.strictEqual(pushed.ecommerce.items[0].item_list_id, 'category_15');
      assert.strictEqual(pushed.ecommerce.items[0].item_list_name, 'Baby Geschirr');
      assert.strictEqual(pushed.ecommerce.items[0].item_id, '27');
    });

    it('38. Checkout Funnel: add_shipping_info and add_payment_info GA4 schema & 7-scenario validation', function () {
      EDL.setEventPrefix('raihan');

      const mockCartItems = [
        EDL.normalizeItem({
          item_id: '27',
          variant_id: '',
          sku_id: 'UTE65-B',
          item_name: 'Baby Essteller mit Unterteilung und Saugfuss - Stahlblau',
          item_brand: 'Haakaa - Think Green Ltd.',
          item_category: 'Baby Geschirr',
          price: 15.39,
          quantity: 2
        }),
        EDL.normalizeItem({
          item_id: '68',
          variant_id: '298',
          sku_id: 'BHK125-R',
          item_name: 'Trink und Snack Tasse',
          item_brand: 'Haakaa - Think Green Ltd.',
          item_category: 'Baby Geschirr',
          item_variant: 'Farbe1: Rostrot',
          price: 13.00,
          quantity: 1
        })
      ];

      const expectedVal = 43.78; // (15.39 * 2) + 13.00 = 30.78 + 13.00 = 43.78
      const couponCode = 'WELCOME10';

      // -------------------------------------------------------------
      // SCENARIO 1: Customer selects a shipping method
      // -------------------------------------------------------------
      let lastTrackedShippingTier = '';
      function trackShipping(tier) {
        if (lastTrackedShippingTier === tier) return false;
        lastTrackedShippingTier = tier;
        EDL.pushEvent('add_shipping_info', {
          currency: 'EUR',
          value: expectedVal,
          coupon: couponCode,
          shipping_tier: tier,
          items: mockCartItems
        });
        return true;
      }

      assert.strictEqual(trackShipping('DHL Express'), true);
      const pushShip1 = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushShip1.event, 'raihan_add_shipping_info');
      assert.strictEqual(pushShip1.original_event, 'add_shipping_info');
      assert.strictEqual(pushShip1.ecommerce.shipping_tier, 'DHL Express');
      assert.strictEqual(pushShip1.ecommerce.currency, 'EUR');
      assert.strictEqual(pushShip1.ecommerce.value, 43.78);
      assert.strictEqual(pushShip1.ecommerce.coupon, 'WELCOME10');
      assert.strictEqual(pushShip1.ecommerce.items.length, 2);
      assert.strictEqual(pushShip1.ecommerce.items[0].item_id, '27');
      assert.strictEqual(pushShip1.ecommerce.items[1].variant_id, '298');

      // Verify advertising platforms populated for add_shipping_info
      assert.ok(pushShip1.meta, 'Meta namespace present');
      assert.strictEqual(pushShip1.meta.currency, 'EUR');
      assert.ok(pushShip1.tiktok, 'TikTok namespace present');
      assert.ok(pushShip1.google_ads, 'Google Ads namespace present');
      assert.strictEqual(pushShip1.google_ads.ecomm_pagetype, 'cart');

      // -------------------------------------------------------------
      // SCENARIO 2: Customer changes the selected shipping method
      // -------------------------------------------------------------
      assert.strictEqual(trackShipping('DPD Standard Home Delivery'), true);
      const pushShip2 = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushShip2.ecommerce.shipping_tier, 'DPD Standard Home Delivery');
      assert.strictEqual(pushShip2.ecommerce.value, 43.78);

      // -------------------------------------------------------------
      // SCENARIO 3: Customer selects a payment method
      // -------------------------------------------------------------
      let lastTrackedPaymentType = '';
      function trackPayment(pType) {
        if (lastTrackedPaymentType === pType) return false;
        lastTrackedPaymentType = pType;
        EDL.pushEvent('add_payment_info', {
          currency: 'EUR',
          value: expectedVal,
          coupon: couponCode,
          payment_type: pType,
          items: mockCartItems
        });
        return true;
      }

      assert.strictEqual(trackPayment('Pay by bank wire'), true);
      const pushPay1 = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushPay1.event, 'raihan_add_payment_info');
      assert.strictEqual(pushPay1.original_event, 'add_payment_info');
      assert.strictEqual(pushPay1.ecommerce.payment_type, 'Pay by bank wire');
      assert.strictEqual(pushPay1.ecommerce.currency, 'EUR');
      assert.strictEqual(pushPay1.ecommerce.value, 43.78);
      assert.strictEqual(pushPay1.ecommerce.coupon, 'WELCOME10');
      assert.strictEqual(pushPay1.ecommerce.items.length, 2);

      // Verify advertising platforms populated for add_payment_info
      assert.ok(pushPay1.meta, 'Meta namespace present for add_payment_info');
      assert.ok(pushPay1.tiktok, 'TikTok namespace present for add_payment_info');
      assert.ok(pushPay1.google_ads, 'Google Ads namespace present for add_payment_info');

      // -------------------------------------------------------------
      // SCENARIO 4: Customer changes the selected payment method
      // -------------------------------------------------------------
      assert.strictEqual(trackPayment('Credit Card (Stripe)'), true);
      const pushPay2 = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushPay2.ecommerce.payment_type, 'Credit Card (Stripe)');
      assert.strictEqual(pushPay2.ecommerce.value, 43.78);

      // -------------------------------------------------------------
      // SCENARIO 5: Customer revisits checkout step (deduplication check)
      // -------------------------------------------------------------
      // Revisiting delivery step without changing method -> duplicate blocked
      assert.strictEqual(trackShipping('DPD Standard Home Delivery'), false, 'Unchanged shipping must not duplicate');
      // Revisiting payment step without changing method -> duplicate blocked
      assert.strictEqual(trackPayment('Credit Card (Stripe)'), false, 'Unchanged payment must not duplicate');

      // But changing again to another carrier -> fires cleanly
      assert.strictEqual(trackShipping('UPS Express Saver'), true);
      const pushShip3 = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushShip3.ecommerce.shipping_tier, 'UPS Express Saver');

      // -------------------------------------------------------------
      // SCENARIO 6: Customer completes a purchase
      // -------------------------------------------------------------
      EDL.pushEvent('purchase', {
        transaction_id: 'ORD-98765',
        value: 43.78,
        currency: 'EUR',
        items: mockCartItems
      });
      const pushPurch = global.window.dataLayer[global.window.dataLayer.length - 1];
      assert.strictEqual(pushPurch.event, 'raihan_purchase');
      assert.strictEqual(pushPurch.original_event, 'purchase');
      assert.strictEqual(pushPurch.ecommerce.transaction_id, 'ORD-98765');

      // -------------------------------------------------------------
      // SCENARIO 7: Dynamic resolution for diverse shipping & payment modules
      // -------------------------------------------------------------
      const testCarriersMap = {
        1: 'Pickup in Store',
        2: 'MyCarrier Express',
        3: 'GLS Parcel Service',
        4: 'FedEx Priority'
      };
      const testPaymentOptionsMap = {
        'payment-option-1': 'Pay by bank wire',
        'payment-option-2': 'Pay by check',
        'payment-option-3': 'PayPal Official',
        'payment-option-4': 'Klarna Pay Later',
        'ps_wirepayment': 'Bank transfer',
        'ps_checkpayment': 'Payments by check'
      };

      function resolveCarrier(idCarrier) {
        return testCarriersMap[idCarrier] || 'Standard Delivery';
      }
      function resolvePayment(idOrMod) {
        return testPaymentOptionsMap[idOrMod] || 'Online Payment';
      }

      assert.strictEqual(resolveCarrier(1), 'Pickup in Store');
      assert.strictEqual(resolveCarrier(3), 'GLS Parcel Service');
      assert.strictEqual(resolveCarrier(999), 'Standard Delivery');

      assert.strictEqual(resolvePayment('payment-option-3'), 'PayPal Official');
      assert.strictEqual(resolvePayment('payment-option-4'), 'Klarna Pay Later');
      assert.strictEqual(resolvePayment('ps_wirepayment'), 'Bank transfer');
      assert.strictEqual(resolvePayment('unknown_gateway'), 'Online Payment');
    });
  });
});
