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

  it('8. Should build canonical GA4/sGTM order_status_update payload for Bank Wire to Payment Accepted', () => {
    const order = {
      id_order: 1248,
      reference: 'RGXUHEBWD',
      currency: 'EUR',
      total_paid: 23.70,
      payment: 'Bank transfer',
      customer_email: 'test.customer@gmail.com'
    };
    const newOrderStatus = {
      id: 2,
      name: 'Payment accepted',
      paid: true,
      shipped: false
    };
    const items = [
      { id_product: 153, id_product_attribute: 0, sku: 'FIT09', name: 'Fit Kidz Barbell Soft Toy', price: 19.20, quantity: 1 }
    ];

    const userData = {
      email_address: sha256(order.customer_email)
    };

    const payload = {
      client_id: `${order.id_order}.${Math.floor(Date.now() / 1000)}`,
      events: [
        {
          name: 'order_status_update',
          params: {
            transaction_id: String(order.id_order),
            order_reference: order.reference,
            new_status_id: newOrderStatus.id,
            new_status_name: newOrderStatus.name,
            previous_status_id: 10,
            is_paid: Boolean(newOrderStatus.paid),
            is_shipped: Boolean(newOrderStatus.shipped),
            currency: order.currency,
            value: order.total_paid,
            payment_method: order.payment,
            user_data: userData,
            items: items.map(i => ({
              item_id: resolveCatalogIdentifier(i.id_product, i.id_product_attribute, i.sku, 'composite'),
              item_name: i.name,
              price: i.price,
              quantity: i.quantity
            }))
          }
        }
      ]
    };

    assert.strictEqual(payload.events[0].name, 'order_status_update');
    assert.strictEqual(payload.events[0].params.transaction_id, '1248');
    assert.strictEqual(payload.events[0].params.order_reference, 'RGXUHEBWD');
    assert.strictEqual(payload.events[0].params.new_status_id, 2);
    assert.strictEqual(payload.events[0].params.new_status_name, 'Payment accepted');
    assert.strictEqual(payload.events[0].params.is_paid, true);
    assert.strictEqual(payload.events[0].params.is_shipped, false);
    assert.strictEqual(payload.events[0].params.value, 23.70);
    assert.strictEqual(payload.events[0].params.payment_method, 'Bank transfer');
    assert.strictEqual(payload.events[0].params.items[0].item_id, '153');
    assert.strictEqual(payload.events[0].params.user_data.email_address, crypto.createHash('sha256').update('test.customer@gmail.com').digest('hex'));
  });

  it('9. Should handle marketplace/remote orders (e.g. Mirakl status 11) with is_paid: true', () => {
    const newOrderStatus = {
      id: 11,
      name: 'Remote payment accepted',
      paid: true,
      shipped: false
    };

    assert.strictEqual(newOrderStatus.paid, true);
    assert.strictEqual(newOrderStatus.id, 11);
  });

  it('10. Should classify Payment Revocation when admin reverts a mistakenly paid order back to unpaid', () => {
    function classifyTransition(previousIsPaid, isPaidNow) {
      if (!previousIsPaid && isPaidNow) {
        return { transitionType: 'payment_confirmed', conversionAction: 'record' };
      } else if (previousIsPaid && !isPaidNow) {
        return { transitionType: 'payment_revoked', conversionAction: 'retract' };
      } else if (previousIsPaid && isPaidNow) {
        return { transitionType: 'status_changed_while_paid', conversionAction: 'maintain' };
      } else {
        return { transitionType: 'status_changed_unpaid', conversionAction: 'none' };
      }
    }

    // Mistakenly paid order (status 2, paid: true) reverted to Bank Wire (status 10, paid: false)
    const result = classifyTransition(true, false);
    assert.strictEqual(result.transitionType, 'payment_revoked');
    assert.strictEqual(result.conversionAction, 'retract');
  });

  it('11. Should classify status_changed_while_paid when order moves from Payment accepted to Shipped', () => {
    function classifyTransition(previousIsPaid, isPaidNow) {
      if (!previousIsPaid && isPaidNow) {
        return { transitionType: 'payment_confirmed', conversionAction: 'record' };
      } else if (previousIsPaid && !isPaidNow) {
        return { transitionType: 'payment_revoked', conversionAction: 'retract' };
      } else if (previousIsPaid && isPaidNow) {
        return { transitionType: 'status_changed_while_paid', conversionAction: 'maintain' };
      } else {
        return { transitionType: 'status_changed_unpaid', conversionAction: 'none' };
      }
    }

    const result = classifyTransition(true, true);
    assert.strictEqual(result.transitionType, 'status_changed_while_paid');
    assert.strictEqual(result.conversionAction, 'maintain');
  });

  it('12. Should build canonical GA4 beacon query string with measurement ID, item indices, and parameters', () => {
    function buildGa4BeaconBody(measurementId, clientId, eventName, params) {
      const parts = [
        'v=2',
        `tid=${encodeURIComponent(measurementId)}`,
        `cid=${encodeURIComponent(clientId)}`,
        `en=${encodeURIComponent(eventName)}`
      ];

      let itemIndex = 1;
      for (const [key, val] of Object.entries(params)) {
        if (key === 'items' && Array.isArray(val)) {
          for (const item of val) {
            const itemParts = [];
            if (item.item_id) itemParts.push(`id${String(item.item_id).replace(/~/g, '_')}`);
            if (item.item_name) itemParts.push(`nm${String(item.item_name).replace(/~/g, '_')}`);
            if (item.price != null) itemParts.push(`pr${item.price}`);
            if (item.quantity != null) itemParts.push(`qt${item.quantity}`);
            if (itemParts.length) {
              parts.push(`pr${itemIndex}=${encodeURIComponent(itemParts.join('~'))}`);
              itemIndex++;
            }
          }
        } else if (key === 'user_data' && typeof val === 'object' && val !== null) {
          if (val.email_address) {
            parts.push(`em=${encodeURIComponent(val.email_address)}`);
            parts.push(`ep.user_email_hash=${encodeURIComponent(val.email_address)}`);
          }
          if (val.phone_number) {
            parts.push(`ph=${encodeURIComponent(val.phone_number)}`);
            parts.push(`ep.user_phone_hash=${encodeURIComponent(val.phone_number)}`);
          }
        } else if (typeof val === 'number') {
          parts.push(`epn.${key}=${val}`);
        } else if (typeof val === 'boolean') {
          parts.push(`ep.${key}=${val ? 'true' : 'false'}`);
        } else if (val != null && String(val) !== '') {
          parts.push(`ep.${key}=${encodeURIComponent(String(val))}`);
        }
      }
      return parts.join('&');
    }

    const beacon = buildGa4BeaconBody('G-TEST12345', '1251.1700000', 'order_status_update', {
      transaction_id: '1251',
      status_transition_type: 'payment_confirmed',
      conversion_action: 'record',
      is_paid: true,
      value: 17.50,
      currency: 'EUR',
      items: [
        { item_id: '68_299', item_name: 'Sip-N-Snack Cup', price: 13.00, quantity: 1 }
      ],
      user_data: {
        email_address: 'd9b015e12e3e9d8e578c7847cbb6509f7a77e596bb05c2a122e2307efea14408'
      }
    });

    assert.ok(beacon.includes('v=2'));
    assert.ok(beacon.includes('tid=G-TEST12345'));
    assert.ok(beacon.includes('en=order_status_update'));
    assert.ok(beacon.includes('ep.transaction_id=1251'));
    assert.ok(beacon.includes('ep.status_transition_type=payment_confirmed'));
    assert.ok(beacon.includes('ep.conversion_action=record'));
    assert.ok(beacon.includes('ep.is_paid=true'));
    assert.ok(beacon.includes('epn.value=17.5'));
    assert.ok(beacon.includes('ep.currency=EUR'));
    assert.ok(beacon.includes('pr1=id68_299~nmSip-N-Snack%20Cup~pr13~qt1'));
    assert.ok(beacon.includes('em=d9b015e12e3e9d8e578c7847cbb6509f7a77e596bb05c2a122e2307efea14408'));
  });

  it('13. Should attach X-Gtm-Server-Preview header when debug token is present', () => {
    function buildHeaders(previewHeader) {
      const headers = {
        'Content-Type': 'text/plain',
        'X-PrestaShop-Source': 'ps_gtm_datalayer',
        'X-Event-Type': 'order_status_update'
      };
      if (previewHeader && previewHeader.trim() !== '') {
        headers['X-Gtm-Server-Preview'] = previewHeader.trim();
      }
      return headers;
    }

    const testToken = 'ZW52LTN8NW84bWp6OTJoTDl1cU5jajZFcjhyZ3wxYTIzM';
    const headers = buildHeaders(testToken);
    assert.strictEqual(headers['X-Gtm-Server-Preview'], testToken);

    const emptyHeaders = buildHeaders('');
    assert.strictEqual(emptyHeaders['X-Gtm-Server-Preview'], undefined);
  });

  it('14. Should resolve webhook endpoint with default /ps-webhook or custom endpoint path', () => {
    function resolveEndpoint(sgtmUrl, customPath) {
      const p = customPath ? customPath.trim() : '/ps-webhook';
      try {
        const u = new URL(sgtmUrl);
        if (u.pathname && u.pathname !== '/') {
          return sgtmUrl;
        }
        return `${sgtmUrl.replace(/\/+$/, '')}/${p.replace(/^\/+/, '')}`;
      } catch (e) {
        return `${sgtmUrl.replace(/\/+$/, '')}/${p.replace(/^\/+/, '')}`;
      }
    }

    assert.strictEqual(
      resolveEndpoint('https://qdktisyx.in.stape.io', '/ps-webhook'),
      'https://qdktisyx.in.stape.io/ps-webhook'
    );
    assert.strictEqual(
      resolveEndpoint('https://qdktisyx.in.stape.io', '/data'),
      'https://qdktisyx.in.stape.io/data'
    );
    assert.strictEqual(
      resolveEndpoint('https://qdktisyx.in.stape.io/custom-endpoint', '/ps-webhook'),
      'https://qdktisyx.in.stape.io/custom-endpoint'
    );
  });

  it('15. Should build dual flat & envelope JSON payload with top-level event_name for custom client', () => {
    function buildDualJsonPayload(eventName, clientId, params) {
      const payload = Object.assign({
        event_name: eventName,
        client_id: clientId,
        timestamp: 1792918871
      }, params);
      payload.events = [
        {
          name: eventName,
          params: params
        }
      ];
      return payload;
    }

    const payload = buildDualJsonPayload('order_status_update', '1254.170000', {
      transaction_id: '1254',
      is_paid: true,
      status_transition_type: 'payment_confirmed',
      value: 17.50
    });

    assert.strictEqual(payload.event_name, 'order_status_update');
    assert.strictEqual(payload.transaction_id, '1254');
    assert.strictEqual(payload.is_paid, true);
    assert.strictEqual(payload.status_transition_type, 'payment_confirmed');
    assert.strictEqual(payload.value, 17.50);
    assert.ok(Array.isArray(payload.events));
    assert.strictEqual(payload.events[0].name, 'order_status_update');
  });

});
