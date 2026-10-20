/**
 * Core DataLayer Engine: canonical normalization, schema validation, and safe dispatch.
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.EcommerceDataLayer = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var DEBUG = false;

  function log(level, message, payload) {
    if (!DEBUG && level === 'info') return;
    var prefix = '[GTM DataLayer] [' + level.toUpperCase() + ']: ';
    if (window.console && window.console[level]) {
      window.console[level](prefix + message, payload || '');
    }
  }

  function sanitizeFloat(val) {
    var parsed = parseFloat(val);
    return isNaN(parsed) ? 0.0 : Math.round(parsed * 100) / 100;
  }

  function sanitizeInt(val) {
    var parsed = parseInt(val, 10);
    return isNaN(parsed) ? 1 : Math.max(1, parsed);
  }

  function normalizeItem(raw) {
    if (!raw || typeof raw !== 'object') return null;

    var item = {
      item_id: String(raw.item_id || raw.id || raw.reference || ''),
      item_name: String(raw.item_name || raw.name || ''),
      price: sanitizeFloat(raw.price),
      quantity: sanitizeInt(raw.quantity)
    };

    if (raw.item_brand || raw.manufacturer) item.item_brand = String(raw.item_brand || raw.manufacturer);
    if (raw.item_category || raw.category) item.item_category = String(raw.item_category || raw.category);
    if (raw.item_category2) item.item_category2 = String(raw.item_category2);
    if (raw.item_category3) item.item_category3 = String(raw.item_category3);
    if (raw.item_variant || raw.variant) item.item_variant = String(raw.item_variant || raw.variant);
    if (raw.index !== undefined) item.index = parseInt(raw.index, 10);
    if (raw.coupon) item.coupon = String(raw.coupon);
    if (raw.discount !== undefined) item.discount = sanitizeFloat(raw.discount);

    return item;
  }

  function normalizeItems(itemsArray) {
    if (!Array.isArray(itemsArray)) return [];
    var output = [];
    for (var i = 0; i < itemsArray.length; i++) {
      var norm = normalizeItem(itemsArray[i]);
      if (norm && norm.item_id && norm.item_name) {
        output.push(norm);
      } else {
        log('warn', 'Item rejected due to missing mandatory item_id or item_name', itemsArray[i]);
      }
    }
    return output;
  }

  function calculateCartValue(items) {
    var total = 0.0;
    for (var i = 0; i < items.length; i++) {
      total += items[i].price * items[i].quantity;
    }
    return Math.round(total * 100) / 100;
  }

  function pushEcommerceEvent(eventName, ecommerceData, userData) {
    if (!eventName || typeof eventName !== 'string') {
      log('error', 'Push aborted: Invalid event name');
      return false;
    }

    window.dataLayer = window.dataLayer || [];

    var canonicalPayload = {
      event: eventName,
      ecommerce: ecommerceData || {}
    };

    if (userData && typeof userData === 'object') {
      canonicalPayload.user_data = userData;
    }

    // Explicitly reset the ecommerce object to purge prior stale properties in GTM
    window.dataLayer.push({ ecommerce: null });
    window.dataLayer.push(canonicalPayload);

    log('info', 'Dispatched ' + eventName, canonicalPayload);
    return true;
  }

  return {
    setDebug: function (mode) {
      DEBUG = !!mode;
    },
    pushEvent: pushEcommerceEvent,
    normalizeItem: normalizeItem,
    normalizeItems: normalizeItems,
    calculateCartValue: calculateCartValue
  };
});
