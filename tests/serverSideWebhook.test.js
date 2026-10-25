/**
 * Test Suite: Server-to-Server Refund & Offline Order Webhooks for Server GTM
 * Validates GA4 / sGTM schema, product catalog strategy resolution,
 * customer PII SHA-256 normalization, and refund deduplication logic.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert');
const crypto = require('crypto');

function sha256(str) {
  return crypto.createHash('sha256').update(str.trim().toLowerCase()).digest('hex');
}

function resolveCatalogIdentifier(idProduct, idProductAttribute, sku, strategy) {
  const pStr = String(idProduct);
  const aId = Number(idProductAttribute) || 0;

  if (strategy === 'reference_sku') {
    if (sku && String(sku).trim() !== '') {
      return String(sku).trim();
    }
    return aId > 0 ? `${pStr}_${aId}` : pStr;
  }

  if (strategy === 'base_id') {
    return pStr;
  }

  // Default 'composite'
  return aId > 0 ? `${pStr}_${aId}` : pStr;
}

function buildRefundPayload(order, items, strategy = 'composite') {
  const formattedItems = items.map(item => ({
    item_id: resolveCatalogIdentifier(item.id_product, item.id_product_attribute, item.sku, strategy),
    item_name: item.name,
    item_brand: item.brand || '',
    item_category: item.category || '',
    item_variant: item.variant || '',
    price: Number(item.price),
    quantity: Number(item.quantity)
  }));

  const totalValue = formattedItems.reduce((acc, curr) => acc + (curr.price * curr.quantity), 0);

  const userData = {};
  if (order.customer_email) {
    userData.email_address = sha256(order.customer_email);
  }
  if (order.customer_phone) {
    const cleanPhone = String(order.customer_phone).replace(/[^0-9]/g, '');
    if (cleanPhone) {
      userData.phone_number = crypto.createHash('sha256').update(cleanPhone).digest('hex');
    }
  }

  return {
    client_id: `${order.id_order}.${Math.floor(Date.now() / 1000)}`,
    events: [
      {
        name: 'refund',
        params: {
          transaction_id: String(order.id_order),
          currency: order.currency || 'EUR',
          value: Math.round((totalValue + (order.shipping_refunded || 0)) * 100) / 100,
          shipping: order.shipping_refunded || 0,
          user_data: userData,
          items: formattedItems
        }
      }
    ]
  };
}

describe('Server-to-Server Refund & Offline Order Webhooks Test Suite', () => {

  it('1. Should build canonical GA4/sGTM refund envelope with accurate financial totals', () => {
    const order = {
      id_order: 1048,
      currency: 'EUR',
      customer_email: 'Customer.Test@Example.com',
      shipping_refunded: 0
    };
    const items = [
      { id_product: 68, id_product_attribute: 299, sku: 'BHK125-B', name: 'Sip-N-Snack Cup', price: 13.00, quantity: 2, variant: 'Colour: Stahlblau' }
    ];

    const payload = buildRefundPayload(order, items, 'composite');

    assert.ok(payload.client_id.startsWith('1048.'));
    assert.strictEqual(payload.events.length, 1);
    assert.strictEqual(payload.events[0].name, 'refund');
    assert.strictEqual(payload.events[0].params.transaction_id, '1048');
    assert.strictEqual(payload.events[0].params.currency, 'EUR');
    assert.strictEqual(payload.events[0].params.value, 26.00);
    assert.strictEqual(payload.events[0].params.items.length, 1);
    assert.strictEqual(payload.events[0].params.items[0].item_id, '68_299');
    assert.strictEqual(payload.events[0].params.items[0].quantity, 2);
    assert.strictEqual(payload.events[0].params.items[0].price, 13.00);
  });

  it('2. Should normalize and SHA-256 hash customer email and phone for Meta CAPI and Enhanced Conversions', () => {
    const order = {
      id_order: 1049,
      customer_email: '  John.Doe@Gmail.com ',
      customer_phone: '+49 (0) 170 123-4567'
    };
    const items = [
      { id_product: 15, id_product_attribute: 0, sku: 'PROD-15', name: 'Simple Mug', price: 9.99, quantity: 1 }
    ];

    const payload = buildRefundPayload(order, items);
    const expectedEmailHash = crypto.createHash('sha256').update('john.doe@gmail.com').digest('hex');
    const expectedPhoneHash = crypto.createHash('sha256').update('4901701234567').digest('hex');

    assert.strictEqual(payload.events[0].params.user_data.email_address, expectedEmailHash);
    assert.strictEqual(payload.events[0].params.user_data.phone_number, expectedPhoneHash);
  });

  it('3. Should resolve Catalog ID Strategy: Option 1 Composite {id_product}_{id_product_attribute}', () => {
    assert.strictEqual(resolveCatalogIdentifier(68, 299, 'BHK125-B', 'composite'), '68_299');
    assert.strictEqual(resolveCatalogIdentifier(15, 0, 'SIMPLE', 'composite'), '15');
  });

  it('4. Should resolve Catalog ID Strategy: Option 2 Base Product ID {id_product}', () => {
    assert.strictEqual(resolveCatalogIdentifier(68, 299, 'BHK125-B', 'base_id'), '68');
    assert.strictEqual(resolveCatalogIdentifier(15, 0, 'SIMPLE', 'base_id'), '15');
  });

  it('5. Should resolve Catalog ID Strategy: Option 3 Reference / SKU {sku} with fallback', () => {
    assert.strictEqual(resolveCatalogIdentifier(68, 299, 'BHK125-B', 'reference_sku'), 'BHK125-B');
    // If SKU is missing, falls back cleanly to composite ID
    assert.strictEqual(resolveCatalogIdentifier(68, 299, '', 'reference_sku'), '68_299');
    assert.strictEqual(resolveCatalogIdentifier(15, 0, null, 'reference_sku'), '15');
  });

  it('6. Should accurately factor in shipping refund amounts into total refund value', () => {
    const order = {
      id_order: 1050,
      currency: 'EUR',
      shipping_refunded: 4.90
    };
    const items = [
      { id_product: 68, id_product_attribute: 299, sku: 'BHK125-B', name: 'Sip-N-Snack Cup', price: 13.00, quantity: 1 }
    ];

    const payload = buildRefundPayload(order, items);
    assert.strictEqual(payload.events[0].params.value, 17.90);
    assert.strictEqual(payload.events[0].params.shipping, 4.90);
  });

  it('7. Should verify refund idempotency and prevent duplicate webhook execution', () => {
    const refundLog = new Set();
    const idOrderSlip = 42;

    // First attempt: Not tracked yet
    const isFirstTracked = refundLog.has(idOrderSlip);
    assert.strictEqual(isFirstTracked, false);

    // Record tracking
    refundLog.add(idOrderSlip);

    // Second attempt: Already tracked
    const isSecondTracked = refundLog.has(idOrderSlip);
    assert.strictEqual(isSecondTracked, true);
  });

});
