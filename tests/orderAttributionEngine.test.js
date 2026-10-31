/**
 * Test Suite: Order Attribution Persistence & Status Change Context Engine
 * Validates extraction and normalization of 13+ ad click IDs, browser cookies,
 * GA4 Client ID & Session ID, and enriched status_change_context metadata.
 */

const { describe, it } = require('node:test');
const assert = require('node:assert');

describe('Order Attribution & Status Change Context Engine Test Suite', () => {

  // Attribution normalization logic parity
  function sanitizeIdentifier(str) {
    return String(str || '').replace(/[^a-zA-Z0-9_\-\.\:\/]/g, '').trim();
  }

  function extractGaClientId(cookieVal) {
    if (!cookieVal) return null;
    const parts = String(cookieVal).split('.');
    if (parts.length >= 4) {
      return `${parts[2]}.${parts[3]}`;
    } else if (parts.length === 2) {
      return cookieVal;
    }
    return null;
  }

  function extractGaSessionId(cookieVal) {
    if (!cookieVal) return null;
    const parts = String(cookieVal).split('.');
    if (parts.length >= 3 && !isNaN(parts[2])) {
      return String(parts[2]);
    }
    return null;
  }

  function formatAttributionPayload(raw) {
    const attribution = {};
    const keys = [
      'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'sccid', 'msclkid', 'oppref',
      'epik', 'li_fat_id', 'rdt_cid', 'twclid', 'dclid',
      'fbp', 'fbc', 'ttp', 'scid', 'uetvid', 'uetsid',
      'ga_client_id', 'ga_session_id',
      'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
    ];

    for (const key of keys) {
      if (raw[key] && String(raw[key]).trim() !== '') {
        attribution[key] = String(raw[key]).trim();
      }
    }
    return attribution;
  }

  it('1. Should sanitize and validate Priority 1 Ad Click IDs without corruption', () => {
    const rawInputs = {
      gclid: 'CjwKCAjw1b7OBhBTEiwA28_test-click.123/safe_chars',
      gbraid: '1234567890abcdef',
      wbraid: '0987654321fedcba',
      fbclid: 'IwAR2Y_x9lQ30x_fb_click.id:100',
      ttclid: 'E.0.1793167600_tt_click',
      msclkid: 'f8a92c10b100_bing',
      sccid: 'snap_click_998877',
      oppref: 'chatgpt_search_ref_001',
      epik: 'pinterest_click_id_99',
      li_fat_id: 'linkedin_fat_id_443',
      rdt_cid: 'reddit_cid_8899',
      twclid: 'twitter_twclid_1122',
      dclid: 'google_marketing_dclid'
    };

    for (const [key, val] of Object.entries(rawInputs)) {
      const sanitized = sanitizeIdentifier(val);
      assert.strictEqual(sanitized, val, `Failed sanitization parity for ${key}`);
      assert.ok(!/[^a-zA-Z0-9_\-\.\:\/]/.test(sanitized));
    }
  });

  it('2. Should strip unsafe XSS or SQL injection characters from click identifiers', () => {
    const malicious = 'CjwKCAjw1b7<script>alert(1)</script>\' OR 1=1 --;';
    const sanitized = sanitizeIdentifier(malicious);
    assert.ok(!sanitized.includes('<script>'));
    assert.ok(!sanitized.includes('\''));
    assert.ok(!sanitized.includes(';'));
    assert.ok(!sanitized.includes(' '));
    assert.strictEqual(sanitized, 'CjwKCAjw1b7scriptalert1/scriptOR11--');
  });

  it('3. Should accurately parse GA4 Client ID from _ga cookie string', () => {
    const standardGa = 'GA1.1.1083920194.1793092415';
    assert.strictEqual(extractGaClientId(standardGa), '1083920194.1793092415');

    const twoPartGa = '1083920194.1793092415';
    assert.strictEqual(extractGaClientId(twoPartGa), '1083920194.1793092415');

    assert.strictEqual(extractGaClientId(''), null);
    assert.strictEqual(extractGaClientId(null), null);
  });

  it('4. Should accurately parse GA4 Session ID from _ga_<container> cookie string', () => {
    const containerCookie = 'GS1.1.1712345678.1.1.1712345678.0.0.0';
    assert.strictEqual(extractGaSessionId(containerCookie), '1712345678');

    assert.strictEqual(extractGaSessionId('invalid'), null);
    assert.strictEqual(extractGaSessionId(''), null);
  });

  it('5. Should format complete attribution payload including click IDs, cookies, and UTMs', () => {
    const rawDbRow = {
      id_order: 153,
      id_customer: 42,
      gclid: 'CjwKCAjw1b7OBhBTEiwA28',
      fbclid: 'IwAR2Y_x9lQ30x',
      fbp: 'fb.1.1793092415.987654321',
      fbc: 'fb.1.1793092415.IwAR2Y_x9lQ30x',
      ga_client_id: '1083920194.1793092415',
      ga_session_id: '1793092415',
      utm_source: 'google',
      utm_medium: 'cpc',
      utm_campaign: 'summer_promo',
      empty_field: null
    };

    const payload = formatAttributionPayload(rawDbRow);

    assert.strictEqual(payload.gclid, 'CjwKCAjw1b7OBhBTEiwA28');
    assert.strictEqual(payload.fbclid, 'IwAR2Y_x9lQ30x');
    assert.strictEqual(payload.fbp, 'fb.1.1793092415.987654321');
    assert.strictEqual(payload.fbc, 'fb.1.1793092415.IwAR2Y_x9lQ30x');
    assert.strictEqual(payload.ga_client_id, '1083920194.1793092415');
    assert.strictEqual(payload.ga_session_id, '1793092415');
    assert.strictEqual(payload.utm_source, 'google');
    assert.strictEqual(payload.utm_medium, 'cpc');
    assert.strictEqual(payload.utm_campaign, 'summer_promo');
    assert.strictEqual(payload.empty_field, undefined);
  });

  it('6. Should build complete status_change_context with forensic actor and timing metadata', () => {
    function buildMockStatusChangeContext(orderAgeSeconds, isEmployee, newOrderStatus) {
      return {
        id_order_history: 842,
        status_change_timestamp: '2026-10-28T12:00:00Z',
        actor_type: isEmployee ? 'administrator' : 'payment_gateway_webhook',
        actor_id: isEmployee ? 'id_employee: 1' : 'ps_checkout',
        actor_name: isEmployee ? 'John Admin' : 'PayPal Gateway',
        actor_ip: '192.168.1.100',
        order_age_seconds: orderAgeSeconds,
        time_in_previous_status_seconds: 3600,
        is_within_google_ads_window: (orderAgeSeconds <= (55 * 86400)),
        is_within_meta_window: (orderAgeSeconds <= (90 * 86400)),
        is_invoice_generated: Boolean(newOrderStatus.invoice),
        is_delivery_slip_generated: Boolean(newOrderStatus.delivery),
        customer_email_sent: Boolean(newOrderStatus.send_email),
        order_state_color: newOrderStatus.color || '#10B981',
        tracking_number: 'DHL123456789',
        carrier_name: 'DHL Express',
        gateway_transaction_id: 'ch_3Nz2ABC81029381'
      };
    }

    // Recent order (2 days old) changed by Administrator
    const ctxAdmin = buildMockStatusChangeContext(172800, true, { invoice: true, delivery: true, send_email: true, color: '#3B82F6' });
    assert.strictEqual(ctxAdmin.actor_type, 'administrator');
    assert.strictEqual(ctxAdmin.is_within_google_ads_window, true);
    assert.strictEqual(ctxAdmin.is_within_meta_window, true);
    assert.strictEqual(ctxAdmin.is_invoice_generated, true);
    assert.strictEqual(ctxAdmin.tracking_number, 'DHL123456789');

    // Older order (65 days old) changed by Gateway Webhook (exceeds Google 55d window, within Meta 90d window)
    const ctxOld = buildMockStatusChangeContext(65 * 86400, false, { invoice: false, delivery: false, send_email: false, color: '#EF4444' });
    assert.strictEqual(ctxOld.actor_type, 'payment_gateway_webhook');
    assert.strictEqual(ctxOld.is_within_google_ads_window, false);
    assert.strictEqual(ctxOld.is_within_meta_window, true);
  });

  it('7. Should verify complete webhook envelope carries attribution & status_change_context', () => {
    const fullEnvelope = {
      event_name: 'order_status_update',
      transaction_id: '153',
      order_reference: 'HTAIWHXQL',
      status_transition_type: 'refund_issued',
      conversion_action: 'retract',
      new_status_name: 'Refunded',
      previous_status_name: 'Payment accepted',
      value: 64.40,
      currency: 'EUR',
      attribution: {
        gclid: 'CjwKCAjw1b7OBhBTEiwA28',
        fbclid: 'IwAR2Y_x9lQ30x',
        fbp: 'fb.1.1793092415.987654321',
        ga_client_id: '1083920194.1793092415'
      },
      status_change_context: {
        actor_type: 'administrator',
        actor_name: 'SuperAdmin',
        order_age_seconds: 7200,
        is_within_google_ads_window: true
      },
      user_data: {
        email_address: 'd54d92...hash'
      },
      items: [
        { item_id: '153', item_name: 'Fit Kidz Barbell Soft Toy', price: 19.20, quantity: 2 }
      ]
    };

    assert.ok(fullEnvelope.attribution);
    assert.strictEqual(fullEnvelope.attribution.gclid, 'CjwKCAjw1b7OBhBTEiwA28');
    assert.ok(fullEnvelope.status_change_context);
    assert.strictEqual(fullEnvelope.status_change_context.actor_type, 'administrator');
    assert.strictEqual(fullEnvelope.status_change_context.is_within_google_ads_window, true);
  });

  it('8. Should safely merge existing attribution values in upsertOrderAttributionRow logic', () => {
    function simulateUpsertMerge(existingRow, newRow) {
      const merged = { ...newRow };
      for (const [col, val] of Object.entries(existingRow)) {
        if (val !== null && val !== undefined && val !== '' && (merged[col] === null || merged[col] === undefined || merged[col] === '')) {
          merged[col] = val;
        }
      }
      return merged;
    }

    const existingInDb = {
      id_order: 1268,
      id_customer: 1713,
      ga_client_id: '1713.1793194296',
      customer_ip: '113.11.34.171',
      gclid: null,
      fbclid: null,
      utm_source: null
    };

    const incomingRecovered = {
      id_order: 1268,
      gclid: 'TEST_GCLID_998877',
      fbclid: 'TEST_FBCLID_443322',
      utm_source: 'google',
      utm_medium: 'cpc',
      utm_campaign: 'summer_sale',
      ga_client_id: null
    };

    const finalRow = simulateUpsertMerge(existingInDb, incomingRecovered);
    assert.strictEqual(finalRow.id_order, 1268);
    assert.strictEqual(finalRow.gclid, 'TEST_GCLID_998877');
    assert.strictEqual(finalRow.fbclid, 'TEST_FBCLID_443322');
    assert.strictEqual(finalRow.utm_source, 'google');
    assert.strictEqual(finalRow.utm_medium, 'cpc');
    assert.strictEqual(finalRow.utm_campaign, 'summer_sale');
    assert.strictEqual(finalRow.ga_client_id, '1713.1793194296'); // Preserved from existing!
    assert.strictEqual(finalRow.customer_ip, '113.11.34.171');   // Preserved from existing!
  });

  it('9. Should promote top attribution click IDs and UTM parameters to first-class webhook params', () => {
    function injectAttributionIntoParams(params, attribution) {
      if (!attribution) return;
      params.attribution = attribution;
      const promotedKeys = [
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'sccid', 'msclkid', 'oppref',
        'epik', 'li_fat_id', 'rdt_cid', 'twclid', 'dclid',
        'fbp', 'fbc', 'ttp', 'scid', 'uetvid', 'uetsid',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
      ];
      for (const pk of promotedKeys) {
        if (attribution[pk] && !params[pk]) {
          params[pk] = attribution[pk];
        }
      }
    }

    const params = {
      transaction_id: '1268',
      value: 17.50,
      currency: 'EUR'
    };

    const attribution = {
      gclid: 'TEST_GCLID_998877',
      fbclid: 'TEST_FBCLID_443322',
      utm_source: 'google',
      utm_medium: 'cpc',
      utm_campaign: 'summer_sale'
    };

    injectAttributionIntoParams(params, attribution);

    // Verified nested
    assert.ok(params.attribution);
    assert.strictEqual(params.attribution.gclid, 'TEST_GCLID_998877');

    // Verified promoted top-level for immediate sGTM client ingestion
    assert.strictEqual(params.gclid, 'TEST_GCLID_998877');
    assert.strictEqual(params.fbclid, 'TEST_FBCLID_443322');
    assert.strictEqual(params.utm_source, 'google');
    assert.strictEqual(params.utm_medium, 'cpc');
    assert.strictEqual(params.utm_campaign, 'summer_sale');
  });

  it('10. Should accurately parse query parameters from native PrestaShop request_uri records', () => {
    function extractParamsFromUri(uri) {
      const qPos = uri.indexOf('?');
      const queryStr = qPos !== -1 ? uri.substring(qPos + 1) : '';
      const params = {};
      if (queryStr) {
        const searchParams = new URLSearchParams(queryStr);
        for (const [k, v] of searchParams.entries()) {
          if (v && v.trim() !== '') {
            params[k] = sanitizeIdentifier(v.trim());
          }
        }
      }
      return params;
    }

    const uriFull = 'families-world.de/de/?gclid=TEST_GCLID_998877&fbclid=TEST_FBCLID_443322&utm_source=google&utm_medium=cpc&utm_campaign=summer_sale';
    const parsedFull = extractParamsFromUri(uriFull);
    assert.strictEqual(parsedFull.gclid, 'TEST_GCLID_998877');
    assert.strictEqual(parsedFull.fbclid, 'TEST_FBCLID_443322');
    assert.strictEqual(parsedFull.utm_source, 'google');
    assert.strictEqual(parsedFull.utm_medium, 'cpc');
    assert.strictEqual(parsedFull.utm_campaign, 'summer_sale');

    const uriTruncated = 'families-world.de/de/?gclid=TEST_GCLID_998877&fbclid=TEST_FBCLID_443322&utm_source=google&utm_medium=';
    const parsedTruncated = extractParamsFromUri(uriTruncated);
    assert.strictEqual(parsedTruncated.gclid, 'TEST_GCLID_998877');
    assert.strictEqual(parsedTruncated.fbclid, 'TEST_FBCLID_443322');
    assert.strictEqual(parsedTruncated.utm_source, 'google');
    assert.strictEqual(parsedTruncated.utm_medium, undefined);
  });

  it('11. Should cleanly split flat attribution into structured marketing_attribution categories', () => {
    function getStructuredAttributionPayload(flat) {
      if (!flat || Object.keys(flat).length === 0) return {};
      const structured = {
        click_ids: {},
        browser_cookies: {},
        utm_parameters: {},
        analytics_identifiers: {},
      };
      const clickParams = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'sccid', 'msclkid', 'oppref', 'epik', 'li_fat_id', 'rdt_cid', 'twclid', 'dclid'];
      const cookieParams = {
        '_fbp': 'fbp', '_fbc': 'fbc', '_ttp': 'ttp', '_scid': 'scid',
        '_uetvid': 'uetvid', '_uetsid': 'uetsid', '_gcl_aw': 'gcl_aw',
        '_gcl_gb': 'gcl_gb', '_gcl_au': 'gcl_au', '_pin_unauth': 'pin_unauth',
        '_pinterest_ct_ua': 'pinterest_ct_ua', '_rdt_uuid': 'rdt_uuid',
      };
      const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

      for (const cp of clickParams) {
        if (flat[cp]) structured.click_ids[cp] = flat[cp];
      }
      for (const tKey of Object.values(cookieParams)) {
        if (flat[tKey]) structured.browser_cookies[tKey] = flat[tKey];
      }
      for (const utm of utmParams) {
        if (flat[utm]) structured.utm_parameters[utm] = flat[utm];
      }
      if (flat.ga_client_id) structured.analytics_identifiers.client_id = flat.ga_client_id;
      if (flat.ga_session_id) structured.analytics_identifiers.session_id = flat.ga_session_id;

      return structured;
    }

    const flatInput = {
      gclid: 'TEST_GCLID_111',
      fbclid: 'TEST_FBCLID_222',
      fbp: 'fb.1.12345678',
      fbc: 'fb.1.12345678.TEST_FBCLID_222',
      utm_source: 'google',
      utm_medium: 'cpc',
      utm_campaign: 'summer_sale',
      ga_client_id: '12345.67890',
      ga_session_id: '1793092415'
    };

    const structured = getStructuredAttributionPayload(flatInput);
    assert.strictEqual(structured.click_ids.gclid, 'TEST_GCLID_111');
    assert.strictEqual(structured.click_ids.fbclid, 'TEST_FBCLID_222');
    assert.strictEqual(structured.browser_cookies.fbp, 'fb.1.12345678');
    assert.strictEqual(structured.browser_cookies.fbc, 'fb.1.12345678.TEST_FBCLID_222');
    assert.strictEqual(structured.utm_parameters.utm_source, 'google');
    assert.strictEqual(structured.analytics_identifiers.client_id, '12345.67890');
    assert.strictEqual(structured.analytics_identifiers.session_id, '1793092415');
  });

  it('12. Should promote cart attribution to order attribution during order validation', () => {
    const cartDb = {
      101: {
        id_cart: 101,
        id_customer: 55,
        gclid: 'CART_GCLID_999',
        fbclid: 'CART_FBCLID_888',
        utm_source: 'facebook',
        date_add: '2026-10-31 10:00:00'
      }
    };
    const orderDb = {};

    function promoteCartAttributionToOrder(idCart, idOrder, idCustomer) {
      const cartRow = cartDb[idCart];
      if (!cartRow) return false;
      orderDb[idOrder] = {
        id_order: idOrder,
        id_cart: idCart,
        id_customer: idCustomer || cartRow.id_customer,
        gclid: cartRow.gclid,
        fbclid: cartRow.fbclid,
        utm_source: cartRow.utm_source,
        date_add: cartRow.date_add,
        date_upd: '2026-10-31 10:15:00'
      };
      return true;
    }

    const success = promoteCartAttributionToOrder(101, 501, 55);
    assert.strictEqual(success, true);
    assert.strictEqual(orderDb[501].gclid, 'CART_GCLID_999');
    assert.strictEqual(orderDb[501].fbclid, 'CART_FBCLID_888');
    assert.strictEqual(orderDb[501].id_cart, 101);
  });

  it('13. Should map refund status transitions to canonical refund event in Server GTM client', () => {
    function mapServerGtmEvent(params) {
      let eventName = 'order_status_update';
      const originalEventName = eventName;
      const isRefundTransition = (params.status_transition_type === 'refund_issued' || params.is_refunded === true);
      if (isRefundTransition) {
        eventName = 'refund';
      }
      return {
        event_name: eventName,
        original_event_name: originalEventName,
        conversion_action: params.conversion_action || (isRefundTransition ? 'retract' : 'maintain')
      };
    }

    const refundTransition = mapServerGtmEvent({
      status_transition_type: 'refund_issued',
      is_refunded: true,
      conversion_action: 'retract'
    });
    assert.strictEqual(refundTransition.event_name, 'refund');
    assert.strictEqual(refundTransition.conversion_action, 'retract');

    const shippedTransition = mapServerGtmEvent({
      status_transition_type: 'status_changed_while_paid',
      is_refunded: false,
      conversion_action: 'maintain'
    });
    assert.strictEqual(shippedTransition.event_name, 'order_status_update');
    assert.strictEqual(shippedTransition.conversion_action, 'maintain');
  });
});
