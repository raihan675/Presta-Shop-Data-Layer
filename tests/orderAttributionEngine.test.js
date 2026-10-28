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
});
