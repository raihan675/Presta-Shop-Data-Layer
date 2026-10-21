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

  function sanitizeFloat(val, decimals) {
    var num = Number(val);
    if (isNaN(num)) return 0.0;
    if (decimals !== undefined && decimals !== null) {
      return roundHalfUp(num, decimals);
    }
    if (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) {
      return roundHalfUp(num, window.psGtmConfig.currencyPrecision);
    }
    var str = String(val);
    var dotIdx = str.indexOf('.');
    if (dotIdx !== -1) {
      var existingDecimals = str.length - dotIdx - 1;
      return roundHalfUp(num, Math.min(6, existingDecimals));
    }
    return num;
  }

  function formatVariant(raw) {
    if (!raw) return '';
    if (typeof raw === 'string') {
      return (raw === '[object Object]') ? '' : raw.trim();
    }
    if (Array.isArray(raw)) {
      var parts = [];
      for (var i = 0; i < raw.length; i++) {
        var a = raw[i];
        if (typeof a === 'string' && a !== '[object Object]') {
          var trimmed = a.trim();
          if (trimmed) parts.push(trimmed);
        } else if (a && typeof a === 'object') {
          if (a.group && a.name) {
            parts.push(String(a.group).trim() + ': ' + String(a.name).trim());
          } else if (a.name) {
            parts.push(String(a.name).trim());
          } else if (a.value) {
            parts.push(String(a.value).trim());
          }
        }
      }
      return parts.join(' | ');
    }
    if (typeof raw === 'object') {
      var objParts = [];
      for (var key in raw) {
        if (raw.hasOwnProperty(key)) {
          var val = raw[key];
          if (typeof val === 'string' || typeof val === 'number') {
            var valStr = String(val).trim();
            if (valStr && valStr !== '[object Object]') {
              if (/^\d+$/.test(key)) {
                objParts.push(valStr);
              } else {
                objParts.push(key.trim() + ': ' + valStr);
              }
            }
          } else if (val && typeof val === 'object' && val.name) {
            var prefix = val.group ? String(val.group).trim() + ': ' : '';
            objParts.push(prefix + String(val.name).trim());
          }
        }
      }
      return objParts.join(' | ');
    }
    return '';
  }

  function sanitizeInt(val) {
    var parsed = parseInt(val, 10);
    return isNaN(parsed) ? 1 : Math.max(1, parsed);
  }

  function normalizeItem(raw, precision) {
    if (!raw || typeof raw !== 'object') return null;

    var prec = (precision !== undefined && precision !== null) ? precision : raw.precision;
    var baseId = String(raw.item_id || raw.base_product_id || raw.id || '');
    var rawVariant = (raw.variant_id !== undefined && raw.variant_id !== null)
      ? raw.variant_id
      : (raw.id_product_attribute !== undefined ? raw.id_product_attribute : '');
    var variantId = (rawVariant && String(rawVariant) !== '0' && parseInt(rawVariant, 10) > 0)
      ? String(rawVariant)
      : '';
    var skuId = String(raw.sku_id || raw.reference || raw.item_sku || '');
    var variant = formatVariant(raw.item_variant || raw.variant || raw.attributes);

    var item = {
      item_id: baseId,
      variant_id: variantId,
      sku_id: skuId,
      item_name: String(raw.item_name || raw.name || ''),
      item_brand: (raw.item_brand || raw.manufacturer) ? String(raw.item_brand || raw.manufacturer) : '',
      item_category: (raw.item_category || raw.category) ? String(raw.item_category || raw.category) : '',
      item_variant: variant,
      price: sanitizeFloat(raw.price, prec),
      quantity: sanitizeInt(raw.quantity)
    };

    if (raw.item_category2) item.item_category2 = String(raw.item_category2);
    if (raw.item_category3) item.item_category3 = String(raw.item_category3);
    if (raw.index !== undefined) item.index = parseInt(raw.index, 10);
    if (raw.coupon) item.coupon = String(raw.coupon);
    if (raw.discount !== undefined) item.discount = sanitizeFloat(raw.discount, prec);

    return item;
  }

  function normalizeItems(itemsArray, precision) {
    if (!Array.isArray(itemsArray)) return [];
    var output = [];
    for (var i = 0; i < itemsArray.length; i++) {
      var norm = normalizeItem(itemsArray[i], precision);
      if (norm && norm.item_id && norm.item_name) {
        output.push(norm);
      } else {
        log('warn', 'Item rejected due to missing mandatory item_id or item_name', itemsArray[i]);
      }
    }
    return output;
  }

  function calculateCartValue(items, precision) {
    var dec = (precision !== undefined && precision !== null)
      ? parseInt(precision, 10)
      : (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined
        ? parseInt(window.psGtmConfig.currencyPrecision, 10)
        : 2);
    var total = 0.0;
    for (var i = 0; i < items.length; i++) {
      var p = typeof items[i].price === 'number' ? items[i].price : parseFloat(items[i].price) || 0;
      var q = typeof items[i].quantity === 'number' ? items[i].quantity : parseInt(items[i].quantity, 10) || 1;
      total += p * q;
    }
    return roundHalfUp(total, dec);
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
    roundHalfUp: roundHalfUp,
    formatVariant: formatVariant
  };
});
