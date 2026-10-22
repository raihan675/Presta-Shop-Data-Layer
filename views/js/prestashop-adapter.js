/**
 * PrestaShop Universal Native Event Adapter:
 * 100% Backend & Pub/Sub Event-Driven — ZERO DOM SCRAPING.
 * Compatible with PrestaShop 1.7.8 through 8.2.x and 9.x across all themes.
 */
(function (window, document, EDL) {
  'use strict';

  if (!EDL) return;

  function syncConfig() {
    if (window.psGtmConfig && window.psGtmConfig.eventPrefix !== undefined) {
      EDL.setEventPrefix(window.psGtmConfig.eventPrefix);
    }
  }
  syncConfig();

  // Cache of backend-resolved products to avoid redundant network requests
  var resolvedProductCache = {};

  /**
   * Resolves authoritative product data directly from backend memory or AJAX controller.
   * Completely independent of DOM structure.
   */
  function resolveBackendProduct(idProduct, idProductAttribute, quantity, callback) {
    var idProdStr = String(idProduct || '').trim();
    var idAttrStr = (idProductAttribute && parseInt(idProductAttribute, 10) > 0) ? String(idProductAttribute).trim() : '';
    var qty = parseInt(quantity, 10) || 1;
    var cacheKey = idProdStr + '_' + idAttrStr;

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
                callback(resp.item);
                return;
              }
            } catch (e) {}
          }
          // In case of network failure, fallback safely
          callback(null);
        }
      };
      xhr.send();
    } else {
      callback(null);
    }
  }

  var lastAddToCartTime = 0;
  var isCartUpdating = false;
  var lastInterceptedCartQty = 0;

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
        var isCartUrl = this._psGtmUrl && (
          this._psGtmUrl.indexOf('controller=cart') !== -1 ||
          this._psGtmUrl.indexOf('/cart') !== -1
        );

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

  // Authoritative in-memory cart quantity state (100% backend & cart object driven, ZERO DOM)
  var cartQuantities = {};
  var cartProductsData = {};
  var lastCartEventSignature = '';

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

  function onPrestashopReady() {
    if (!window.prestashop) return;
    syncConfig();
    initCartState();

    // 1. Native cart actions (add_to_cart, remove_from_cart) via PrestaShop Core Event Bus
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
          EDL.pushEvent('remove_from_cart', {
            currency: currencyCode,
            value: EDL.calculateCartValue(deltaResult.itemsRemoved, prec),
            items: deltaResult.itemsRemoved
          });
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

    // 2. Native Combination Switch on PDP via PrestaShop Core Event Bus
    var lastViewedProductAttributeId = (window.psGtmConfig && window.psGtmConfig.currentProduct && window.psGtmConfig.currentProduct.variant_id)
      ? String(window.psGtmConfig.currentProduct.variant_id)
      : '';

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

    // 3. Native select_item (GA4) Tracking for Category & Search Listings
    var lastSelectedItemId = '';
    var lastSelectedItemTime = 0;

    function handleSelectItem(idProduct) {
      if (!idProduct) return;
      var now = new Date().getTime();
      if (lastSelectedItemId === String(idProduct) && (now - lastSelectedItemTime < 1000)) {
        return; // Debounce duplicate/rapid clicks
      }

      var list = window.psGtmConfig && window.psGtmConfig.currentList;
      var matchedItem = null;

      if (list && Array.isArray(list.items)) {
        for (var i = 0; i < list.items.length; i++) {
          if (String(list.items[i].item_id) === String(idProduct)) {
            matchedItem = list.items[i];
            break;
          }
        }
      }

      var listId = (list && list.item_list_id) || (matchedItem && matchedItem.item_list_id) || '';
      var listName = (list && list.item_list_name) || (matchedItem && matchedItem.item_list_name) || '';

      if (matchedItem) {
        lastSelectedItemId = String(idProduct);
        lastSelectedItemTime = now;

        var selectedItem = Object.assign({}, matchedItem);
        selectedItem.item_list_id = listId;
        selectedItem.item_list_name = listName;

        var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;
        var normItems = EDL.normalizeItems([selectedItem], prec);

        if (normItems.length > 0) {
          EDL.pushEvent('select_item', {
            item_list_id: listId,
            item_list_name: listName,
            items: normItems
          });
        }
      } else {
        // Fallback resolution for non-hydrated or custom listings
        resolveBackendProduct(idProduct, 0, 1, function (resolvedItem) {
          if (resolvedItem) {
            lastSelectedItemId = String(idProduct);
            lastSelectedItemTime = now;

            resolvedItem.item_list_id = listId;
            resolvedItem.item_list_name = listName;

            var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;
            var normItems = EDL.normalizeItems([resolvedItem], prec);

            if (normItems.length > 0) {
              EDL.pushEvent('select_item', {
                item_list_id: listId,
                item_list_name: listName,
                items: normItems
              });
            }
          }
        });
      }
    }

    // Capture Quick View clicks via PrestaShop Core Event Bus
    window.prestashop.on('clickQuickView', function (event) {
      var idProduct = (event && event.dataset && (event.dataset.idProduct || event.dataset.id_product)) || '';
      if (idProduct) {
        handleSelectItem(idProduct);
      }
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

    // 5. Fallback Delegated Click Handler on Wishlist Triggers (Universal Theme Compatibility)
    document.addEventListener('click', function (e) {
      if (!e.target || !e.target.closest) return;
      var btn = e.target.closest(
        '.wishlist-button, .wishlist-button-add, .btn-wishlist, [data-action="add-to-wishlist"], ' +
        '[data-wishlist-add], .js-wishlist-button, .wishtlist-btn, .addToWishlist, .add-to-wishlist, ' +
        '.wishlist_button, .iqitwishlist-add'
      );
      if (!btn) return;

      // Skip if button indicates item is already in wishlist (action is removal)
      var isAlreadyChecked = btn.classList.contains('active') ||
                            btn.classList.contains('checked') ||
                            btn.getAttribute('data-checked') === 'true';
      if (isAlreadyChecked) return;

      var idProd = btn.getAttribute('data-product-id') ||
                   btn.getAttribute('data-id-product') ||
                   btn.getAttribute('data-id_product') ||
                   (btn.dataset && (btn.dataset.productId || btn.dataset.idProduct));

      var idAttr = btn.getAttribute('data-product-attribute-id') ||
                   btn.getAttribute('data-id-product-attribute') ||
                   btn.getAttribute('data-id_product_attribute') ||
                   (btn.dataset && (btn.dataset.productAttributeId || btn.dataset.idProductAttribute)) || 0;

      if (!idProd && window.psGtmConfig && window.psGtmConfig.currentProduct) {
        idProd = window.psGtmConfig.currentProduct.item_id;
        if (!idAttr) idAttr = window.psGtmConfig.currentProduct.variant_id;
      }

      if (idProd) {
        setTimeout(function () {
          dispatchAddToWishlist(idProd, idAttr);
        }, 400);
      }
    }, true);

    // Capture Product Link Selection from Listings (Capture phase for instant pre-unload execution)
    document.addEventListener('click', function (e) {
      if (!e.target) return;

      var targetEl = e.target;
      // Exclude add-to-cart buttons inside listing cards
      if (targetEl.closest && targetEl.closest('.add-to-cart, [data-button-action="add-to-cart"], .quick-view')) {
        return;
      }

      var anchor = targetEl.closest ? targetEl.closest('a') : null;
      var productCard = targetEl.closest ? targetEl.closest('[data-id-product], .js-product-miniature, article.product-miniature') : null;

      var idProduct = '';
      if (productCard && productCard.getAttribute('data-id-product')) {
        idProduct = productCard.getAttribute('data-id-product');
      } else if (anchor && anchor.href) {
        var urlMatch = anchor.href.match(/(?:[\/-]|^|id_product=)(\d+)(?:-[^\/?#]+|\.html|$|[?&])/i);
        if (urlMatch && urlMatch[1]) {
          idProduct = urlMatch[1];
        }
      }

      if (idProduct && window.psGtmConfig && window.psGtmConfig.currentList) {
        handleSelectItem(idProduct);
      }
    }, true);

    // 6. Checkout Step Observers: add_shipping_info & add_payment_info (Universal PrestaShop Core & Custom Themes)
    var shippingInfoTracked = false;
    var paymentInfoTracked = false;

    function getCheckoutItems() {
      if (window.psGtmConfig && window.psGtmConfig.initialServerPayload && window.psGtmConfig.initialServerPayload.ecommerce && Array.isArray(window.psGtmConfig.initialServerPayload.ecommerce.items)) {
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
      if (shippingInfoTracked) return;
      var rawItems = getCheckoutItems();
      var normItems = EDL.normalizeItems(rawItems);
      if (normItems.length === 0) return;

      var currency = (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
      var val = EDL.calculateCartValue(normItems);

      shippingInfoTracked = true;
      EDL.pushEvent('add_shipping_info', {
        currency: currency,
        value: val,
        shipping_tier: String(carrierName || 'Standard Delivery').trim(),
        items: normItems
      });
    }

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (form && (form.id === 'js-delivery' || (form.action && form.action.indexOf('delivery') !== -1))) {
        var checkedCarrier = form.querySelector('input[name^="delivery_option"]:checked');
        var carrierLabel = '';
        if (checkedCarrier) {
          var row = checkedCarrier.closest('.delivery-option') || checkedCarrier.closest('tr') || checkedCarrier.closest('.row') || checkedCarrier.parentElement;
          if (row) {
            var labelEl = row.querySelector('.carrier-name, .delivery-option-name, label');
            if (labelEl) carrierLabel = labelEl.textContent.trim();
          }
        }
        dispatchShippingInfo(carrierLabel);
      }
    }, true);

    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('button[name="confirmDeliveryOption"], .continue[name="confirm-addresses"]') : null;
      if (btn && btn.name === 'confirmDeliveryOption') {
        var checkedCarrier = document.querySelector('input[name^="delivery_option"]:checked');
        var carrierLabel = '';
        if (checkedCarrier) {
          var row = checkedCarrier.closest('.delivery-option') || checkedCarrier.closest('tr') || checkedCarrier.closest('.row') || checkedCarrier.parentElement;
          if (row) {
            var labelEl = row.querySelector('.carrier-name, .delivery-option-name, label');
            if (labelEl) carrierLabel = labelEl.textContent.trim();
          }
        }
        dispatchShippingInfo(carrierLabel);
      }
    }, true);

    // Capture Payment Step Selection (add_payment_info)
    function dispatchPaymentInfo(paymentMethod) {
      if (paymentInfoTracked) return;
      var rawItems = getCheckoutItems();
      var normItems = EDL.normalizeItems(rawItems);
      if (normItems.length === 0) return;

      var currency = (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';
      var val = EDL.calculateCartValue(normItems);

      paymentInfoTracked = true;
      EDL.pushEvent('add_payment_info', {
        currency: currency,
        value: val,
        payment_type: String(paymentMethod || 'Online Payment').trim(),
        items: normItems
      });
    }

    document.addEventListener('change', function (e) {
      var target = e.target;
      if (target && target.name === 'payment-option' && target.checked) {
        var labelEl = document.querySelector('label[for="' + target.id + '"]') || target.closest('label');
        var method = labelEl ? labelEl.textContent.trim() : (target.dataset && target.dataset.moduleName ? target.dataset.moduleName : '');
        dispatchPaymentInfo(method);
      }
    }, true);

    // 7. Native Faceted Navigation & AJAX Catalog Pagination (ps_facetedsearch / core listing)
    window.prestashop.on('updateProductList', function (eventData) {
      // PrestaShop updates product listings dynamically via AJAX.
      // Delegated document click listeners automatically cover re-rendered miniatures.
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onPrestashopReady);
  } else {
    onPrestashopReady();
  }

})(window, document, window.EcommerceDataLayer);
