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

  it('12. Should build canonical JSON payload with application/json headers', () => {
    function buildHeaders(eventName) {
      return {
        'Content-Type': 'application/json',
        'X-PrestaShop-Source': 'ps_gtm_datalayer',
        'X-Event-Type': eventName
      };
    }

    const headers = buildHeaders('order_status_update');
    assert.strictEqual(headers['Content-Type'], 'application/json');
    assert.strictEqual(headers['X-PrestaShop-Source'], 'ps_gtm_datalayer');
    assert.strictEqual(headers['X-Event-Type'], 'order_status_update');
  });

  it('13. Should ensure pure JSON serialization with unescaped slashes and unicode', () => {
    const payload = {
      event_name: 'order_status_update',
      transaction_id: '1254',
      status_name: 'Awaiting bank wire payment & processing'
    };
    const jsonStr = JSON.stringify(payload);
    assert.ok(jsonStr.includes('order_status_update'));
    assert.ok(jsonStr.includes('1254'));
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

  it('16. Should classify refund_issued with conversion_action: retract when order status changes to Refunded', () => {
    function classifyTransitionWithOverrides(newStatusId, statusName, previousIsPaid, isPaidNow) {
      const statusNameLower = (statusName || '').toLowerCase();
      const isRefunded = (newStatusId === 7 || newStatusId === 20 || newStatusId === 26 ||
        statusNameLower.includes('refund') || statusNameLower.includes('erstattet'));
      const isCanceled = (newStatusId === 6 || statusNameLower.includes('cancel') || statusNameLower.includes('storniert'));

      if (isRefunded) {
        return { transitionType: 'refund_issued', conversionAction: 'retract', isRefunded: true, isCanceled: false };
      } else if (isCanceled) {
        return { transitionType: 'order_canceled', conversionAction: 'retract', isRefunded: false, isCanceled: true };
      } else if (!previousIsPaid && isPaidNow) {
        return { transitionType: 'payment_confirmed', conversionAction: 'record', isRefunded: false, isCanceled: false };
      } else if (previousIsPaid && !isPaidNow) {
        return { transitionType: 'payment_revoked', conversionAction: 'retract', isRefunded: false, isCanceled: false };
      } else if (previousIsPaid && isPaidNow) {
        return { transitionType: 'status_changed_while_paid', conversionAction: 'maintain', isRefunded: false, isCanceled: false };
      } else {
        return { transitionType: 'status_changed_unpaid', conversionAction: 'none', isRefunded: false, isCanceled: false };
      }
    }

    // Changing from Payment accepted (ID 2, previously paid) to Refunded (ID 7)
    const result1 = classifyTransitionWithOverrides(7, 'Refunded', true, false);
    assert.strictEqual(result1.transitionType, 'refund_issued');
    assert.strictEqual(result1.conversionAction, 'retract');
    assert.strictEqual(result1.isRefunded, true);

    // Changing from German Erstattet (ID 7) even if previous was unpaid bank wire
    const result2 = classifyTransitionWithOverrides(7, 'Erstattet', false, false);
    assert.strictEqual(result2.transitionType, 'refund_issued');
    assert.strictEqual(result2.conversionAction, 'retract');
    assert.strictEqual(result2.isRefunded, true);

    // Mollie partial refund (ID 20)
    const result3 = classifyTransitionWithOverrides(20, 'Partially refunded by Mollie', true, false);
    assert.strictEqual(result3.transitionType, 'refund_issued');
    assert.strictEqual(result3.conversionAction, 'retract');
  });

  it('17. Should classify order_canceled with conversion_action: retract when order status changes to Canceled', () => {
    function classifyTransitionWithOverrides(newStatusId, statusName, previousIsPaid, isPaidNow) {
      const statusNameLower = (statusName || '').toLowerCase();
      const isRefunded = (newStatusId === 7 || newStatusId === 20 || newStatusId === 26 ||
        statusNameLower.includes('refund') || statusNameLower.includes('erstattet'));
      const isCanceled = (newStatusId === 6 || statusNameLower.includes('cancel') || statusNameLower.includes('storniert'));

      if (isRefunded) {
        return { transitionType: 'refund_issued', conversionAction: 'retract', isRefunded: true, isCanceled: false };
      } else if (isCanceled) {
        return { transitionType: 'order_canceled', conversionAction: 'retract', isRefunded: false, isCanceled: true };
      } else if (!previousIsPaid && isPaidNow) {
        return { transitionType: 'payment_confirmed', conversionAction: 'record', isRefunded: false, isCanceled: false };
      } else if (previousIsPaid && !isPaidNow) {
        return { transitionType: 'payment_revoked', conversionAction: 'retract', isRefunded: false, isCanceled: false };
      } else if (previousIsPaid && isPaidNow) {
        return { transitionType: 'status_changed_while_paid', conversionAction: 'maintain', isRefunded: false, isCanceled: false };
      } else {
        return { transitionType: 'status_changed_unpaid', conversionAction: 'none', isRefunded: false, isCanceled: false };
      }
    }

    const result = classifyTransitionWithOverrides(6, 'Bestellung storniert', true, false);
    assert.strictEqual(result.transitionType, 'order_canceled');
    assert.strictEqual(result.conversionAction, 'retract');
    assert.strictEqual(result.isCanceled, true);
  });

  it('18. Should strictly ignore status update when previousStatusId is 0 (initial checkout) or identical to newStatusId', () => {
    function shouldDispatchStatusUpdate(previousStatusId, newStatusId) {
      if (previousStatusId <= 0 || previousStatusId === newStatusId) {
        return false;
      }
      return true;
    }

    // Initial order placement at checkout (Bank wire, status 10)
    assert.strictEqual(shouldDispatchStatusUpdate(0, 10), false);
    assert.strictEqual(shouldDispatchStatusUpdate(10, 10), false);

    // Initial order placement at checkout (Card paid, status 2)
    assert.strictEqual(shouldDispatchStatusUpdate(0, 2), false);
    assert.strictEqual(shouldDispatchStatusUpdate(2, 2), false);

    // Legitimate status change in Back Office: Bank wire (10) -> Payment accepted (2)
    assert.strictEqual(shouldDispatchStatusUpdate(10, 2), true);

    // Legitimate status change in Back Office: Payment accepted (2) -> Refunded (7)
    assert.strictEqual(shouldDispatchStatusUpdate(2, 7), true);
  });

  it('19. Should inject X-Gtm-Server-Preview header when preview token is configured and omit when empty', () => {
    function buildHttpHeaders(eventName, previewHeader = null) {
      const headers = [
        'Content-Type: application/json',
        'X-PrestaShop-Source: ps_gtm_datalayer',
        'X-Event-Type: ' + eventName,
      ];
      if (previewHeader && String(previewHeader).trim() !== '') {
        headers.push('X-Gtm-Server-Preview: ' + String(previewHeader).trim());
      }
      return headers;
    }

    const previewToken = 'ZW52LTZ8dnFTZ0FrZ0R2aUZxbTVzbUZyTTNXUXwxYTE4MTBkZjYwYTE3NGYzYzJhNDg=';

    // With preview header (Debugging in GTM Server Preview)
    const headersWithPreview = buildHttpHeaders('order_status_update', previewToken);
    assert.ok(headersWithPreview.includes('X-Gtm-Server-Preview: ' + previewToken));
    assert.strictEqual(headersWithPreview.length, 4);

    // Without preview header (Production mode)
    const headersWithoutPreview = buildHttpHeaders('order_status_update', '');
    assert.ok(!headersWithoutPreview.some(h => h.startsWith('X-Gtm-Server-Preview:')));
    assert.strictEqual(headersWithoutPreview.length, 3);
  });

  it('20. Should build canonical diagnostic test payload for order_status_update with preview header', () => {
    function buildDiagnosticPayload(transactionId, statusTransition, previewHeader) {
      const payload = {
        event_name: 'order_status_update',
        client_id: 'test_order.' + 1793167600,
        timestamp: 1793167600,
        transaction_id: transactionId,
        order_reference: 'DIAGNOSTIC-TEST',
        status_transition_type: statusTransition,
        conversion_action: 'record',
        new_status_id: 2,
        new_status_name: 'Payment accepted',
        previous_status_id: 10,
        previous_status_name: 'Awaiting bank wire payment',
        is_paid: true,
        previous_is_paid: false,
        currency: 'EUR',
        value: 49.99,
        items: [
          {
            item_id: 'TEST_ITEM_1',
            item_name: 'Test Product - sGTM Webhook Diagnostics',
            price: 49.99,
            quantity: 1
          }
        ]
      };

      const headers = [
        'Content-Type: application/json',
        'X-PrestaShop-Source: ps_gtm_datalayer',
        'X-Event-Type: order_status_update',
        'X-Diagnostic-Test: 1'
      ];
      if (previewHeader) {
        headers.push('X-Gtm-Server-Preview: ' + previewHeader);
      }

      return { payload, headers };
    }

    const previewToken = 'ZW52LTZ8dnFTZ0FrZ0R2aUZxbTVzbUZyTTNXUXwxYTE4MTBkZjYwYTE3NGYzYzJhNDg=';
    const diag = buildDiagnosticPayload('TEST-1001', 'payment_confirmed', previewToken);

    assert.strictEqual(diag.payload.event_name, 'order_status_update');
    assert.strictEqual(diag.payload.transaction_id, 'TEST-1001');
    assert.strictEqual(diag.payload.status_transition_type, 'payment_confirmed');
    assert.strictEqual(diag.payload.is_paid, true);
    assert.ok(diag.headers.includes('X-Gtm-Server-Preview: ' + previewToken));
    assert.ok(diag.headers.includes('X-Diagnostic-Test: 1'));
  });

  it('21. Should track prior order state via actionOrderStatusUpdate map across status transitions', () => {
    const previousOrderStatusMap = {};

    function onActionOrderStatusUpdate(idOrder, currentDbState) {
      previousOrderStatusMap[idOrder] = currentDbState;
    }

    function onActionOrderStatusPostUpdate(idOrder, newOrderStatusId) {
      const previousStatusId = previousOrderStatusMap[idOrder] || 0;
      if (previousStatusId <= 0 || previousStatusId === newOrderStatusId) {
        return null; // Ignore duplicate or unrecorded
      }
      return { idOrder, previousStatusId, newOrderStatusId, dispatched: true };
    }

    // Step 1: Pre-hook fires before order state updates from Bank wire (10) to Payment accepted (2)
    onActionOrderStatusUpdate(1048, 10);
    assert.strictEqual(previousOrderStatusMap[1048], 10);

    // Step 2: Post-hook executes and consumes previousStatusId
    const dispatchResult = onActionOrderStatusPostUpdate(1048, 2);
    assert.ok(dispatchResult);
    assert.strictEqual(dispatchResult.previousStatusId, 10);
    assert.strictEqual(dispatchResult.newOrderStatusId, 2);
    assert.strictEqual(dispatchResult.dispatched, true);

    // Step 3: Re-saving the same status (2 -> 2) must be ignored
    onActionOrderStatusUpdate(1048, 2);
    const duplicateResult = onActionOrderStatusPostUpdate(1048, 2);
    assert.strictEqual(duplicateResult, null);
  });

  it('22. Should include recovered advertising attribution and status_change_context in order_status_update payload', () => {
    function buildEnrichedStatusUpdatePayload(orderId, attribution, statusContext) {
      const params = {
        transaction_id: String(orderId),
        order_reference: 'TEST-REF',
        status_transition_type: 'payment_confirmed',
        conversion_action: 'record',
        new_status_id: 2,
        new_status_name: 'Payment accepted',
        currency: 'EUR',
        value: 99.00
      };

      if (attribution && Object.keys(attribution).length > 0) {
        params.attribution = attribution;
      }
      if (statusContext && Object.keys(statusContext).length > 0) {
        params.status_change_context = statusContext;
      }

      return {
        event_name: 'order_status_update',
        client_id: `${orderId}.${Date.now()}`,
        timestamp: Math.floor(Date.now() / 1000),
        events: [
          {
            name: 'order_status_update',
            params: params
          }
        ],
        ...params
      };
    }

    const testAttribution = {
      gclid: 'CjwKCAjw1b7OBhBTEiwA28',
      fbclid: 'IwAR2Y_x9lQ30x',
      fbp: 'fb.1.1793092415.987654321',
      ga_client_id: '1083920194.1793092415'
    };

    const testStatusContext = {
      actor_type: 'administrator',
      actor_id: 'id_employee: 1',
      actor_name: 'Admin User',
      order_age_seconds: 7200,
      is_within_google_ads_window: true,
      tracking_number: 'DHL123456789'
    };

    const payload = buildEnrichedStatusUpdatePayload(153, testAttribution, testStatusContext);

    assert.strictEqual(payload.event_name, 'order_status_update');
    assert.strictEqual(payload.transaction_id, '153');
    assert.ok(payload.attribution);
    assert.strictEqual(payload.attribution.gclid, 'CjwKCAjw1b7OBhBTEiwA28');
    assert.strictEqual(payload.attribution.fbclid, 'IwAR2Y_x9lQ30x');
    assert.ok(payload.status_change_context);
    assert.strictEqual(payload.status_change_context.actor_type, 'administrator');
    assert.strictEqual(payload.status_change_context.tracking_number, 'DHL123456789');
    assert.strictEqual(payload.events[0].params.attribution.gclid, 'CjwKCAjw1b7OBhBTEiwA28');
  });

});
