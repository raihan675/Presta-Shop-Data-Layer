/**
 * Core DataLayer Engine: canonical normalization, schema validation, dynamic prefixing, and safe dispatch.
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
  var eventPrefix = '';

  function log(level, message, payload) {
    if (!DEBUG && level === 'info') return;
    var prefix = '[GTM DataLayer] [' + level.toUpperCase() + ']: ';
    if (typeof window !== 'undefined' && window.console && window.console[level]) {
      window.console[level](prefix + message, payload || '');
    }
  }

  function normalizePrefix(prefix) {
    if (!prefix || typeof prefix !== 'string') return '';
    var cleaned = prefix.trim();
    // Replace spaces, hyphens, and dots with underscores
    cleaned = cleaned.replace(/[\s\.\-]+/g, '_');
    // Remove characters other than alphanumeric and underscores
    cleaned = cleaned.replace(/[^a-zA-Z0-9_]/g, '');
    // Remove leading and trailing underscores
    cleaned = cleaned.replace(/^_+|_+$/g, '');
    return cleaned;
  }

  function formatEventName(canonicalName, prefixOverride) {
    var p = prefixOverride !== undefined ? normalizePrefix(prefixOverride) : eventPrefix;
    if (!p) {
      return String(canonicalName);
    }
    // Avoid double prefixing
    var prefixToken = p + '_';
    if (String(canonicalName).indexOf(prefixToken) === 0) {
      return String(canonicalName);
    }
    return p + '_' + String(canonicalName);
  }

  function roundHalfUp(val, decimals) {
    var d = decimals !== undefined ? decimals : 2;
    var factor = Math.pow(10, d);
    var num = Number(val);
    if (isNaN(num)) return 0.0;
    return Math.round((num + Number.EPSILON) * factor) / factor;
  }

  function sanitizeFloat(val) {
    return roundHalfUp(val, 2);
  }

  function sanitizeInt(val) {
    var parsed = parseInt(val, 10);
    return isNaN(parsed) ? 1 : Math.max(1, parsed);
  }

  function normalizeItem(raw) {
    if (!raw || typeof raw !== 'object') return null;

    var baseId = String(raw.item_id || raw.base_product_id || raw.id || '');
    var rawVariant = (raw.variant_id !== undefined && raw.variant_id !== null)
      ? raw.variant_id
      : (raw.id_product_attribute !== undefined ? raw.id_product_attribute : '');
    var variantId = (rawVariant && String(rawVariant) !== '0' && parseInt(rawVariant, 10) > 0)
      ? String(rawVariant)
      : '';
    var skuId = String(raw.sku_id || raw.reference || raw.item_sku || '');
    var variant = (raw.item_variant || raw.variant) ? String(raw.item_variant || raw.variant) : '';

    var item = {
      item_id: baseId,
      variant_id: variantId,
      sku_id: skuId,
      item_name: String(raw.item_name || raw.name || ''),
      item_variant: variant,
      price: sanitizeFloat(raw.price),
      quantity: sanitizeInt(raw.quantity)
    };

    if (raw.item_brand || raw.manufacturer) item.item_brand = String(raw.item_brand || raw.manufacturer);
    if (raw.item_category || raw.category) item.item_category = String(raw.item_category || raw.category);
    if (raw.item_category2) item.item_category2 = String(raw.item_category2);
    if (raw.item_category3) item.item_category3 = String(raw.item_category3);
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
    return roundHalfUp(total, 2);
  }

  function pushEcommerceEvent(canonicalEventName, ecommerceData, userData) {
    if (!canonicalEventName || typeof canonicalEventName !== 'string') {
      log('error', 'Push aborted: Invalid event name');
      return false;
    }

    if (typeof window !== 'undefined') {
      window.dataLayer = window.dataLayer || [];
    }

    var finalEventName = formatEventName(canonicalEventName);

    var canonicalPayload = {
      event: finalEventName,
      original_event: canonicalEventName,
      ecommerce: ecommerceData || {}
    };

    if (userData && typeof userData === 'object') {
      canonicalPayload.user_data = userData;
    }

    // Explicitly reset the ecommerce object to purge prior stale properties in GTM
    if (typeof window !== 'undefined' && window.dataLayer) {
      window.dataLayer.push({ ecommerce: null });
      window.dataLayer.push(canonicalPayload);
    }

    log('info', 'Dispatched ' + finalEventName, canonicalPayload);
    return true;
  }

  // Auto-detect prefix from global config if set by server
  if (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.eventPrefix) {
    eventPrefix = normalizePrefix(window.psGtmConfig.eventPrefix);
  }

  return {
    setDebug: function (mode) {
      DEBUG = !!mode;
    },
    setEventPrefix: function (prefix) {
      eventPrefix = normalizePrefix(prefix);
    },
    getEventPrefix: function () {
      return eventPrefix;
    },
    normalizePrefix: normalizePrefix,
    formatEventName: formatEventName,
    pushEvent: pushEcommerceEvent,
    normalizeItem: normalizeItem,
    normalizeItems: normalizeItems,
    calculateCartValue: calculateCartValue,
    roundHalfUp: roundHalfUp
  };
});
