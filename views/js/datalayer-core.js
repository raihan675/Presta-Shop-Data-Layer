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
    if (raw.item_list_id) item.item_list_id = String(raw.item_list_id);
    if (raw.item_list_name) item.item_list_name = String(raw.item_list_name);
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

  /**
   * Resolves the exact catalog identifier according to feed matching strategy.
   * Default: composite convention {id_product}_{id_product_attribute} (e.g. 68_299)
   */
  function resolveCatalogItemId(item, strategy) {
    if (!item || typeof item !== 'object') return '';
    var strat = strategy || (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.catalogIdStrategy) || 'composite';
    if (strat === 'reference_sku' && item.sku_id) {
      return String(item.sku_id).trim();
    }
    if (strat === 'id_product') {
      return String(item.item_id).trim();
    }

    var baseId = String(item.item_id || '').trim();
    var varId = String(item.variant_id || '').trim();

    if (baseId.indexOf('_') !== -1) {
      return baseId;
    }

    if (varId && varId !== '0' && parseInt(varId, 10) > 0) {
      return baseId + '_' + varId;
    }

    return baseId;
  }

  /**
   * Universal ISO 4217 minor unit converter for OpenAI Ads.
   * Converts floating point / integer currency values to exact integer minor units.
   */
  function toMinorUnits(amount, currencyCode, customPrecision) {
    var val = typeof amount === 'number' ? amount : parseFloat(amount) || 0.0;
    var code = (currencyCode ? String(currencyCode) : 'EUR').trim().toUpperCase();

    // Zero-decimal currencies (multiplier = 1)
    var zeroDecimal = [
      'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
      'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'
    ];
    if (zeroDecimal.indexOf(code) !== -1) {
      return Math.round(val);
    }

    // Three-decimal currencies (multiplier = 1000)
    var threeDecimal = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];
    if (threeDecimal.indexOf(code) !== -1) {
      return Math.round(val * 1000);
    }

    // Four-decimal currencies (multiplier = 10000)
    var fourDecimal = ['CLF', 'UYW'];
    if (fourDecimal.indexOf(code) !== -1) {
      return Math.round(val * 10000);
    }

    if (customPrecision !== undefined && customPrecision !== null && !isNaN(customPrecision) && parseInt(customPrecision, 10) !== 2) {
      return Math.round(val * Math.pow(10, parseInt(customPrecision, 10)));
    }

    // Standard two-decimal currencies (USD, EUR, GBP, AUD, CAD, BDT, INR, etc.)
    return Math.round(val * 100);
  }

  /**
   * Universal Advertising Platform Namespace Builder:
   * Generates exact, catalog-matching payload parameters for Meta, TikTok, and Google Ads Dynamic Remarketing.
   */
  function buildPlatformNamespaces(canonicalEventName, items, currency, totalValue, options) {
    options = options || {};
    items = Array.isArray(items) ? items : [];
    var curr = currency || (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
    var val = (totalValue !== undefined && totalValue !== null)
      ? (typeof totalValue === 'number' ? totalValue : parseFloat(totalValue) || 0.0)
      : calculateCartValue(items);
    var strategy = options.catalogIdStrategy || (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.catalogIdStrategy) || 'composite';
    var advSettings = (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.advertisingSettings) ? window.psGtmConfig.advertisingSettings : null;

    var isList = (canonicalEventName === 'view_item_list');
    var isSingleProduct = (canonicalEventName === 'view_item' || canonicalEventName === 'select_item' || canonicalEventName === 'add_to_wishlist') && items.length === 1;

    var pageType = 'other';
    if (canonicalEventName === 'view_search_results' || canonicalEventName === 'search') {
      pageType = 'searchresults';
    } else if (canonicalEventName === 'view_item_list') {
      pageType = (options.listId && String(options.listId).indexOf('search') !== -1) ? 'searchresults' : 'category';
    } else if (canonicalEventName === 'view_item' || canonicalEventName === 'select_item' || canonicalEventName === 'add_to_wishlist') {
      pageType = 'product';
    } else if (
      canonicalEventName === 'view_cart' ||
      canonicalEventName === 'add_to_cart' ||
      canonicalEventName === 'remove_from_cart' ||
      canonicalEventName === 'begin_checkout' ||
      canonicalEventName === 'add_shipping_info' ||
      canonicalEventName === 'add_payment_info'
    ) {
      pageType = 'cart';
    } else if (canonicalEventName === 'purchase') {
      pageType = 'purchase';
    }

    var metaContentIds = [];
    var metaContents = [];
    var tiktokContents = [];
    var googleProdIds = [];
    var googleDynamicItems = [];
    var googleCartDataItems = [];
    var pinterestProdIds = [];
    var pinterestLineItems = [];
    var microsoftProdIds = [];
    var microsoftItems = [];
    var totalNumItems = 0;

    for (var i = 0; i < items.length; i++) {
      var it = items[i];
      if (!it) continue;
      var catId = resolveCatalogItemId(it, strategy);
      var itemQty = typeof it.quantity === 'number' ? it.quantity : parseInt(it.quantity, 10) || 1;
      if (itemQty <= 0) itemQty = 1;
      var itemPrice = typeof it.price === 'number' ? it.price : parseFloat(it.price) || 0.0;
      totalNumItems += itemQty;

      // Meta: on category listing, pass parent base ID with product_group; on individual actions, pass catalog ID
      var metaId = isList ? String(it.item_id) : catId;
      if (metaContentIds.indexOf(metaId) === -1) {
        metaContentIds.push(metaId);
      }
      // Meta official contents schema: id, quantity, item_price
      metaContents.push({
        id: metaId,
        quantity: itemQty,
        item_price: itemPrice
      });

      // TikTok official contents schema: content_id, content_type, content_name, content_category, quantity, price
      var ttId = isList ? String(it.item_id) : catId;
      tiktokContents.push({
        content_id: ttId,
        content_type: 'product',
        content_name: String(it.item_name || ''),
        content_category: String(it.item_category || ''),
        quantity: itemQty,
        price: itemPrice
      });

      if (googleProdIds.indexOf(catId) === -1) {
        googleProdIds.push(catId);
      }
      // Google Ads Retail Dynamic Remarketing items schema
      googleDynamicItems.push({
        id: catId,
        google_business_vertical: 'retail'
      });
      // Google Ads Conversions with Cart Data items schema
      googleCartDataItems.push({
        id: catId,
        quantity: itemQty,
        price: itemPrice
      });

      if (pinterestProdIds.indexOf(catId) === -1) {
        pinterestProdIds.push(catId);
      }
      // Pinterest Web Tag official line_items schema
      pinterestLineItems.push({
        product_id: catId,
        product_name: String(it.item_name || ''),
        product_price: itemPrice,
        product_quantity: itemQty,
        product_category: String(it.item_category || ''),
        product_brand: String(it.item_brand || ''),
        product_variant: String(it.item_variant || ''),
        product_variant_id: (it.variant_id !== undefined && it.variant_id !== null && String(it.variant_id) !== '0') ? String(it.variant_id) : ''
      });

      if (microsoftProdIds.indexOf(catId) === -1) {
        microsoftProdIds.push(catId);
      }
      // Microsoft Advertising official items schema
      var msftItem = {
        id: catId,
        name: String(it.item_name || ''),
        brand: String(it.item_brand || ''),
        category: String(it.item_category || ''),
        variant: String(it.item_variant || ''),
        price: itemPrice,
        quantity: itemQty
      };
      if (it.list_name) msftItem.list_name = String(it.list_name);
      if (it.list_position !== undefined) msftItem.list_position = Number(it.list_position);
      microsoftItems.push(msftItem);
    }

    // Meta Event Name Resolution
    var metaEventName = 'Custom';
    var metaCustomName = null;

    if (canonicalEventName === 'view_item' || canonicalEventName === 'select_item') {
      metaEventName = 'ViewContent';
    } else if (canonicalEventName === 'view_cart') {
      metaEventName = 'Custom';
      metaCustomName = 'ViewCart';
    } else if (canonicalEventName === 'view_item_list') {
      metaEventName = 'ViewCategory';
    } else if (canonicalEventName === 'add_to_cart') {
      metaEventName = 'AddToCart';
    } else if (canonicalEventName === 'purchase') {
      metaEventName = 'Purchase';
    } else if (canonicalEventName === 'search' || canonicalEventName === 'view_search_results') {
      metaEventName = 'Search';
    } else if (canonicalEventName === 'add_to_wishlist') {
      metaEventName = 'AddToWishlist';
    } else if (canonicalEventName === 'begin_checkout') {
      metaEventName = 'InitiateCheckout';
    } else if (canonicalEventName === 'add_shipping_info') {
      metaEventName = 'AddShippingInfo';
    } else if (canonicalEventName === 'add_payment_info') {
      metaEventName = 'AddPaymentInfo';
    } else if (canonicalEventName === 'remove_from_cart') {
      metaEventName = 'Custom';
      metaCustomName = 'RemoveFromCart';
    } else {
      metaEventName = 'Custom';
      metaCustomName = canonicalEventName;
    }

    var metaNamespace = {
      event_name: metaEventName,
      content_type: isList ? 'product_group' : 'product',
      content_ids: metaContentIds,
      contents: metaContents,
      currency: curr,
      value: val,
      num_items: (totalNumItems > 0) ? totalNumItems : 1
    };
    if (metaCustomName) {
      metaNamespace.custom_event_name = metaCustomName;
    }
    if (options.orderId) {
      metaNamespace.order_id = String(options.orderId);
    }
    if (options.searchTerm) {
      metaNamespace.search_string = String(options.searchTerm);
    }
    if (options.categoryName) {
      metaNamespace.content_category = String(options.categoryName);
    }
    if (isSingleProduct && items[0] && items[0].item_name) {
      metaNamespace.content_name = String(items[0].item_name);
      if (!metaNamespace.content_category && items[0].item_category) {
        metaNamespace.content_category = String(items[0].item_category);
      }
    }

    var mUserData = options.metaUserData || options.meta_user_data ||
      (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.metaUserData ? window.psGtmConfig.metaUserData : null);
    if (mUserData && typeof mUserData === 'object' && Object.keys(mUserData).length > 0) {
      metaNamespace.user_data = mUserData;
    }

    // TikTok Event Name Resolution
    var tiktokEventName = 'Custom';
    var tiktokCustomName = null;

    if (canonicalEventName === 'view_item' || canonicalEventName === 'select_item' || canonicalEventName === 'view_cart' || canonicalEventName === 'view_item_list') {
      tiktokEventName = 'ViewContent';
    } else if (canonicalEventName === 'add_to_cart') {
      tiktokEventName = 'AddToCart';
    } else if (canonicalEventName === 'purchase') {
      tiktokEventName = 'CompletePayment';
    } else if (canonicalEventName === 'search' || canonicalEventName === 'view_search_results') {
      tiktokEventName = 'Search';
    } else if (canonicalEventName === 'add_to_wishlist') {
      tiktokEventName = 'AddToWishlist';
    } else if (canonicalEventName === 'begin_checkout') {
      tiktokEventName = 'InitiateCheckout';
    } else if (canonicalEventName === 'add_shipping_info') {
      tiktokEventName = 'AddShippingInfo';
    } else if (canonicalEventName === 'add_payment_info') {
      tiktokEventName = 'AddPaymentInfo';
    } else if (canonicalEventName === 'remove_from_cart') {
      tiktokEventName = 'Custom';
      tiktokCustomName = 'RemoveFromCart';
    } else {
      tiktokEventName = 'Custom';
      tiktokCustomName = canonicalEventName;
    }

    var tiktokNamespace = {
      event_name: tiktokEventName,
      content_type: isList ? 'product_group' : 'product',
      content_id: (metaContentIds.length > 0) ? metaContentIds[0] : '',
      content_ids: metaContentIds,
      contents: tiktokContents,
      currency: curr,
      value: val,
      quantity: (totalNumItems > 0) ? totalNumItems : 1
    };
    if (tiktokCustomName) {
      tiktokNamespace.custom_event_name = tiktokCustomName;
    }
    if (options.orderId) {
      tiktokNamespace.order_id = String(options.orderId);
    }
    if (options.searchTerm) {
      tiktokNamespace.search_string = String(options.searchTerm);
      tiktokNamespace.query = String(options.searchTerm);
    }
    if (options.categoryName) {
      tiktokNamespace.content_category = String(options.categoryName);
    }
    if (isSingleProduct && items[0] && items[0].item_name) {
      tiktokNamespace.content_name = String(items[0].item_name);
      if (!tiktokNamespace.content_category && items[0].item_category) {
        tiktokNamespace.content_category = String(items[0].item_category);
      }
    }

    var tUserData = options.tiktokUserData || options.tiktok_user_data ||
      (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.tiktokUserData ? window.psGtmConfig.tiktokUserData : null);
    if (tUserData && typeof tUserData === 'object' && Object.keys(tUserData).length > 0) {
      tiktokNamespace.user_data = tUserData;
    }

    var gadsEventName = canonicalEventName;
    if (canonicalEventName === 'view_item' || canonicalEventName === 'select_item') {
      gadsEventName = 'view_item';
    } else if (canonicalEventName === 'view_item_list') {
      gadsEventName = 'view_item_list';
    } else if (canonicalEventName === 'search' || canonicalEventName === 'view_search_results') {
      gadsEventName = 'view_search_results';
    } else if (canonicalEventName === 'view_cart') {
      gadsEventName = 'view_cart';
    } else if (canonicalEventName === 'add_to_cart') {
      gadsEventName = 'add_to_cart';
    } else if (canonicalEventName === 'remove_from_cart') {
      gadsEventName = 'remove_from_cart';
    } else if (canonicalEventName === 'begin_checkout') {
      gadsEventName = 'begin_checkout';
    } else if (canonicalEventName === 'add_shipping_info') {
      gadsEventName = 'add_shipping_info';
    } else if (canonicalEventName === 'add_payment_info') {
      gadsEventName = 'add_payment_info';
    } else if (canonicalEventName === 'purchase') {
      gadsEventName = 'purchase';
    } else if (canonicalEventName === 'add_to_wishlist') {
      gadsEventName = 'add_to_wishlist';
    }

    var dynamicRemarketing = {
      event_name: gadsEventName,
      value: val,
      currency: curr,
      items: googleDynamicItems,
      ecomm_pagetype: pageType,
      ecomm_prodid: isSingleProduct ? (googleProdIds[0] || '') : googleProdIds,
      ecomm_totalvalue: val
    };
    if (options.categoryName) {
      dynamicRemarketing.ecomm_category = String(options.categoryName);
    }
    if (options.searchTerm) {
      dynamicRemarketing.search_term = String(options.searchTerm);
    }

    var googleAdsNamespace = {
      event_name: gadsEventName,
      dynamic_remarketing: dynamicRemarketing
    };

    var cartDataEnabled = advSettings ? (advSettings.gads_cart_data !== false && advSettings.gads_cart_data !== 0 && advSettings.gads_cart_data !== '0') : true;
    if (cartDataEnabled && googleCartDataItems.length > 0) {
      var txId = options.transactionId ? String(options.transactionId) : (options.orderId ? String(options.orderId) : '');
      var cartData = {
        event_name: gadsEventName,
        value: val,
        currency: curr,
        items: googleCartDataItems
      };
      if (txId) {
        cartData.transaction_id = txId;
      }
      if (options.discount) {
        cartData.discount = parseFloat(options.discount);
      }
      var gMerchantId = options.awMerchantId || (advSettings && advSettings.gads_merchant_id ? advSettings.gads_merchant_id : '');
      if (gMerchantId) {
        cartData.aw_merchant_id = String(gMerchantId);
      }
      if (options.awFeedCountry) {
        cartData.aw_feed_country = String(options.awFeedCountry);
      }
      if (options.awFeedLanguage) {
        cartData.aw_feed_language = String(options.awFeedLanguage);
      }
      if (options.newCustomer !== undefined || options.new_customer !== undefined) {
        cartData.new_customer = Boolean(options.newCustomer !== undefined ? options.newCustomer : options.new_customer);
      }
      if (options.customerLifetimeValue !== undefined || options.customer_lifetime_value !== undefined) {
        cartData.customer_lifetime_value = parseFloat(options.customerLifetimeValue !== undefined ? options.customerLifetimeValue : options.customer_lifetime_value);
      }
      googleAdsNamespace.cart_data = cartData;
    }

    // Pinterest Event Mapping and Schema Resolution
    var pinterestEventName = 'Custom';
    var pinterestCustomName = null;

    if (canonicalEventName === 'view_item' || canonicalEventName === 'select_item') {
      pinterestEventName = 'PageVisit';
    } else if (canonicalEventName === 'view_cart') {
      pinterestEventName = 'Custom';
      pinterestCustomName = 'ViewCart';
    } else if (canonicalEventName === 'view_item_list') {
      pinterestEventName = 'ViewCategory';
    } else if (canonicalEventName === 'add_to_cart') {
      pinterestEventName = 'AddToCart';
    } else if (canonicalEventName === 'purchase') {
      pinterestEventName = 'Checkout';
    } else if (canonicalEventName === 'search' || canonicalEventName === 'view_search_results') {
      pinterestEventName = 'Search';
    } else if (canonicalEventName === 'begin_checkout') {
      pinterestEventName = 'InitiateCheckout';
    } else if (canonicalEventName === 'add_payment_info') {
      pinterestEventName = 'AddPaymentInfo';
    } else if (canonicalEventName === 'add_to_wishlist') {
      pinterestEventName = 'AddToWishList';
    } else if (canonicalEventName === 'remove_from_cart') {
      pinterestEventName = 'Custom';
      pinterestCustomName = 'RemoveFromCart';
    } else if (canonicalEventName === 'add_shipping_info') {
      pinterestEventName = 'Custom';
      pinterestCustomName = 'AddShippingInfo';
    } else {
      pinterestEventName = 'Custom';
      pinterestCustomName = canonicalEventName;
    }

    var pinterestNamespace = {
      event_name: pinterestEventName,
      currency: curr,
      value: val,
      order_quantity: (totalNumItems > 0) ? totalNumItems : 1,
      product_ids: pinterestProdIds,
      line_items: pinterestLineItems
    };

    if (pinterestCustomName) {
      pinterestNamespace.custom_event_name = pinterestCustomName;
    }
    if (options.orderId) {
      pinterestNamespace.order_id = String(options.orderId);
    }
    if (options.searchTerm) {
      pinterestNamespace.search_query = String(options.searchTerm);
    }
    if (options.categoryName) {
      pinterestNamespace.category_name = String(options.categoryName);
      pinterestNamespace.product_category = String(options.categoryName);
    }
    if (options.property) {
      pinterestNamespace.property = String(options.property);
    }

    var pUserData = options.pinterestUserData || options.pinterest_user_data ||
      (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.pinterestUserData ? window.psGtmConfig.pinterestUserData : null);
    if (pUserData && typeof pUserData === 'object' && Object.keys(pUserData).length > 0) {
      pinterestNamespace.user_data = pUserData;
    }

    // Microsoft Advertising Event Action and Schema Resolution
    var msftEventAction = canonicalEventName;
    if (canonicalEventName === 'view_item' || canonicalEventName === 'select_item') {
      msftEventAction = 'view_item';
    } else if (canonicalEventName === 'view_cart') {
      msftEventAction = 'view_cart';
    } else if (canonicalEventName === 'view_item_list') {
      msftEventAction = 'view_item_list';
    } else if (canonicalEventName === 'search' || canonicalEventName === 'view_search_results') {
      msftEventAction = 'view_search_results';
    } else if (canonicalEventName === 'add_to_cart') {
      msftEventAction = 'add_to_cart';
    } else if (canonicalEventName === 'remove_from_cart') {
      msftEventAction = 'remove_from_cart';
    } else if (canonicalEventName === 'begin_checkout') {
      msftEventAction = 'begin_checkout';
    } else if (canonicalEventName === 'add_shipping_info') {
      msftEventAction = 'checkout_progress';
    } else if (canonicalEventName === 'add_payment_info') {
      msftEventAction = 'add_payment_info';
    } else if (canonicalEventName === 'purchase') {
      msftEventAction = 'purchase';
    } else if (canonicalEventName === 'add_to_wishlist') {
      msftEventAction = 'add_to_wishlist';
    }

    var microsoftAdsNamespace = {
      event_action: msftEventAction,
      event_category: 'ecommerce',
      revenue_value: val,
      currency: curr,
      items: microsoftItems
    };

    if (options.orderId) {
      microsoftAdsNamespace.event_label = 'Order ' + String(options.orderId);
    }
    if (options.searchTerm) {
      microsoftAdsNamespace.search_term = String(options.searchTerm);
    }
    if (canonicalEventName === 'begin_checkout') {
      microsoftAdsNamespace.checkout_step = 1;
    } else if (canonicalEventName === 'add_shipping_info') {
      microsoftAdsNamespace.checkout_step = 3;
      if (options.property) microsoftAdsNamespace.checkout_option = String(options.property);
    } else if (canonicalEventName === 'add_payment_info') {
      microsoftAdsNamespace.checkout_step = 4;
      if (options.property) microsoftAdsNamespace.checkout_option = String(options.property);
    }
    if (options.coupon) {
      microsoftAdsNamespace.coupon = String(options.coupon);
    }

    if (canonicalEventName === 'purchase') {
      var msftTxId = options.transactionId ? String(options.transactionId) : (options.orderId ? String(options.orderId) : '');
      if (msftTxId) {
        microsoftAdsNamespace.transaction_id = msftTxId;
        microsoftAdsNamespace.event_id = msftTxId + '_' + Date.now();
      }
      if (options.tax !== undefined) microsoftAdsNamespace.tax = parseFloat(options.tax) || 0.0;
      if (options.shipping !== undefined) microsoftAdsNamespace.shipping = parseFloat(options.shipping) || 0.0;
      if (options.affiliation) microsoftAdsNamespace.affiliation = String(options.affiliation);
    }

    var msftRemarketing = {
      ecomm_pagetype: pageType,
      ecomm_prodid: Array.isArray(microsoftProdIds) ? microsoftProdIds.map(String) : [],
      ecomm_totalvalue: val
    };
    if (options.categoryName) {
      msftRemarketing.ecomm_category = String(options.categoryName);
    }
    if (options.searchTerm) {
      msftRemarketing.ecomm_query = String(options.searchTerm);
    }
    microsoftAdsNamespace.dynamic_remarketing = msftRemarketing;

    var msUserData = options.microsoftUserData || options.microsoft_user_data ||
      (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.microsoftUserData ? window.psGtmConfig.microsoftUserData : null);
    if (msUserData && typeof msUserData === 'object') {
      microsoftAdsNamespace.pid = msUserData.pid || msUserData;
    }

    // -------------------------------------------------------------
    // OpenAI / ChatGPT Ads Namespace Builder
    // -------------------------------------------------------------
    var openAiPrecision = (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined)
      ? parseInt(window.psGtmConfig.currencyPrecision, 10)
      : 2;
    var openAiEventName = 'custom';
    var openAiType = 'custom';
    var openAiCustomName = null;
    var includeAmount = false;
    var includeContents = false;

    if (canonicalEventName === 'view_item' || canonicalEventName === 'view_item_list' || canonicalEventName === 'view_search_results' || canonicalEventName === 'view_cart') {
      openAiEventName = 'contents_viewed';
      openAiType = 'contents';
      includeContents = true;
    } else if (canonicalEventName === 'add_to_cart') {
      openAiEventName = 'items_added';
      openAiType = 'contents';
      includeAmount = true;
      includeContents = true;
    } else if (canonicalEventName === 'begin_checkout') {
      openAiEventName = 'checkout_started';
      openAiType = 'contents';
      includeAmount = true;
      includeContents = true;
    } else if (canonicalEventName === 'purchase') {
      openAiEventName = 'order_created';
      openAiType = 'contents';
      includeAmount = true;
      includeContents = true;
    } else {
      openAiEventName = 'custom';
      openAiType = 'custom';
      openAiCustomName = canonicalEventName;
    }

    var openAiContents = [];
    if (includeContents) {
      for (var k = 0; k < items.length; k++) {
        var openAiIt = items[k];
        if (!openAiIt) continue;
        var oaiCId = resolveCatalogItemId(openAiIt, strategy);
        var oaiQty = typeof openAiIt.quantity === 'number' ? openAiIt.quantity : parseInt(openAiIt.quantity, 10) || 1;
        if (oaiQty <= 0) oaiQty = 1;
        var oaiPrice = typeof openAiIt.price === 'number' ? openAiIt.price : parseFloat(openAiIt.price) || 0.0;
        var oaiItem = {
          id: isList ? String(openAiIt.item_id) : oaiCId,
          name: String(openAiIt.item_name || ''),
          content_type: isList ? 'product_group' : 'product'
        };
        if (openAiEventName === 'items_added') {
          oaiItem.quantity = oaiQty;
          oaiItem.amount = toMinorUnits(oaiPrice, curr, openAiPrecision);
          oaiItem.currency = curr;
        } else if (openAiEventName === 'checkout_started' || openAiEventName === 'order_created') {
          oaiItem.quantity = oaiQty;
        }
        openAiContents.push(oaiItem);
      }
    }

    var openAiData = {
      type: openAiType
    };
    if (includeAmount) {
      openAiData.amount = toMinorUnits(val, curr, openAiPrecision);
      openAiData.currency = curr;
    }
    if (includeContents) {
      openAiData.contents = openAiContents;
    }

    var oaiUserData = options.openaiUserData || options.openai_user_data ||
      (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.openaiUserData ? window.psGtmConfig.openaiUserData : null);
    if (oaiUserData && typeof oaiUserData === 'object' && Object.keys(oaiUserData).length > 0) {
      openAiData.user = oaiUserData;
    }

    var openaiAdsNamespace = {
      command: 'measure',
      event_name: openAiEventName,
      data: openAiData
    };
    if (openAiCustomName) {
      openaiAdsNamespace.options = {
        custom_event_name: openAiCustomName
      };
    }

    // -------------------------------------------------------------
    // Snapchat Ads Namespace Builder (Dual-Object: pixel & dynamic_ads)
    // -------------------------------------------------------------
    var snapchatEventName = 'CUSTOM_EVENT_1';
    switch (canonicalEventName) {
      case 'view_item_list':
        snapchatEventName = 'LIST_VIEW';
        break;
      case 'view_search_results':
      case 'search':
        snapchatEventName = 'SEARCH';
        break;
      case 'view_item':
        snapchatEventName = 'VIEW_CONTENT';
        break;
      case 'select_item':
        snapchatEventName = 'CUSTOM_EVENT_1';
        break;
      case 'add_to_wishlist':
        snapchatEventName = 'ADD_TO_WISHLIST';
        break;
      case 'add_to_cart':
        snapchatEventName = 'ADD_CART';
        break;
      case 'remove_from_cart':
        snapchatEventName = 'CUSTOM_EVENT_2';
        break;
      case 'view_cart':
        snapchatEventName = 'VIEW_CONTENT';
        break;
      case 'begin_checkout':
        snapchatEventName = 'START_CHECKOUT';
        break;
      case 'add_shipping_info':
        snapchatEventName = 'CUSTOM_EVENT_3';
        break;
      case 'add_payment_info':
        snapchatEventName = 'ADD_BILLING';
        break;
      case 'purchase':
        snapchatEventName = 'PURCHASE';
        break;
      default:
        snapchatEventName = 'CUSTOM_EVENT_1';
        break;
    }

    var snapItemIds = [];
    var snapContents = [];
    var snapBrands = [];

    for (var snIdx = 0; snIdx < items.length; snIdx++) {
      var sIt = items[snIdx];
      if (!sIt) continue;
      var sCatId = resolveCatalogItemId(sIt, strategy);
      var sId = isList ? String(sIt.item_id) : sCatId;
      var sQty = typeof sIt.quantity === 'number' ? sIt.quantity : parseInt(sIt.quantity, 10) || 1;
      if (sQty <= 0) sQty = 1;
      var sPrice = typeof sIt.price === 'number' ? sIt.price : parseFloat(sIt.price) || 0.0;

      if (snapItemIds.indexOf(sId) === -1) {
        snapItemIds.push(sId);
      }
      var sItemObj = {
        id: sId,
        quantity: sQty,
        item_price: sPrice
      };
      if (sIt.item_brand) {
        sItemObj.brand = String(sIt.item_brand);
        if (snapBrands.indexOf(String(sIt.item_brand)) === -1) {
          snapBrands.push(String(sIt.item_brand));
        }
      }
      snapContents.push(sItemObj);
    }

    // 1. Snap Pixel Object
    var snapchatPixel = {
      currency: curr,
      price: val,
      item_ids: snapItemIds,
      number_items: totalNumItems > 0 ? totalNumItems : 1
    };
    if (options && options.categoryName) {
      snapchatPixel.item_category = String(options.categoryName);
    } else if (isSingleProduct && items[0] && items[0].item_category) {
      snapchatPixel.item_category = String(items[0].item_category);
    }
    if (options && options.searchTerm) {
      snapchatPixel.search_string = String(options.searchTerm);
    }
    if (isSingleProduct && items[0] && items[0].item_name) {
      snapchatPixel.description = String(items[0].item_name);
    }
    if (canonicalEventName === 'purchase') {
      var snapTxId = options && (options.transactionId || options.orderId) ? String(options.transactionId || options.orderId) : '';
      if (snapTxId) {
        snapchatPixel.transaction_id = snapTxId;
      }
    }

    // 2. Dynamic Ads Object (CAPI custom_data)
    var snapchatDynamicAds = {
      currency: curr,
      value: val,
      content_type: isList ? 'product_group' : 'product',
      content_ids: snapItemIds,
      contents: snapContents,
      num_items: totalNumItems > 0 ? totalNumItems : 1
    };
    if (snapBrands.length > 0) {
      snapchatDynamicAds.brands = snapBrands;
    }
    if (isSingleProduct && items[0] && items[0].item_name) {
      snapchatDynamicAds.content_name = String(items[0].item_name);
    }
    if (options && options.categoryName) {
      snapchatDynamicAds.content_category = String(options.categoryName);
    } else if (isSingleProduct && items[0] && items[0].item_category) {
      snapchatDynamicAds.content_category = String(items[0].item_category);
    }
    if (options && options.searchTerm) {
      snapchatDynamicAds.search_string = String(options.searchTerm);
    }
    if (canonicalEventName === 'purchase') {
      var snapOrderId = options && (options.transactionId || options.orderId) ? String(options.transactionId || options.orderId) : '';
      if (snapOrderId) {
        snapchatDynamicAds.order_id = snapOrderId;
      }
    }

    var snapchatUserData = options.snapchatUserData || options.snapchat_user_data ||
      (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.snapchatUserData ? window.psGtmConfig.snapchatUserData : null);

    var snapchatNamespace = {
      event_name: snapchatEventName,
      action_source: 'WEB',
      pixel: snapchatPixel,
      dynamic_ads: snapchatDynamicAds
    };
    if (snapchatUserData && typeof snapchatUserData === 'object' && Object.keys(snapchatUserData).length > 0) {
      snapchatNamespace.user_data = snapchatUserData;
    }

    var masterEnabled = advSettings ? (advSettings.master_enabled !== false && advSettings.master_enabled !== 0 && advSettings.master_enabled !== '0') : true;
    if (!masterEnabled) {
      return {};
    }

    var metaEnabled = advSettings ? (advSettings.meta_enabled !== false && advSettings.meta_enabled !== 0 && advSettings.meta_enabled !== '0') : true;
    var tiktokEnabled = advSettings ? (advSettings.tiktok_enabled !== false && advSettings.tiktok_enabled !== 0 && advSettings.tiktok_enabled !== '0') : true;
    var gadsEnabled = advSettings ? (advSettings.gads_enabled !== false && advSettings.gads_enabled !== 0 && advSettings.gads_enabled !== '0') : true;
    var pinterestEnabled = advSettings ? (advSettings.pinterest_enabled !== false && advSettings.pinterest_enabled !== 0 && advSettings.pinterest_enabled !== '0') : true;
    var microsoftEnabled = advSettings ? (advSettings.microsoft_enabled !== false && advSettings.microsoft_enabled !== 0 && advSettings.microsoft_enabled !== '0') : true;
    var openaiEnabled = advSettings ? (advSettings.openai_enabled !== false && advSettings.openai_enabled !== 0 && advSettings.openai_enabled !== '0') : true;
    var snapchatEnabled = advSettings ? (advSettings.snapchat_enabled !== false && advSettings.snapchat_enabled !== 0 && advSettings.snapchat_enabled !== '0') : true;

    var metaEvents = (advSettings && Array.isArray(advSettings.meta_events)) ? advSettings.meta_events : null;
    var tiktokEvents = (advSettings && Array.isArray(advSettings.tiktok_events)) ? advSettings.tiktok_events : null;
    var gadsEvents = (advSettings && Array.isArray(advSettings.gads_events)) ? advSettings.gads_events : null;
    var pinterestEvents = (advSettings && Array.isArray(advSettings.pinterest_events)) ? advSettings.pinterest_events : null;
    var microsoftEvents = (advSettings && Array.isArray(advSettings.microsoft_events)) ? advSettings.microsoft_events : null;
    var openaiEvents = (advSettings && Array.isArray(advSettings.openai_events)) ? advSettings.openai_events : null;
    var snapchatEvents = (advSettings && Array.isArray(advSettings.snapchat_events)) ? advSettings.snapchat_events : null;

    var isSearchListing = (canonicalEventName === 'view_item_list' && (pageType === 'searchresults' || (options && options.listId && String(options.listId).indexOf('search') !== -1)));

    var result = {};
    if (!isSearchListing && metaEnabled && (!metaEvents || metaEvents.indexOf(canonicalEventName) !== -1)) {
      result.meta = metaNamespace;
    }
    if (!isSearchListing && tiktokEnabled && (!tiktokEvents || tiktokEvents.indexOf(canonicalEventName) !== -1)) {
      result.tiktok = tiktokNamespace;
    }
    if (gadsEnabled && (!gadsEvents || gadsEvents.indexOf(canonicalEventName) !== -1)) {
      result.google_ads = googleAdsNamespace;
    }
    if (!isSearchListing && pinterestEnabled && (!pinterestEvents || pinterestEvents.indexOf(canonicalEventName) !== -1)) {
      result.pinterest = pinterestNamespace;
    }
    if (microsoftEnabled && (!microsoftEvents || microsoftEvents.indexOf(canonicalEventName) !== -1)) {
      result.microsoft_ads = microsoftAdsNamespace;
    }
    if (openaiEnabled && (!openaiEvents || openaiEvents.indexOf(canonicalEventName) !== -1)) {
      result.openai_ads = openaiAdsNamespace;
    }
    if (snapchatEnabled && (!snapchatEvents || snapchatEvents.indexOf(canonicalEventName) !== -1)) {
      result.snapchat = snapchatNamespace;
    }

    return result;
  }

  function pushEcommerceEvent(canonicalEventName, ecommerceData, userData, additionalNamespaces) {
    if (!canonicalEventName || typeof canonicalEventName !== 'string') {
      log('error', 'Push aborted: Invalid event name');
      return false;
    }

    if (typeof window !== 'undefined') {
      window.dataLayer = window.dataLayer || [];
    }

    var finalEventName = formatEventName(canonicalEventName);
    ecommerceData = ecommerceData || {};

    var items = ecommerceData.items || [];
    var currency = ecommerceData.currency || (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
    var totalValue = ecommerceData.value !== undefined ? ecommerceData.value : calculateCartValue(items);

    var platformNamespaces = buildPlatformNamespaces(canonicalEventName, items, currency, totalValue, {
      listId: ecommerceData.item_list_id || '',
      listName: ecommerceData.item_list_name || '',
      categoryName: (items[0] && items[0].item_category) ? items[0].item_category : '',
      orderId: ecommerceData.transaction_id || '',
      transactionId: ecommerceData.transaction_id || '',
      discount: ecommerceData.discount || (additionalNamespaces && additionalNamespaces.order_totals ? (additionalNamespaces.order_totals.discount_total || 0) : 0),
      awMerchantId: ecommerceData.aw_merchant_id || (additionalNamespaces && additionalNamespaces.aw_merchant_id) || '',
      awFeedCountry: ecommerceData.aw_feed_country || (additionalNamespaces && additionalNamespaces.aw_feed_country) || '',
      awFeedLanguage: ecommerceData.aw_feed_language || (additionalNamespaces && additionalNamespaces.aw_feed_language) || '',
      newCustomer: ecommerceData.new_customer !== undefined ? ecommerceData.new_customer : (additionalNamespaces && additionalNamespaces.new_customer !== undefined ? additionalNamespaces.new_customer : undefined),
      customerLifetimeValue: ecommerceData.customer_lifetime_value !== undefined ? ecommerceData.customer_lifetime_value : (additionalNamespaces && additionalNamespaces.customer_lifetime_value !== undefined ? additionalNamespaces.customer_lifetime_value : undefined),
      searchTerm: ecommerceData.search_term || '',
      property: ecommerceData.shipping_tier || ecommerceData.payment_type || '',
      tax: ecommerceData.tax !== undefined ? ecommerceData.tax : (additionalNamespaces && additionalNamespaces.order_totals ? additionalNamespaces.order_totals.tax_total : undefined),
      shipping: ecommerceData.shipping !== undefined ? ecommerceData.shipping : (additionalNamespaces && additionalNamespaces.order_totals ? additionalNamespaces.order_totals.shipping_total : undefined),
      coupon: ecommerceData.coupon || (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.coupon) || '',
      affiliation: ecommerceData.affiliation || '',
      microsoftUserData: (additionalNamespaces && additionalNamespaces.microsoft_ads && additionalNamespaces.microsoft_ads.pid) ? additionalNamespaces.microsoft_ads.pid : (additionalNamespaces && additionalNamespaces.microsoft_user_data ? additionalNamespaces.microsoft_user_data : null),
      openaiUserData: (additionalNamespaces && additionalNamespaces.openai_ads && additionalNamespaces.openai_ads.data && additionalNamespaces.openai_ads.data.user) ? additionalNamespaces.openai_ads.data.user : (additionalNamespaces && additionalNamespaces.openai_user_data ? additionalNamespaces.openai_user_data : null),
      snapchatUserData: (additionalNamespaces && additionalNamespaces.snapchat && additionalNamespaces.snapchat.user_data) ? additionalNamespaces.snapchat.user_data : (additionalNamespaces && additionalNamespaces.snapchat_user_data ? additionalNamespaces.snapchat_user_data : null)
    });

    var canonicalPayload = {
      event: finalEventName,
      original_event: canonicalEventName,
      ecommerce: ecommerceData
    };

    if (ecommerceData.new_customer !== undefined || (additionalNamespaces && additionalNamespaces.new_customer !== undefined)) {
      canonicalPayload.new_customer = Boolean(ecommerceData.new_customer !== undefined ? ecommerceData.new_customer : additionalNamespaces.new_customer);
    }
    if (ecommerceData.customer_lifetime_value !== undefined || (additionalNamespaces && additionalNamespaces.customer_lifetime_value !== undefined)) {
      canonicalPayload.customer_lifetime_value = parseFloat(ecommerceData.customer_lifetime_value !== undefined ? ecommerceData.customer_lifetime_value : additionalNamespaces.customer_lifetime_value);
    }

    if (canonicalEventName === 'view_search_results') {
      if (ecommerceData.search_term !== undefined) {
        canonicalPayload.search_term = String(ecommerceData.search_term);
      }
      if (ecommerceData.search_results_count !== undefined) {
        canonicalPayload.search_results_count = Number(ecommerceData.search_results_count);
      }
    }

    if (platformNamespaces.meta) {
      canonicalPayload.meta = platformNamespaces.meta;
    }
    if (platformNamespaces.tiktok) {
      canonicalPayload.tiktok = platformNamespaces.tiktok;
    }
    if (platformNamespaces.google_ads) {
      canonicalPayload.google_ads = platformNamespaces.google_ads;
    }
    if (platformNamespaces.pinterest) {
      canonicalPayload.pinterest = platformNamespaces.pinterest;
    }
    if (platformNamespaces.microsoft_ads) {
      canonicalPayload.microsoft_ads = platformNamespaces.microsoft_ads;
    }
    if (platformNamespaces.openai_ads) {
      canonicalPayload.openai_ads = platformNamespaces.openai_ads;
    }
    if (platformNamespaces.snapchat) {
      canonicalPayload.snapchat = platformNamespaces.snapchat;
    }

    if (additionalNamespaces && typeof additionalNamespaces === 'object') {
      if (additionalNamespaces.meta) canonicalPayload.meta = Object.assign({}, canonicalPayload.meta || {}, additionalNamespaces.meta);
      if (additionalNamespaces.tiktok) canonicalPayload.tiktok = Object.assign({}, canonicalPayload.tiktok || {}, additionalNamespaces.tiktok);
      if (additionalNamespaces.google_ads) canonicalPayload.google_ads = Object.assign({}, canonicalPayload.google_ads || {}, additionalNamespaces.google_ads);
      if (additionalNamespaces.pinterest) canonicalPayload.pinterest = Object.assign({}, canonicalPayload.pinterest || {}, additionalNamespaces.pinterest);
      if (additionalNamespaces.microsoft_ads) canonicalPayload.microsoft_ads = Object.assign({}, canonicalPayload.microsoft_ads || {}, additionalNamespaces.microsoft_ads);
      if (additionalNamespaces.openai_ads) canonicalPayload.openai_ads = Object.assign({}, canonicalPayload.openai_ads || {}, additionalNamespaces.openai_ads);
      if (additionalNamespaces.snapchat) canonicalPayload.snapchat = Object.assign({}, canonicalPayload.snapchat || {}, additionalNamespaces.snapchat);
      if (additionalNamespaces.order_totals) canonicalPayload.order_totals = additionalNamespaces.order_totals;
    }

    if (userData && typeof userData === 'object') {
      canonicalPayload.user_data = userData;
    }

    // Explicitly reset the ecommerce and advertising objects to purge prior stale properties in GTM
    if (typeof window !== 'undefined' && window.dataLayer) {
      window.dataLayer.push({
        ecommerce: null,
        meta: null,
        tiktok: null,
        google_ads: null,
        pinterest: null,
        microsoft_ads: null,
        openai_ads: null,
        snapchat: null
      });
      window.dataLayer.push(canonicalPayload);
    }

    log('info', 'Dispatched ' + finalEventName, canonicalPayload);
    return true;
  }

  /**
   * Universal Cart Delta Engine:
   * Pure mathematical delta evaluator comparing previous cart state to new cart state.
   * Completely decoupled from the DOM.
   *
   * @param {Object} prevQuantities Map of "id_product_id_product_attribute" => integer quantity
   * @param {Object} prevProductsData Map of "id_product_id_product_attribute" => cached product details
   * @param {Array} newCartProducts Array of products from PrestaShop cart (e.g. resp.cart.products)
   * @param {Object} options Configuration overrides (precision, currentProduct, productCombinations)
   * @returns {Object} { itemsAdded: [], itemsRemoved: [], updatedQuantities: {}, updatedProductsData: {} }
   */
  function processCartDelta(prevQuantities, prevProductsData, newCartProducts, options) {
    options = options || {};
    prevQuantities = prevQuantities || {};
    prevProductsData = prevProductsData || {};
    newCartProducts = Array.isArray(newCartProducts) ? newCartProducts : [];

    var prec = (options.precision !== undefined && options.precision !== null)
      ? parseInt(options.precision, 10)
      : (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined
        ? parseInt(window.psGtmConfig.currencyPrecision, 10)
        : 2);

    var newQuantities = {};
    var newProductsData = {};
    var itemsAdded = [];
    var itemsRemoved = [];

    // 1. Process items in the new cart state
    for (var i = 0; i < newCartProducts.length; i++) {
      var item = newCartProducts[i];
      if (!item) continue;

      var idProd = String(item.id_product || item.item_id || item.id || '').trim();
      if (!idProd) continue;

      var rawAttr = (item.id_product_attribute !== undefined && item.id_product_attribute !== null)
        ? item.id_product_attribute
        : (item.variant_id !== undefined ? item.variant_id : '');
      var idAttr = (rawAttr && String(rawAttr) !== '0' && parseInt(rawAttr, 10) > 0) ? String(rawAttr) : '';

      var key = idProd + '_' + (idAttr || '0');
      var qty = parseInt(item.quantity || item.cart_quantity, 10) || 0;
      newQuantities[key] = qty;

      var rawPrice = parseFloat(item.price_wt || item.price_amount || item.price || 0.0);
      var price = roundHalfUp(rawPrice, prec);

      var brand = String(item.manufacturer_name || item.item_brand || item.manufacturer || '');
      if (!brand && options.currentProduct && String(options.currentProduct.item_id) === idProd) {
        brand = String(options.currentProduct.item_brand || '');
      }

      var category = String(item.category_name || item.category || item.item_category || '');
      if (!category && options.currentProduct && String(options.currentProduct.item_id) === idProd) {
        category = String(options.currentProduct.item_category || '');
      }

      var variant = '';
      if (idAttr && options.productCombinations && options.productCombinations[idAttr]) {
        variant = String(options.productCombinations[idAttr].item_variant || '');
      } else if (idAttr && options.currentProduct && String(options.currentProduct.variant_id) === idAttr) {
        variant = String(options.currentProduct.item_variant || '');
      } else {
        variant = formatVariant(item.attributes || item.item_variant || item.variant || item.attributes_small || '');
      }

      var itemDetails = {
        item_id: idProd,
        variant_id: idAttr,
        sku_id: String(item.reference || item.sku_id || ''),
        item_name: String(item.name || item.item_name || ''),
        item_brand: brand,
        item_category: category,
        item_variant: variant,
        price: price,
        quantity: qty
      };
      newProductsData[key] = itemDetails;

      var prevQty = parseInt(prevQuantities[key], 10) || 0;
      var delta = qty - prevQty;

      if (delta > 0) {
        var addedItem = Object.assign({}, itemDetails, { quantity: delta });
        itemsAdded.push(addedItem);
      } else if (delta < 0) {
        var removedItem = Object.assign({}, itemDetails, { quantity: Math.abs(delta) });
        itemsRemoved.push(removedItem);
      }
    }

    // 2. Identify products that existed previously but are now completely removed (absent or 0 in new state)
    for (var k in prevQuantities) {
      if (prevQuantities.hasOwnProperty(k)) {
        var oldQ = parseInt(prevQuantities[k], 10) || 0;
        var newQ = parseInt(newQuantities[k], 10) || 0;
        if (oldQ > 0 && newQ === 0) {
          var details = prevProductsData[k];
          if (!details) {
            var parts = k.split('_');
            details = {
              item_id: parts[0],
              variant_id: parts[1] && parts[1] !== '0' ? parts[1] : '',
              item_name: 'Product',
              price: 0.0
            };
          }
          var fullyRemovedItem = Object.assign({}, details, { quantity: oldQ });
          itemsRemoved.push(fullyRemovedItem);
        }
      }
    }

    return {
      itemsAdded: normalizeItems(itemsAdded, prec),
      itemsRemoved: normalizeItems(itemsRemoved, prec),
      updatedQuantities: newQuantities,
      updatedProductsData: newProductsData
    };
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
    formatVariant: formatVariant,
    processCartDelta: processCartDelta,
    resolveCatalogItemId: resolveCatalogItemId,
    buildPlatformNamespaces: buildPlatformNamespaces,
    toMinorUnits: toMinorUnits
  };
});
