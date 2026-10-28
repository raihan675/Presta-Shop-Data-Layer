/**
 * PrestaShop Universal Native Event Adapter:
 * 100% Backend & Pub/Sub Event-Driven — ZERO DOM SCRAPING.
 * Compatible with PrestaShop 1.7.8 through 8.2.x and 9.x across all themes.
 */
(function (window, document, EDL) {
  'use strict';

  if (!EDL) return;
  if (window.__psGtmAdapterLoaded) return;
  window.__psGtmAdapterLoaded = true;

  function syncConfig() {
    if (window.psGtmConfig && window.psGtmConfig.eventPrefix !== undefined) {
      EDL.setEventPrefix(window.psGtmConfig.eventPrefix);
    }
  }
  syncConfig();

  // Universal Storefront Marketing Attribution & Ad Click ID Persistence
  (function captureClientAttribution() {
    try {
      function setPersistentAttributionCookie(dataStr) {
        var maxAge = 2592000; // 30 days
        var isSecure = window.location.protocol === 'https:';
        var secureFlag = isSecure ? '; Secure' : '';
        var sameSiteFlag = '; SameSite=Lax';
        var pathFlag = '; path=/';

        // 1. Host cookie
        document.cookie = 'ps_gtm_attribution=' + encodeURIComponent(dataStr) + '; max-age=' + maxAge + pathFlag + sameSiteFlag + secureFlag;

        // 2. Wildcard root domain cookie (e.g. .familys-world.de)
        var hostname = window.location.hostname;
        if (hostname && hostname.indexOf('.') !== -1 && hostname !== 'localhost' && !/^\d+\.\d+\.\d+\.\d+$/.test(hostname)) {
          var parts = hostname.split('.');
          if (parts.length >= 2) {
            var rootDomain = '.' + parts.slice(-2).join('.');
            document.cookie = 'ps_gtm_attribution=' + encodeURIComponent(dataStr) + '; max-age=' + maxAge + pathFlag + '; domain=' + rootDomain + sameSiteFlag + secureFlag;
          }
        }
      }

      var data = {};
      try {
        var m = document.cookie.match(/(?:^|;\s*)ps_gtm_attribution=([^;]+)/);
        if (m) {
          data = JSON.parse(decodeURIComponent(m[1])) || {};
        } else {
          var ls = localStorage.getItem('ps_gtm_attribution');
          if (ls) data = JSON.parse(ls) || {};
        }
      } catch(e) {}

      var updated = false;

      // Extract click and campaign identifiers from URL query
      var s = window.location.search;
      if (s && s.indexOf('?') !== -1) {
        var params = new URLSearchParams(s.substring(1));
        var keys = ['gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'sccid', 'msclkid', 'oppref', 'epik', 'li_fat_id', 'rdt_cid', 'twclid', 'dclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
        for (var i = 0; i < keys.length; i++) {
          var v = params.get(keys[i]);
          if (v && typeof v === 'string' && v.trim() !== '') {
            data[keys[i]] = v.trim();
            updated = true;
          }
        }
      }

      // Extract first-party ad cookies
      var cookieMap = {
        '_fbp': 'fbp',
        '_fbc': 'fbc',
        '_ttp': 'ttp',
        '_scid': 'scid',
        '_uetvid': 'uetvid',
        '_uetsid': 'uetsid'
      };
      for (var cName in cookieMap) {
        var cm = document.cookie.match(new RegExp('(?:^|;\\s*)' + cName + '=([^;]+)'));
        if (cm && cm[1] && !data[cookieMap[cName]]) {
          data[cookieMap[cName]] = decodeURIComponent(cm[1]).trim();
          updated = true;
        }
      }

      if (updated || !localStorage.getItem('ps_gtm_attribution')) {
        var str = JSON.stringify(data);
        if (Object.keys(data).length > 0) {
          setPersistentAttributionCookie(str);
          try { localStorage.setItem('ps_gtm_attribution', str); } catch(e) {}
        }
      }
    } catch(e) {}
  })();

  // Cache of backend-resolved products to avoid redundant network requests
  var resolvedProductCache = {};

  // Authoritative in-memory cart quantity state (100% backend & cart object driven, ZERO DOM)
  var cartQuantities = {};
  var cartProductsData = {};
  var lastCartEventSignature = '';
  var lastRemoveSignature = '';
  var lastRemoveTime = 0;

  function initCartState() {
    if (window.psGtmConfig && window.psGtmConfig.cartProducts && typeof window.psGtmConfig.cartProducts === 'object') {
      for (var k in window.psGtmConfig.cartProducts) {
        if (window.psGtmConfig.cartProducts.hasOwnProperty(k)) {
          cartQuantities[String(k)] = parseInt(window.psGtmConfig.cartProducts[k], 10) || 0;
        }
      }
    }
    if (window.psGtmConfig && window.psGtmConfig.cartProductsData && typeof window.psGtmConfig.cartProductsData === 'object') {
      for (var kd in window.psGtmConfig.cartProductsData) {
        if (window.psGtmConfig.cartProductsData.hasOwnProperty(kd)) {
          cartProductsData[String(kd)] = window.psGtmConfig.cartProductsData[kd];
        }
      }
    }
  }
  initCartState();

  /**
   * Resolves authoritative product data directly from backend memory or AJAX controller.
   * Completely independent of DOM structure.
   */
  function resolveBackendProduct(idProduct, idProductAttribute, quantity, callback) {
    var idProdStr = String(idProduct || '').trim();
    var idAttrStr = (idProductAttribute && parseInt(idProductAttribute, 10) > 0) ? String(idProductAttribute).trim() : '';
    var qty = parseInt(quantity, 10) || 1;
    var cacheKey = idProdStr + '_' + idAttrStr;
    var zeroKey = idProdStr + '_0';

    // 0. Check in-memory cart products data
    var cartItem = (cartProductsData && (cartProductsData[cacheKey] || cartProductsData[zeroKey])) ||
                   (window.psGtmConfig && window.psGtmConfig.cartProductsData && (window.psGtmConfig.cartProductsData[cacheKey] || window.psGtmConfig.cartProductsData[zeroKey]));
    if (cartItem) {
      var itemCopy = Object.assign({}, cartItem);
      itemCopy.quantity = qty;
      callback(itemCopy);
      return;
    }

    // 1. Check current PDP product if on product page
    var currProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
    if (currProd && String(currProd.item_id) === idProdStr) {
      // If variant requested, check pre-hydrated database combinations map
      if (idAttrStr && window.psGtmConfig.productCombinations && window.psGtmConfig.productCombinations[idAttrStr]) {
        var combo = window.psGtmConfig.productCombinations[idAttrStr];
        var item = {
          item_id: String(combo.item_id),
          variant_id: String(combo.variant_id),
          sku_id: String(combo.sku_id),
          item_name: String(combo.item_name),
          item_brand: String(currProd.item_brand || ''),
          item_category: String(currProd.item_category || ''),
          item_variant: String(combo.item_variant || ''),
          price: parseFloat(combo.price) || parseFloat(currProd.price) || 0.0,
          quantity: qty
        };
        callback(item);
        return;
      } else if (!idAttrStr) {
        // Simple product or base combination
        var hasCombinations = window.psGtmConfig.productCombinations && Object.keys(window.psGtmConfig.productCombinations).length > 0;
        var itemBase = {
          item_id: String(currProd.item_id),
          variant_id: (hasCombinations && currProd.variant_id && parseInt(currProd.variant_id, 10) > 0) ? String(currProd.variant_id) : '',
          sku_id: String(currProd.sku_id || ''),
          item_name: String(currProd.item_name || ''),
          item_brand: String(currProd.item_brand || ''),
          item_category: String(currProd.item_category || ''),
          item_variant: hasCombinations ? String(currProd.item_variant || '') : '',
          price: parseFloat(currProd.price) || 0.0,
          quantity: qty
        };
        callback(itemBase);
        return;
      }
    }

    // 2. Check current listing products (Category, Search, Brand pages)
    if (window.psGtmConfig && window.psGtmConfig.currentList && Array.isArray(window.psGtmConfig.currentList.items)) {
      for (var li = 0; li < window.psGtmConfig.currentList.items.length; li++) {
        var listItem = window.psGtmConfig.currentList.items[li];
        if (listItem && String(listItem.item_id) === idProdStr) {
          var resolvedFromList = Object.assign({}, listItem);
          resolvedFromList.quantity = qty;
          callback(resolvedFromList);
          return;
        }
      }
    }

    // 3. Check local memory cache
    if (resolvedProductCache[cacheKey]) {
      var cached = Object.assign({}, resolvedProductCache[cacheKey]);
      cached.quantity = qty;
      callback(cached);
      return;
    }

    // 4. Query native backend AJAX FrontController
    if (window.psGtmConfig && window.psGtmConfig.ajaxUrl && idProdStr) {
      var url = window.psGtmConfig.ajaxUrl +
        '&action=resolveProduct' +
        '&id_product=' + encodeURIComponent(idProdStr) +
        '&id_product_attribute=' + encodeURIComponent(idAttrStr) +
        '&quantity=' + encodeURIComponent(qty);

      var xhr = new XMLHttpRequest();
      xhr.open('GET', url, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
          if (xhr.status === 200) {
            try {
              var resp = JSON.parse(xhr.responseText);
              if (resp && resp.success && resp.item) {
                resolvedProductCache[cacheKey] = resp.item;
                callback(resp.item, resp);
                return;
              }
            } catch (e) {}
          }
          // In case of network failure, fallback safely
          callback(null, null);
        }
      };
      xhr.send();
    } else {
      callback(null, null);
    }
  }

  var lastAddToCartTime = 0;
  var isCartUpdating = false;
  var lastInterceptedCartQty = 0;

  function isCartEndpoint(url) {
    if (!url || typeof url !== 'string') return false;
    var lower = url.toLowerCase();
    return lower.indexOf('controller=cart') !== -1 ||
           lower.indexOf('/cart') !== -1 ||
           lower.indexOf('/warenkorb') !== -1 ||
           lower.indexOf('/panier') !== -1 ||
           lower.indexOf('/carrinho') !== -1 ||
           lower.indexOf('/carrello') !== -1 ||
           lower.indexOf('/carrito') !== -1 ||
           lower.indexOf('onepagecheckout') !== -1 ||
           lower.indexOf('opc') !== -1;
  }

  function isCartDeleteRequest(url, data) {
    var str = String(url || '').toLowerCase();
    if (str.indexOf('delete=1') !== -1 ||
        str.indexOf('action=delete') !== -1 ||
        str.indexOf('deleteproduct') !== -1 ||
        str.indexOf('opc_delete_cart') !== -1 ||
        str.indexOf('delete-from-cart') !== -1) {
      return true;
    }
    if (typeof data === 'string') {
      var dLower = data.toLowerCase();
      if (dLower.indexOf('delete=1') !== -1 ||
          dLower.indexOf('action=delete') !== -1 ||
          dLower.indexOf('deleteproduct') !== -1 ||
          dLower.indexOf('opc_delete_cart') !== -1 ||
          dLower.indexOf('delete-from-cart') !== -1) {
        return true;
      }
    }
    if (typeof FormData !== 'undefined' && data instanceof FormData) {
      if (data.get('delete') === '1' || data.get('action') === 'delete' || data.get('action') === 'delete-from-cart') {
        return true;
      }
    }
    if (typeof data === 'object' && data !== null) {
      if (data.delete === 1 || data.delete === '1' || data.action === 'delete' || data.action === 'delete-from-cart') {
        return true;
      }
    }
    return false;
  }

  function extractCartDeleteData(url, data) {
    var prodId = '';
    var attrId = '';
    var qty = 1;

    function inspect(input) {
      if (!input) return;
      if (typeof input === 'string') {
        try {
          var decoded = decodeURIComponent(input);
          var pMatch = decoded.match(/(?:id_product|idProduct|productId)\s*[:=]\s*["']?(\d+)/i) ||
                      decoded.match(/(?:id_product|idProduct|productId)\D*(\d+)/i);
          var aMatch = decoded.match(/(?:id_product_attribute|idProductAttribute|productAttributeId)\s*[:=]\s*["']?(\d+)/i) ||
                      decoded.match(/(?:id_product_attribute|idProductAttribute|productAttributeId)\D*(\d+)/i);
          var qMatch = decoded.match(/(?:qty|quantity)\s*[:=]\s*["']?(\d+)/i);
          if (pMatch && pMatch[1]) prodId = prodId || pMatch[1];
          if (aMatch && aMatch[1]) attrId = attrId || aMatch[1];
          if (qMatch && qMatch[1]) qty = parseInt(qMatch[1], 10);
        } catch (e) {
          var pMatch2 = input.match(/(?:id_product|idProduct|productId)\D*(\d+)/i);
          var aMatch2 = input.match(/(?:id_product_attribute|idProductAttribute|productAttributeId)\D*(\d+)/i);
          if (pMatch2 && pMatch2[1]) prodId = prodId || pMatch2[1];
          if (aMatch2 && aMatch2[1]) attrId = attrId || aMatch2[1];
        }
      } else if (typeof FormData !== 'undefined' && input instanceof FormData) {
        prodId = input.get('id_product') || input.get('idProduct') || input.get('productId') || prodId;
        attrId = input.get('id_product_attribute') || input.get('idProductAttribute') || input.get('productAttributeId') || attrId;
        var fQty = input.get('qty') || input.get('quantity');
        if (fQty) qty = parseInt(fQty, 10);
      } else if (typeof input === 'object' && input !== null) {
        prodId = input.id_product || input.idProduct || input.productId || prodId;
        attrId = input.id_product_attribute || input.idProductAttribute || input.productAttributeId || attrId;
        if (input.qty || input.quantity) qty = parseInt(input.qty || input.quantity, 10);
      }
    }

    inspect(url);
    if (!prodId) inspect(data);

    return {
      productId: String(prodId || '').trim(),
      productAttributeId: (attrId && String(attrId) !== '0' && parseInt(attrId, 10) > 0) ? String(attrId).trim() : '',
      quantity: (qty && parseInt(qty, 10) > 0) ? parseInt(qty, 10) : null
    };
  }

  function dispatchDirectRemoveFromCart(idProduct, idProductAttribute, explicitQuantity) {
    if (!idProduct) return;
    var normAttr = (idProductAttribute && String(idProductAttribute) !== '0' && parseInt(idProductAttribute, 10) > 0)
      ? String(idProductAttribute).trim()
      : '';
    var key = String(idProduct).trim() + '_' + (normAttr || '0');
    var now = new Date().getTime();

    // Debounce rapid triggers / duplicate callbacks within 1500ms
    if (lastRemoveSignature === key && (now - lastRemoveTime < 1500)) {
      return;
    }
    lastRemoveSignature = key;
    lastRemoveTime = now;

    // Authoritative in-cart quantity resolution:
    // When a line item is removed/deleted, all existing units of that item in the cart are removed.
    var inCartQty = parseInt(cartQuantities[key], 10) ||
                    (cartProductsData[key] && parseInt(cartProductsData[key].quantity, 10)) ||
                    (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.cartProducts && parseInt(window.psGtmConfig.cartProducts[key], 10)) ||
                    (typeof window !== 'undefined' && window.psGtmConfig && window.psGtmConfig.cartProductsData && window.psGtmConfig.cartProductsData[key] && parseInt(window.psGtmConfig.cartProductsData[key].quantity, 10)) ||
                    0;

    var removeQty = inCartQty > 0
      ? inCartQty
      : ((explicitQuantity && parseInt(explicitQuantity, 10) > 0) ? parseInt(explicitQuantity, 10) : 1);
    var currencyCode = (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
    var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined)
      ? parseInt(window.psGtmConfig.currencyPrecision, 10)
      : 2;

    resolveBackendProduct(idProduct, normAttr, removeQty, function (resolvedItem) {
      var item = resolvedItem ? resolvedItem : (cartProductsData[key] || {
        item_id: String(idProduct),
        variant_id: normAttr,
        item_name: 'Product #' + idProduct,
        price: 0.0,
        quantity: removeQty
      });
      item.quantity = removeQty;

      var normItems = EDL.normalizeItems([item], prec);
      if (normItems.length > 0) {
        EDL.pushEvent('remove_from_cart', {
          currency: currencyCode,
          value: EDL.calculateCartValue(normItems, prec),
          items: normItems
        });
      }

      delete cartQuantities[key];
      delete cartProductsData[key];
      if (window.psGtmConfig && window.psGtmConfig.cartProductsData) {
        delete window.psGtmConfig.cartProductsData[key];
      }
    });
  }

  // Intercept native HTTP POST requests to PrestaShop CartController (Network/Controller level, ZERO DOM)
  (function () {
    if (typeof window === 'undefined' || !window.XMLHttpRequest) return;
    var origOpen = XMLHttpRequest.prototype.open;
    var origSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
      this._psGtmUrl = String(url || '');
      this._psGtmMethod = String(method || '').toUpperCase();
      return origOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function (data) {
      try {
        var isCartUrl = this._psGtmUrl && isCartEndpoint(this._psGtmUrl);

        if (this._psGtmMethod === 'POST' && isCartUrl && data) {
          var parsedQty = 0;
          if (typeof data === 'string') {
            var qtyMatch = data.match(/(?:^|[&?])(?:qty|quantity|quantity_wanted)=(\d+)/i);
            if (qtyMatch && qtyMatch[1]) {
              parsedQty = parseInt(qtyMatch[1], 10);
            }
          } else if (typeof FormData !== 'undefined' && data instanceof FormData) {
            var q = data.get('qty') || data.get('quantity') || data.get('quantity_wanted');
            if (q) parsedQty = parseInt(q, 10);
          } else if (typeof data === 'object') {
            var rawQ = data.qty || data.quantity || data.quantity_wanted;
            if (rawQ) parsedQty = parseInt(rawQ, 10);
          }

          if (parsedQty > 0) {
            lastInterceptedCartQty = parsedQty;
          }
        }

        // Intercept native PrestaShop Cart Deletion requests (Network/Controller level, ZERO DOM)
        if (isCartUrl && isCartDeleteRequest(this._psGtmUrl, data)) {
          var extractedDeleteXhr = extractCartDeleteData(this._psGtmUrl, data);
          if (extractedDeleteXhr.productId) {
            this.addEventListener('load', function () {
              if (this.status >= 200 && this.status < 300) {
                dispatchDirectRemoveFromCart(extractedDeleteXhr.productId, extractedDeleteXhr.productAttributeId, extractedDeleteXhr.quantity);
              }
            });
          }
        }

        // Intercept native PrestaShop Wishlist requests (blockwishlist / ps_wishlist / theme wishlists)
        if (isWishlistEndpoint(this._psGtmUrl)) {
          var currProdXhr = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
          var extractedXhr = extractWishlistProductData(this._psGtmUrl, data, currProdXhr);

          this.addEventListener('load', function () {
            if (this.status >= 200 && this.status < 300) {
              var isSuccess = false;
              try {
                var resp = JSON.parse(this.responseText);
                isSuccess = resp && (
                  resp.success === true ||
                  resp.status === 'success' ||
                  resp.status === true ||
                  resp.success === 1 ||
                  (resp.hasError === false && !resp.errors && !resp.error)
                );
              } catch (e) {
                var txt = (this.responseText || '').trim();
                if (txt === '1' || txt === 'ok' || txt === 'success' || (txt.length < 50 && txt.indexOf('success') !== -1)) {
                  isSuccess = true;
                }
              }

              if (isSuccess && extractedXhr.productId) {
                dispatchAddToWishlist(extractedXhr.productId, extractedXhr.productAttributeId);
              }
            }
          });
        }
      } catch (e) {}

      return origSend.apply(this, arguments);
    };
  })();

  // Intercept native fetch() requests for modern themes adding to wishlist (Apollo GraphQL / blockwishlist)
  (function () {
    if (typeof window === 'undefined' || !window.fetch) return;
    var origFetch = window.fetch;
    window.fetch = function (input, init) {
      var url = (typeof input === 'string') ? input : (input && input.url ? input.url : '');

      if (isCartEndpoint(url) && isCartDeleteRequest(url, init && init.body)) {
        var extractedDeleteFetch = extractCartDeleteData(url, init && init.body);
        if (extractedDeleteFetch.productId) {
          return origFetch.apply(this, arguments).then(function (res) {
            if (res.ok) {
              dispatchDirectRemoveFromCart(extractedDeleteFetch.productId, extractedDeleteFetch.productAttributeId, extractedDeleteFetch.quantity);
            }
            return res;
          });
        }
      }

      if (isWishlistEndpoint(url)) {
        var currProdFetch = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
        var body = init && init.body;
        var extractedFetch = extractWishlistProductData(url, body, currProdFetch);

        return origFetch.apply(this, arguments).then(function (res) {
          if (res.ok) {
            try {
              res.clone().text().then(function (rawText) {
                var isSuccess = false;
                try {
                  var resp = JSON.parse(rawText);
                  isSuccess = resp && (
                    resp.success === true ||
                    resp.status === 'success' ||
                    resp.status === true ||
                    resp.success === 1 ||
                    (resp.hasError === false && !resp.errors && !resp.error)
                  );
                } catch (e) {
                  var txt = (rawText || '').trim();
                  if (txt === '1' || txt === 'ok' || txt === 'success' || (txt.length < 50 && txt.indexOf('success') !== -1)) {
                    isSuccess = true;
                  }
                }

                if (isSuccess && extractedFetch.productId) {
                  dispatchAddToWishlist(extractedFetch.productId, extractedFetch.productAttributeId);
                }
              }).catch(function () {});
            } catch (e) {}
          }
          return res;
        });
      }
      return origFetch.apply(this, arguments);
    };
  })();

  // Universal extractor of product & attribute IDs from any wishlist trigger (URL, body, or object)
  function extractWishlistProductData(url, data, fallbackProduct) {
    var prodId = '';
    var attrId = '';

    function inspect(input) {
      if (!input) return;
      if (typeof input === 'string') {
        try {
          var decoded = decodeURIComponent(input);
          var pMatch = decoded.match(/(?:id_product|idProduct|productId|params\[id_product\])\s*[:=]\s*["']?(\d+)/i) ||
                      decoded.match(/(?:id_product|idProduct|productId|params\[id_product\])\D*(\d+)/i);
          var aMatch = decoded.match(/(?:id_product_attribute|idProductAttribute|productAttributeId|params\[id_product_attribute\])\s*[:=]\s*["']?(\d+)/i) ||
                      decoded.match(/(?:id_product_attribute|idProductAttribute|productAttributeId|params\[id_product_attribute\])\D*(\d+)/i);
          if (pMatch && pMatch[1]) prodId = prodId || pMatch[1];
          if (aMatch && aMatch[1]) attrId = attrId || aMatch[1];
        } catch (e) {
          var pMatch2 = input.match(/(?:id_product|idProduct|productId|params\[id_product\])\D*(\d+)/i);
          var aMatch2 = input.match(/(?:id_product_attribute|idProductAttribute|productAttributeId|params\[id_product_attribute\])\D*(\d+)/i);
          if (pMatch2 && pMatch2[1]) prodId = prodId || pMatch2[1];
          if (aMatch2 && aMatch2[1]) attrId = attrId || aMatch2[1];
        }

        if (!prodId) {
          try {
            var parsed = JSON.parse(input);
            if (parsed && typeof parsed === 'object') inspectObj(parsed);
          } catch (e) {}
        }
      } else if (typeof input === 'object') {
        inspectObj(input);
      }
    }

    function inspectObj(obj) {
      if (!obj) return;
      if (typeof FormData !== 'undefined' && obj instanceof FormData) {
        prodId = obj.get('id_product') || obj.get('idProduct') || obj.get('productId') || obj.get('params[id_product]') || prodId;
        attrId = obj.get('id_product_attribute') || obj.get('idProductAttribute') || obj.get('productAttributeId') || obj.get('params[id_product_attribute]') || attrId;
      } else if (typeof URLSearchParams !== 'undefined' && obj instanceof URLSearchParams) {
        prodId = obj.get('id_product') || obj.get('idProduct') || obj.get('productId') || obj.get('params[id_product]') || prodId;
        attrId = obj.get('id_product_attribute') || obj.get('idProductAttribute') || obj.get('productAttributeId') || obj.get('params[id_product_attribute]') || attrId;
      } else {
        var d = obj.detail || obj;
        prodId = d.productId || d.id_product || d.idProduct || (d.params && (d.params.id_product || d.params.productId)) || prodId;
        attrId = d.productAttributeId || d.id_product_attribute || d.idProductAttribute || (d.params && (d.params.id_product_attribute || d.params.productAttributeId)) || attrId;
      }
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

  function isWishlistEndpoint(url) {
    if (!url || typeof url !== 'string') return false;
    var lower = url.toLowerCase();
    var isTarget = lower.indexOf('blockwishlist') !== -1 ||
                   lower.indexOf('wishlist') !== -1 ||
                   lower.indexOf('iqitwishlist') !== -1 ||
                   lower.indexOf('leowishlist') !== -1 ||
                   lower.indexOf('wtwishlist') !== -1 ||
                   lower.indexOf('stwishlist') !== -1 ||
                   lower.indexOf('addproducttowishlist') !== -1 ||
                   lower.indexOf('addtowishlist') !== -1 ||
                   lower.indexOf('addtolist') !== -1;

    var isRemoval = lower.indexOf('delete') !== -1 ||
                    lower.indexOf('remove') !== -1 ||
                    lower.indexOf('deleteproduct') !== -1 ||
                    lower.indexOf('removefromlist') !== -1;

    return isTarget && !isRemoval;
  }

  var lastWishlistProductId = '';
  var lastWishlistTime = 0;

  function dispatchAddToWishlist(idProduct, idProductAttribute) {
    if (!idProduct) return;
    var now = new Date().getTime();
    var normAttr = (idProductAttribute && String(idProductAttribute) !== '0' && parseInt(idProductAttribute, 10) > 0)
      ? String(idProductAttribute).trim()
      : '';
    var key = String(idProduct).trim() + '_' + (normAttr || '0');

    if (lastWishlistProductId === key && (now - lastWishlistTime < 1500)) {
      return; // Debounce rapid triggers / duplicate callbacks
    }
    lastWishlistProductId = key;
    lastWishlistTime = now;

    var currencyCode = (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
    var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined)
      ? parseInt(window.psGtmConfig.currencyPrecision, 10)
      : 2;

    resolveBackendProduct(idProduct, normAttr, 1, function (resolvedItem) {
      if (!resolvedItem) {
        resolvedItem = {
          item_id: String(idProduct),
          variant_id: normAttr,
          item_name: 'Product #' + idProduct,
          price: 0.0,
          quantity: 1
        };
      }

      var normItems = EDL.normalizeItems([resolvedItem], prec);
      if (normItems.length > 0) {
        var totalVal = EDL.calculateCartValue(normItems, prec);
        EDL.pushEvent('add_to_wishlist', {
          currency: currencyCode,
          value: totalVal,
          items: normItems
        });
      }
    });
  }

  // Hook blockwishlist official Vue EventBus (window.WishlistEventBus)
  function hookWishlistEventBus() {
    function subscribeBus(bus) {
      if (!bus || typeof bus.$on !== 'function' || bus._psGtmHooked) return;
      bus._psGtmHooked = true;
      bus.$on('addedToWishlist', function (e) {
        var d = (e && e.detail) ? e.detail : (e || {});
        var prodId = d.productId || d.id_product || d.idProduct;
        var attrId = d.productAttributeId || d.id_product_attribute || d.idProductAttribute || 0;
        if (prodId) {
          dispatchAddToWishlist(prodId, attrId);
        }
      });
    }

    if (window.WishlistEventBus) {
      subscribeBus(window.WishlistEventBus);
    }

    try {
      var busHolder = window.WishlistEventBus;
      Object.defineProperty(window, 'WishlistEventBus', {
        configurable: true,
        enumerable: true,
        get: function () {
          return busHolder;
        },
        set: function (newVal) {
          busHolder = newVal;
          subscribeBus(newVal);
        }
      });
    } catch (e) {}

    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('wishlistEventBusInit', function () {
        if (window.WishlistEventBus) {
          subscribeBus(window.WishlistEventBus);
        }
      });
    }
  }
  hookWishlistEventBus();

  function onPrestashopReady() {
    syncConfig();
    initCartState();

    // 1. Native cart actions (add_to_cart, remove_from_cart) via PrestaShop Core Event Bus
    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('updateCart', function (event) {
        if (!event) return;

        // Failure Guard: If server reported an error (out of stock, invalid qty), suppress event
        if (event.resp && (event.resp.hasError || (event.resp.errors && event.resp.errors.length > 0))) {
          return;
        }

        var reason = event.reason || {};
        var action = reason.action || reason.linkAction || (event.resp && event.resp.cartAction) || '';
        var cart = event.resp ? event.resp.cart : null;
        var currencyCode = (cart && cart.currency) ? cart.currency.iso_code : ((window.psGtmConfig && window.psGtmConfig.currency) || 'EUR');
        var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;

        // Primary Path: Authoritative Universal Cart Delta Engine (Zero DOM)
        if (cart && Array.isArray(cart.products) && EDL.processCartDelta) {
          var deltaResult = EDL.processCartDelta(cartQuantities, cartProductsData, cart.products, {
            precision: prec,
            currentProduct: (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null,
            productCombinations: (window.psGtmConfig && window.psGtmConfig.productCombinations) ? window.psGtmConfig.productCombinations : null
          });

          // Update in-memory state
          cartQuantities = deltaResult.updatedQuantities;
          cartProductsData = deltaResult.updatedProductsData;

          // Emit add_to_cart for positive quantity changes (new additions or quantity increments)
          if (deltaResult.itemsAdded && deltaResult.itemsAdded.length > 0) {
            lastAddToCartTime = new Date().getTime();
            isCartUpdating = true;
            setTimeout(function () { isCartUpdating = false; }, 2500);

            EDL.pushEvent('add_to_cart', {
              currency: currencyCode,
              value: EDL.calculateCartValue(deltaResult.itemsAdded, prec),
              items: deltaResult.itemsAdded
            });
          }

          // Emit remove_from_cart for negative quantity changes (decrements or complete removals)
          if (deltaResult.itemsRemoved && deltaResult.itemsRemoved.length > 0) {
            var filteredRemoved = [];
            for (var rIdx = 0; rIdx < deltaResult.itemsRemoved.length; rIdx++) {
              var rItem = deltaResult.itemsRemoved[rIdx];
              var rKey = String(rItem.item_id).trim() + '_' + (rItem.variant_id ? String(rItem.variant_id).trim() : '0');
              if (lastRemoveSignature === rKey && (new Date().getTime() - lastRemoveTime < 1500)) {
                continue; // Skip duplicate already tracked
              }
              filteredRemoved.push(rItem);
            }
            if (filteredRemoved.length > 0) {
              EDL.pushEvent('remove_from_cart', {
                currency: currencyCode,
                value: EDL.calculateCartValue(filteredRemoved, prec),
                items: filteredRemoved
              });
            }
          }

          return;
        }

        // Secondary Fallback Path: For non-standard themes omitting cart.products in updateCart event
        if (action === 'add-to-cart' || action === 'up') {
          var now = new Date().getTime();
          if (now - lastAddToCartTime < 800) return;
          lastAddToCartTime = now;
          isCartUpdating = true;
          setTimeout(function () { isCartUpdating = false; }, 2500);

          var addedIdProduct = reason.idProduct || reason.id_product || (event.resp && (event.resp.id_product || event.resp.idProduct)) || '';
          if (!addedIdProduct && window.psGtmConfig && window.psGtmConfig.currentProduct) {
            addedIdProduct = window.psGtmConfig.currentProduct.item_id;
          }

          var rawAttr = (reason.idProductAttribute !== undefined && reason.idProductAttribute !== null)
            ? reason.idProductAttribute
            : (reason.id_product_attribute !== undefined ? reason.id_product_attribute : (event.resp && (event.resp.id_product_attribute !== undefined ? event.resp.id_product_attribute : event.resp.idProductAttribute)));
          var addedIdAttr = (rawAttr && parseInt(rawAttr, 10) > 0) ? String(rawAttr) : '';

          var qty = 1;
          if (event.resp && event.resp.quantity !== undefined && parseInt(event.resp.quantity, 10) > 0) {
            qty = parseInt(event.resp.quantity, 10);
          } else if (lastInterceptedCartQty > 0) {
            qty = lastInterceptedCartQty;
            lastInterceptedCartQty = 0;
          } else if (event.resp && event.resp.qty !== undefined && parseInt(event.resp.qty, 10) > 0) {
            qty = parseInt(event.resp.qty, 10);
          } else if (reason.quantity !== undefined && parseInt(reason.quantity, 10) > 0) {
            qty = parseInt(reason.quantity, 10);
          } else if (reason.qty !== undefined && parseInt(reason.qty, 10) > 0) {
            qty = parseInt(reason.qty, 10);
          }

          resolveBackendProduct(addedIdProduct, addedIdAttr, qty, function (resolvedItem) {
            if (resolvedItem) {
              var norm = EDL.normalizeItems([resolvedItem], prec);
              if (norm.length > 0) {
                EDL.pushEvent('add_to_cart', {
                  currency: currencyCode,
                  value: EDL.calculateCartValue(norm, prec),
                  items: norm
                });
              }
            }
          });

        } else if (action === 'delete-from-cart' || action === 'remove-from-cart' || action === 'down') {
          var removedIdProduct = reason.idProduct || reason.id_product || '';
          var rawRemovedAttr = reason.idProductAttribute || reason.id_product_attribute || '';
          var normRemovedAttr = (rawRemovedAttr && parseInt(rawRemovedAttr, 10) > 0) ? String(rawRemovedAttr) : '';
          var removeQty = (reason.quantity !== undefined && parseInt(reason.quantity, 10) > 0) ? parseInt(reason.quantity, 10) : 1;

          var cachedKey = String(removedIdProduct) + '_' + (normRemovedAttr || '0');
          if (lastRemoveSignature === cachedKey && (new Date().getTime() - lastRemoveTime < 1500)) {
            return; // Skip duplicate already tracked
          }
          if (cartQuantities[cachedKey] && action === 'delete-from-cart') {
            removeQty = cartQuantities[cachedKey];
          }

          resolveBackendProduct(removedIdProduct, normRemovedAttr, removeQty, function (resolvedItem) {
            var item = resolvedItem ? resolvedItem : (cartProductsData[cachedKey] || {
              item_id: String(removedIdProduct),
              variant_id: normRemovedAttr,
              item_name: 'Product',
              price: 0.0,
              quantity: removeQty
            });
            item.quantity = removeQty;

            var normItems = EDL.normalizeItems([item], prec);
            if (normItems.length > 0) {
              EDL.pushEvent('remove_from_cart', {
                currency: currencyCode,
                value: EDL.calculateCartValue(normItems, prec),
                items: normItems
              });
            }
          });
        }
      });
    }

    // 2. Native Combination Switch on PDP via PrestaShop Core Event Bus
    var lastViewedProductAttributeId = (window.psGtmConfig && window.psGtmConfig.currentProduct && window.psGtmConfig.currentProduct.variant_id)
      ? String(window.psGtmConfig.currentProduct.variant_id)
      : '';

    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('updatedProduct', function (event) {
        if (!event || !event.id_product_attribute || event.id_product_attribute === '0') {
          return;
        }

        var now = new Date().getTime();
        // Guard 1: Suppress combination event if cart action is in progress or occurred within 2500ms
        if (isCartUpdating || (now - lastAddToCartTime < 2500)) {
          return;
        }

        var idAttr = String(event.id_product_attribute);

        // Guard 2: Suppress duplicate view_item if the combination attribute did NOT actually change
        if (lastViewedProductAttributeId && idAttr === lastViewedProductAttributeId) {
          return;
        }
        lastViewedProductAttributeId = idAttr;

        var currentProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
        var idProduct = currentProd ? currentProd.item_id : (event.id_product || '');

        resolveBackendProduct(idProduct, idAttr, 1, function (variantItem) {
          if (variantItem) {
            if (window.psGtmConfig) {
              window.psGtmConfig.currentProduct = variantItem;
            }

            var items = EDL.normalizeItems([variantItem]);
            if (items.length > 0) {
              EDL.pushEvent('view_item', {
                currency: (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR',
                value: items[0].price,
                items: items
              });
            }
          }
        });
      });
    }

    // 3. Native select_item (GA4) Tracking for Category & Search Listings
    var lastSelectedItemId = '';
    var lastSelectedItemTime = 0;

    function handleSelectItem(idProduct) {
      if (!idProduct) return;
      var now = new Date().getTime();

      // Suppress select_item if a wishlist action occurred within 2000ms
      if (now - lastWishlistTime < 2000) {
        return;
      }

      // Suppress select_item on PDP if user clicked the main product being viewed
      var pageCtx = window.psGtmConfig && window.psGtmConfig.pageContext;
      var currentMainId = (window.psGtmConfig && window.psGtmConfig.currentProduct && window.psGtmConfig.currentProduct.item_id) || '';
      if (pageCtx && pageCtx.controller === 'product' && currentMainId && String(idProduct) === String(currentMainId)) {
        return;
      }

      if (lastSelectedItemId === String(idProduct) && (now - lastSelectedItemTime < 1500)) {
        return; // Debounce duplicate/rapid clicks within 1500ms
      }
      lastSelectedItemId = String(idProduct);
      lastSelectedItemTime = now;

      var list = window.psGtmConfig && window.psGtmConfig.currentList;
      var pageCtx = window.psGtmConfig && window.psGtmConfig.pageContext;
      var matchedItem = null;

      if (list && Array.isArray(list.items)) {
        for (var i = 0; i < list.items.length; i++) {
          if (String(list.items[i].item_id) === String(idProduct)) {
            matchedItem = list.items[i];
            break;
          }
        }
      }
      if (!matchedItem && window.psGtmConfig && window.psGtmConfig.searchResults && Array.isArray(window.psGtmConfig.searchResults.items)) {
        var sItems = window.psGtmConfig.searchResults.items;
        for (var si = 0; si < sItems.length; si++) {
          if (String(sItems[si].item_id) === String(idProduct)) {
            matchedItem = sItems[si];
            break;
          }
        }
      }

      // Tier 1: Current hydrated list
      var listId = (list && list.item_list_id) || (matchedItem && matchedItem.item_list_id) || '';
      var listName = (list && list.item_list_name) || (matchedItem && matchedItem.item_list_name) || '';

      // Tier 2: Page Context
      if (!listId && pageCtx && pageCtx.list_id) {
        listId = pageCtx.list_id;
        listName = pageCtx.list_name || pageCtx.list_id;
      }

      var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;

      function dispatchSelected(itemToTrack, explicitListId, explicitListName) {
        var finalItem = Object.assign({}, itemToTrack);
        var finalId = explicitListId || finalItem.item_list_id || listId;
        var finalName = explicitListName || finalItem.item_list_name || listName;

        // Tier 3 & 4 Fallbacks if still empty
        if (!finalId) {
          if (finalItem.item_category) {
            finalId = 'category_' + String(finalItem.item_category).toLowerCase().replace(/[^a-z0-9_-]/g, '_');
            finalName = finalItem.item_category;
          } else {
            finalId = 'product_listing';
            finalName = 'Product Listing';
          }
        }
        if (!finalName) {
          finalName = finalId;
        }

        finalItem.item_list_id = finalId;
        finalItem.item_list_name = finalName;

        lastSelectedItemId = String(idProduct);
        lastSelectedItemTime = now;

        var normItems = EDL.normalizeItems([finalItem], prec);
        if (normItems.length > 0) {
          EDL.pushEvent('select_item', {
            item_list_id: finalId,
            item_list_name: finalName,
            items: normItems
          });
        }
      }

      if (matchedItem) {
        dispatchSelected(matchedItem, listId, listName);
      } else {
        // Fallback resolution for non-hydrated or custom listings
        resolveBackendProduct(idProduct, 0, 1, function (resolvedItem, fullResp) {
          if (resolvedItem) {
            var resolvedListId = listId;
            var resolvedListName = listName;

            // Tier 3: Use authoritative backend category if listId is not set
            if (!resolvedListId) {
              if (fullResp && fullResp.default_category_id) {
                resolvedListId = 'category_' + fullResp.default_category_id;
                resolvedListName = fullResp.default_category_name || ('Category #' + fullResp.default_category_id);
              } else if (resolvedItem.item_list_id) {
                resolvedListId = resolvedItem.item_list_id;
                resolvedListName = resolvedItem.item_list_name || resolvedListId;
              }
            }

            dispatchSelected(resolvedItem, resolvedListId, resolvedListName);
          }
        });
      }
    }

    // Quick View handler (select_item omitted per merchant configuration)
    window.prestashop.on('clickQuickView', function (event) {
      // Intentionally decoupled — select_item omitted per theme-independent architecture
    });

    // 4. PrestaShop Core Wishlist Event Bus & Custom Document Listeners (blockwishlist / ps_wishlist)
    window.prestashop.on('addToWishlist', function (e) {
      var d = (e && e.detail) ? e.detail : (e || {});
      var idProd = d.id_product || d.idProduct || d.productId || (e && e.id_product);
      var idAttr = d.id_product_attribute || d.idProductAttribute || d.productAttributeId || (e && e.id_product_attribute) || 0;
      if (idProd) dispatchAddToWishlist(idProd, idAttr);
    });
    window.prestashop.on('wishlist:add', function (e) {
      var d = (e && e.detail) ? e.detail : (e || {});
      var idProd = d.id_product || d.idProduct || d.productId || (e && e.id_product);
      var idAttr = d.id_product_attribute || d.idProductAttribute || d.productAttributeId || (e && e.id_product_attribute) || 0;
      if (idProd) dispatchAddToWishlist(idProd, idAttr);
    });
    window.prestashop.on('addedToWishlist', function (e) {
      var d = (e && e.detail) ? e.detail : (e || {});
      var idProd = d.id_product || d.idProduct || d.productId || (e && e.id_product);
      var idAttr = d.id_product_attribute || d.idProductAttribute || d.productAttributeId || (e && e.id_product_attribute) || 0;
      if (idProd) dispatchAddToWishlist(idProd, idAttr);
    });

    document.addEventListener('blockwishlist:added', function (e) {
      var detail = (e && e.detail) || {};
      var idProd = detail.idProduct || detail.id_product || detail.productId || '';
      var idAttr = detail.idProductAttribute || detail.id_product_attribute || detail.productAttributeId || 0;
      if (idProd) dispatchAddToWishlist(idProd, idAttr);
    });

    document.addEventListener('wishlist:added', function (e) {
      var detail = (e && e.detail) || {};
      var idProd = detail.idProduct || detail.id_product || detail.productId || '';
      var idAttr = detail.idProductAttribute || detail.id_product_attribute || detail.productAttributeId || 0;
      if (idProd) dispatchAddToWishlist(idProd, idAttr);
    });

    // 5. Checkout Step Observers: add_shipping_info & add_payment_info (Universal PrestaShop Core & Custom Themes)
    var lastTrackedShippingTier = '';
    var lastTrackedShippingTime = 0;
    var lastTrackedPaymentType = '';
    var lastTrackedPaymentTime = 0;

    function getCheckoutItems() {
      if (window.psGtmConfig && window.psGtmConfig.initialServerPayload && window.psGtmConfig.initialServerPayload.ecommerce && Array.isArray(window.psGtmConfig.initialServerPayload.ecommerce.items) && window.psGtmConfig.initialServerPayload.ecommerce.items.length > 0) {
        return window.psGtmConfig.initialServerPayload.ecommerce.items;
      }
      if (window.psGtmConfig && window.psGtmConfig.cartProductsData) {
        var items = [];
        for (var k in window.psGtmConfig.cartProductsData) {
          if (window.psGtmConfig.cartProductsData.hasOwnProperty(k)) {
            items.push(window.psGtmConfig.cartProductsData[k]);
          }
        }
        if (items.length > 0) return items;
      }
      return [];
    }

    // Capture Carrier / Delivery Step Selection (add_shipping_info)
    function dispatchShippingInfo(carrierName) {
      var tier = String(carrierName || 'Standard Delivery').trim();
      if (!tier) tier = 'Standard Delivery';
      var now = new Date().getTime();

      // Deduplicate rapid identical triggers (preserve ability to switch between methods)
      if (lastTrackedShippingTier === tier && (now - lastTrackedShippingTime < 2500)) {
        return;
      }

      var rawItems = getCheckoutItems();
      var normItems = EDL.normalizeItems(rawItems);
      if (normItems.length === 0) return;

      var currency = (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
      var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;
      var val = EDL.calculateCartValue(normItems, prec);
      var coupon = (window.psGtmConfig && window.psGtmConfig.coupon) || '';

      lastTrackedShippingTier = tier;
      lastTrackedShippingTime = now;

      var payload = {
        currency: currency,
        value: val,
        shipping_tier: tier,
        items: normItems
      };
      if (coupon) {
        payload.coupon = coupon;
      }

      EDL.pushEvent('add_shipping_info', payload);
    }

    function resolveCarrierFromInput(inputEl) {
      if (!inputEl) {
        if (window.psGtmConfig && window.psGtmConfig.selectedCarrier) {
          return window.psGtmConfig.selectedCarrier;
        }
        return 'Standard Delivery';
      }
      var val = inputEl.value || (inputEl.dataset && inputEl.dataset.carrierId) || '';
      var m = String(val).match(/(\d+)/);
      var idCarrier = m ? parseInt(m[1], 10) : 0;
      if (idCarrier && window.psGtmConfig && window.psGtmConfig.carriers) {
        if (window.psGtmConfig.carriers[idCarrier]) {
          return window.psGtmConfig.carriers[idCarrier];
        }
        if (window.psGtmConfig.carriers[String(idCarrier)]) {
          return window.psGtmConfig.carriers[String(idCarrier)];
        }
      }
      return idCarrier ? ('Carrier #' + idCarrier) : 'Standard Delivery';
    }

    function findCheckedCarrierInput(formEl) {
      var scope = formEl || document;
      if (!scope.querySelectorAll) return null;
      var inputs = scope.querySelectorAll('input[name^="delivery_option"], input[id^="delivery_option_"]');
      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].checked) {
          return inputs[i];
        }
      }
      return null;
    }

    // Trigger 1: Customer changes shipping method radio button
    document.addEventListener('change', function (e) {
      var target = e.target;
      if (!target) return;
      var isCarrier = (target.name && target.name.indexOf('delivery_option') === 0) ||
                      (target.id && target.id.indexOf('delivery_option') === 0);
      if (isCarrier && target.checked) {
        dispatchShippingInfo(resolveCarrierFromInput(target));
      }
    }, true);

    // Trigger 2: Delivery Step Form Submission (standard PrestaShop delivery form submit)
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (form && (form.id === 'js-delivery' || (form.action && form.action.indexOf('delivery') !== -1))) {
        var carrierInput = findCheckedCarrierInput(form);
        dispatchShippingInfo(resolveCarrierFromInput(carrierInput));
      }
    }, true);

    // Trigger 3: Continue / Confirm Delivery button clicked
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('button[name="confirmDeliveryOption"], button[name="confirm-delivery-step"], .continue[name="confirm-addresses"]') : null;
      if (btn && (btn.name === 'confirmDeliveryOption' || btn.name === 'confirm-delivery-step')) {
        var form = btn.form || (document.forms && document.forms['js-delivery']) || (document.querySelector && document.querySelector('#js-delivery'));
        var carrierInput = findCheckedCarrierInput(form);
        dispatchShippingInfo(resolveCarrierFromInput(carrierInput));
      }
    }, true);

    // Trigger 4: PrestaShop Core JS Event Bus (changedCheckoutStep)
    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('changedCheckoutStep', function () {
        var carrierInput = findCheckedCarrierInput();
        if (carrierInput) {
          dispatchShippingInfo(resolveCarrierFromInput(carrierInput));
        }
      });
    }

    // Capture Payment Step Selection (add_payment_info)
    function resolvePaymentMethod(targetInput) {
      if (!targetInput) return 'Online Payment';
      var id = targetInput.id || '';
      var val = targetInput.value || '';
      var modName = (targetInput.dataset && (targetInput.dataset.moduleName || targetInput.dataset.module)) || '';

      var pMap = (window.psGtmConfig && window.psGtmConfig.paymentOptionsMap) || {};
      var pMethods = (window.psGtmConfig && window.psGtmConfig.paymentMethods) || {};

      // 1. Check authoritative paymentOptionsMap by option ID (e.g. payment-option-1)
      if (id && pMap[id]) return pMap[id];
      // 2. Check by option value
      if (val && pMap[val]) return pMap[val];
      // 3. Check by module name
      if (modName && pMap[modName]) return pMap[modName];
      if (modName && pMethods[modName]) return pMethods[modName];
      // 4. Check if module name can be inferred from input ID
      if (id) {
        for (var k in pMethods) {
          if (pMethods.hasOwnProperty(k) && id.indexOf(k) !== -1) {
            return pMethods[k];
          }
        }
      }
      return modName ? modName : (val && val !== 'on' ? val : 'Online Payment');
    }

    function dispatchPaymentInfo(paymentMethod) {
      var pType = String(paymentMethod || 'Online Payment').trim();
      if (!pType) pType = 'Online Payment';
      var now = new Date().getTime();

      // Deduplicate rapid identical triggers (preserve ability to switch between payment options)
      if (lastTrackedPaymentType === pType && (now - lastTrackedPaymentTime < 2500)) {
        return;
      }

      var rawItems = getCheckoutItems();
      var normItems = EDL.normalizeItems(rawItems);
      if (normItems.length === 0) return;

      var currency = (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
      var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;
      var val = EDL.calculateCartValue(normItems, prec);
      var coupon = (window.psGtmConfig && window.psGtmConfig.coupon) || '';

      lastTrackedPaymentType = pType;
      lastTrackedPaymentTime = now;

      var payload = {
        currency: currency,
        value: val,
        payment_type: pType,
        items: normItems
      };
      if (coupon) {
        payload.coupon = coupon;
      }

      EDL.pushEvent('add_payment_info', payload);
    }

    // Trigger 1: Payment Radio Button Changed
    document.addEventListener('change', function (e) {
      var target = e.target;
      if (target && (target.name === 'payment-option' || (target.id && target.id.indexOf('payment-option') === 0)) && target.checked) {
        dispatchPaymentInfo(resolvePaymentMethod(target));
      }
    }, true);

    // Trigger 2: Click on Payment Option Container or Label (covers custom JS radio UI themes)
    document.addEventListener('click', function (e) {
      if (!e.target || !e.target.closest) return;
      var optContainer = e.target.closest('.payment-option, [data-module-name], .payment-options label');
      if (optContainer) {
        setTimeout(function () {
          var radio = optContainer.querySelector ? (optContainer.querySelector('input[type="radio"]') || document.querySelector('input[name="payment-option"]:checked')) : null;
          if (radio && radio.checked) {
            dispatchPaymentInfo(resolvePaymentMethod(radio));
          }
        }, 60);
      }
    }, true);

    // Trigger 3: Final Order Place / Payment Confirmation Button Clicked (ensures default pre-selected payment option is tracked)
    document.addEventListener('click', function (e) {
      if (!e.target || !e.target.closest) return;
      var confirmBtn = e.target.closest('#payment-confirmation button, .payment-confirmation button, button[id^="pay-with-"], [data-action="place-order"]');
      if (confirmBtn) {
        var activeRadio = (document.querySelector && document.querySelector('input[name="payment-option"]:checked')) || (document.querySelector && document.querySelector('.payment-option input[type="radio"]:checked'));
        if (activeRadio) {
          dispatchPaymentInfo(resolvePaymentMethod(activeRadio));
        } else if (window.psGtmConfig && window.psGtmConfig.paymentOptionsMap) {
          var keys = Object.keys(window.psGtmConfig.paymentOptionsMap);
          if (keys.length > 0) {
            dispatchPaymentInfo(window.psGtmConfig.paymentOptionsMap[keys[0]]);
          }
        }
      }
    }, true);

    // 7. Native Faceted Navigation & AJAX Catalog Pagination (ps_facetedsearch / core listing)
    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('updateProductList', function (eventData) {
        // PrestaShop updates product listings dynamically via AJAX.
        // Delegated document click listeners automatically cover re-rendered miniatures.
      });
    }

    // 8. Client-Side Search Discovery & Third-Party Search Theme Safety Net
    (function () {
      if (typeof window === 'undefined') return;

      function checkSearchDiscovery() {
        var pageCtx = window.psGtmConfig && window.psGtmConfig.pageContext;
        var isSearch = false;
        if (pageCtx && pageCtx.controller) {
          var cLower = String(pageCtx.controller).toLowerCase();
          if (cLower === 'search' || cLower.indexOf('search') !== -1) {
            isSearch = true;
          }
        }
        if (!isSearch && window.location) {
          var href = window.location.href || '';
          if (href.indexOf('/suche') !== -1 || href.indexOf('/search') !== -1 || href.indexOf('iqitsearch') !== -1 || /[?&](?:s|search_query|q|query)=/.test(href)) {
            isSearch = true;
          }
        }
        if (!isSearch) return;

        var query = '';
        try {
          if (window.location && window.location.search) {
            var params = new URLSearchParams(window.location.search);
            query = params.get('s') || params.get('search_query') || params.get('q') || params.get('query') || params.get('tag') || '';
          }
        } catch (e) {}

        if (!query && window.psGtmConfig) {
          if (window.psGtmConfig.searchResults && window.psGtmConfig.searchResults.search_term) {
            query = String(window.psGtmConfig.searchResults.search_term).trim();
          } else if (window.psGtmConfig.pageContext && window.psGtmConfig.pageContext.search_term) {
            query = String(window.psGtmConfig.pageContext.search_term).trim();
          }
        }

        if (!query) return;

        if (window.psGtmConfig) {
          window.psGtmConfig.pageContext = window.psGtmConfig.pageContext || {};
          window.psGtmConfig.pageContext.search_term = query;
        }

        var hasSearchEventPushed = false;
        if (window.dataLayer && Array.isArray(window.dataLayer)) {
          for (var i = 0; i < window.dataLayer.length; i++) {
            var entry = window.dataLayer[i];
            if (entry && (entry.original_event === 'view_search_results' || (entry.event && entry.event.indexOf('view_search_results') !== -1))) {
              hasSearchEventPushed = true;
              break;
            }
          }
        }

        if (!hasSearchEventPushed && EDL && typeof EDL.pushEvent === 'function') {
          var listItems = (window.psGtmConfig && window.psGtmConfig.searchResults && window.psGtmConfig.searchResults.items) || (window.psGtmConfig && window.psGtmConfig.currentList && window.psGtmConfig.currentList.items) || [];
          EDL.pushEvent('view_search_results', {
            search_term: query,
            search_results_count: listItems.length,
            items: listItems
          });
        }
      }

      checkSearchDiscovery();
    })();

    // 9. Order Confirmation & Checkout Marketing Attribution Synchronization
    (function () {
      function syncAttributionNow(orderId) {
        try {
          var raw = localStorage.getItem('ps_gtm_attribution');
          if (!raw) {
            var m = document.cookie.match(/(?:^|;\s*)ps_gtm_attribution=([^;]+)/);
            if (m) raw = decodeURIComponent(m[1]);
          }
          if (!raw) return;

          var ajaxUrl = (window.psGtmConfig && window.psGtmConfig.ajaxUrl) ? window.psGtmConfig.ajaxUrl : '';
          if (!ajaxUrl && typeof prestashop !== 'undefined' && prestashop.urls && prestashop.urls.base_url) {
            ajaxUrl = prestashop.urls.base_url + 'index.php?fc=module&module=ps_gtm_datalayer&controller=ajax';
          }
          if (!ajaxUrl) return;

          var oId = orderId || (window.psGtmConfig && window.psGtmConfig.orderId) || 0;
          if (!oId && window.location && window.location.search) {
            var sp = new URLSearchParams(window.location.search);
            if (sp.has('id_order')) {
              oId = parseInt(sp.get('id_order'), 10) || 0;
            }
          }

          var fd = new FormData();
          fd.append('action', 'syncAttribution');
          fd.append('attribution', raw);
          if (oId > 0) {
            fd.append('id_order', oId);
          }

          if (navigator.sendBeacon && oId > 0) {
            navigator.sendBeacon(ajaxUrl, fd);
          } else if (window.fetch) {
            fetch(ajaxUrl, {
              method: 'POST',
              body: fd,
              credentials: 'same-origin'
            }).catch(function(){});
          }
        } catch(e) {}
      }

      window.__psGtmSyncAttribution = syncAttributionNow;

      var isConfirmation = false;
      if (window.psGtmConfig && window.psGtmConfig.orderId) {
        isConfirmation = true;
      } else if (window.location && window.location.pathname) {
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('order-confirmation') !== -1 || path.indexOf('bestellbestaetigung') !== -1 || path.indexOf('bestellbestatigung') !== -1 || path.indexOf('commande-confirmee') !== -1) {
          isConfirmation = true;
        }
      }

      if (isConfirmation) {
        syncAttributionNow();
      }
    })();

  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onPrestashopReady);
  } else {
    onPrestashopReady();
  }

})(window, document, window.EcommerceDataLayer);
