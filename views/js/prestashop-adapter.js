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

    // 2. Check local memory cache
    if (resolvedProductCache[cacheKey]) {
      var cached = Object.assign({}, resolvedProductCache[cacheKey]);
      cached.quantity = qty;
      callback(cached);
      return;
    }

    // 3. Query native backend AJAX FrontController
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
  // Authoritative in-memory cart quantity state (100% backend & cart object driven, ZERO DOM)
  var cartQuantities = {};

  function initCartState() {
    if (window.psGtmConfig && window.psGtmConfig.cartProducts && typeof window.psGtmConfig.cartProducts === 'object') {
      for (var k in window.psGtmConfig.cartProducts) {
        if (window.psGtmConfig.cartProducts.hasOwnProperty(k)) {
          cartQuantities[String(k)] = parseInt(window.psGtmConfig.cartProducts[k], 10) || 0;
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

      var reason = event.reason || {};
      var action = reason.action || reason.linkAction || (event.resp && event.resp.cartAction) || '';
      var cart = event.resp ? event.resp.cart : null;
      var currencyCode = (cart && cart.currency) ? cart.currency.iso_code : ((window.psGtmConfig && window.psGtmConfig.currency) || 'EUR');

      if (action === 'add-to-cart') {
        var now = new Date().getTime();
        if (now - lastAddToCartTime < 800) return;
        lastAddToCartTime = now;

        var addedIdProduct = reason.idProduct || reason.id_product || (event.resp && (event.resp.id_product || event.resp.idProduct)) || '';
        if (!addedIdProduct && window.psGtmConfig && window.psGtmConfig.currentProduct) {
          addedIdProduct = window.psGtmConfig.currentProduct.item_id;
        }

        var rawAttr = (reason.idProductAttribute !== undefined && reason.idProductAttribute !== null)
          ? reason.idProductAttribute
          : (reason.id_product_attribute !== undefined ? reason.id_product_attribute : (event.resp && (event.resp.id_product_attribute !== undefined ? event.resp.id_product_attribute : event.resp.idProductAttribute)));
        var addedIdAttr = (rawAttr && parseInt(rawAttr, 10) > 0) ? String(rawAttr) : '';

        // First attempt to match from server cart response (pure JSON from CartController)
        var matchedProduct = null;
        if (cart && Array.isArray(cart.products) && addedIdProduct) {
          for (var i = 0; i < cart.products.length; i++) {
            var cp = cart.products[i];
            var cpAttr = (cp.id_product_attribute && parseInt(cp.id_product_attribute, 10) > 0) ? String(cp.id_product_attribute) : '';
            if (String(cp.id_product) === String(addedIdProduct) && cpAttr === addedIdAttr) {
              matchedProduct = cp;
              break;
            }
          }
        }

        // Authoritative Quantity Resolution (100% Backend & Cart Object Driven - ZERO DOM)
        var cartKey = String(addedIdProduct) + '_' + String(addedIdAttr || '0');
        var previousQty = (cartQuantities[cartKey] !== undefined) ? cartQuantities[cartKey] : 0;
        var currentCartQty = matchedProduct ? (parseInt(matchedProduct.quantity || matchedProduct.cart_quantity, 10) || 0) : 0;
        var deltaQty = currentCartQty > previousQty ? (currentCartQty - previousQty) : 0;

        var qty = 1;
        if (event.resp && event.resp.quantity !== undefined && parseInt(event.resp.quantity, 10) > 0) {
          qty = parseInt(event.resp.quantity, 10);
        } else if (event.resp && event.resp.qty !== undefined && parseInt(event.resp.qty, 10) > 0) {
          qty = parseInt(event.resp.qty, 10);
        } else if (deltaQty > 0) {
          qty = deltaQty;
        } else if (reason.quantity !== undefined && parseInt(reason.quantity, 10) > 0) {
          qty = parseInt(reason.quantity, 10);
        } else if (reason.qty !== undefined && parseInt(reason.qty, 10) > 0) {
          qty = parseInt(reason.qty, 10);
        } else if (currentCartQty > 0 && previousQty === 0) {
          qty = currentCartQty;
        }

        // Synchronize in-memory cart registry from authoritative server cart response
        if (cart && Array.isArray(cart.products)) {
          var updatedState = {};
          for (var c = 0; c < cart.products.length; c++) {
            var itemInCart = cart.products[c];
            var k = String(itemInCart.id_product) + '_' + String(itemInCart.id_product_attribute || '0');
            updatedState[k] = parseInt(itemInCart.quantity || itemInCart.cart_quantity, 10) || 0;
          }
          cartQuantities = updatedState;
        }

        if (matchedProduct) {
          var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;
          var rawPrice = parseFloat(matchedProduct.price_wt || matchedProduct.price_amount || matchedProduct.price || 0.0);
          var resolvedPrice = EDL.roundHalfUp ? EDL.roundHalfUp(rawPrice, prec) : (Math.round((rawPrice + Number.EPSILON) * Math.pow(10, prec)) / Math.pow(10, prec));
          var brand = String(matchedProduct.manufacturer_name || '');
          if (!brand && window.psGtmConfig && window.psGtmConfig.currentProduct && String(window.psGtmConfig.currentProduct.item_id) === String(matchedProduct.id_product)) {
            brand = window.psGtmConfig.currentProduct.item_brand || '';
          }

          // Authoritative Category Resolution
          var catName = '';
          if (window.psGtmConfig && window.psGtmConfig.currentProduct && String(window.psGtmConfig.currentProduct.item_id) === String(matchedProduct.id_product)) {
            catName = window.psGtmConfig.currentProduct.item_category || '';
          }
          if (!catName) {
            catName = String(matchedProduct.category_name || matchedProduct.category || '');
          }

          // Authoritative Variant String Resolution
          var variantStr = '';
          if (addedIdAttr && window.psGtmConfig && window.psGtmConfig.productCombinations && window.psGtmConfig.productCombinations[addedIdAttr]) {
            variantStr = window.psGtmConfig.productCombinations[addedIdAttr].item_variant || '';
          } else if (addedIdAttr && window.psGtmConfig && window.psGtmConfig.currentProduct && String(window.psGtmConfig.currentProduct.variant_id) === addedIdAttr) {
            variantStr = window.psGtmConfig.currentProduct.item_variant || '';
          } else if (EDL.formatVariant) {
            variantStr = EDL.formatVariant(matchedProduct.attributes || matchedProduct.attribute_string || matchedProduct.attributes_small || '');
          }

          var cartItem = {
            item_id: String(matchedProduct.id_product),
            variant_id: (matchedProduct.id_product_attribute && parseInt(matchedProduct.id_product_attribute, 10) > 0) ? String(matchedProduct.id_product_attribute) : '',
            sku_id: String(matchedProduct.reference || ''),
            item_name: String(matchedProduct.name || ''),
            item_brand: brand,
            item_category: catName,
            item_variant: variantStr,
            price: resolvedPrice,
            quantity: qty
          };

          // Dispatch immediately from authoritative cart data
          var normItems = EDL.normalizeItems([cartItem], prec);
          if (normItems.length > 0) {
            EDL.pushEvent('add_to_cart', {
              currency: currencyCode,
              value: EDL.calculateCartValue(normItems, prec),
              items: normItems
            });
            return;
          }
        }

        // Authoritatively resolve via backend database resolver
        resolveBackendProduct(addedIdProduct, addedIdAttr, qty, function (resolvedItem) {
          if (!resolvedItem && matchedProduct) {
            resolvedItem = cartItem;
          }
          if (resolvedItem) {
            var prec = (window.psGtmConfig && window.psGtmConfig.currencyPrecision !== undefined) ? parseInt(window.psGtmConfig.currencyPrecision, 10) : 2;
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

      } else if (action === 'delete-from-cart' || action === 'remove-from-cart') {
        var removedIdProduct = reason.idProduct || reason.id_product || '';
        var rawRemovedAttr = reason.idProductAttribute || reason.id_product_attribute || '';
        var normRemovedAttr = (rawRemovedAttr && parseInt(rawRemovedAttr, 10) > 0) ? String(rawRemovedAttr) : '';

        resolveBackendProduct(removedIdProduct, normRemovedAttr, 1, function (resolvedItem) {
          var item = resolvedItem ? resolvedItem : {
            item_id: String(removedIdProduct),
            variant_id: normRemovedAttr,
            item_name: 'Product',
            price: 0.0,
            quantity: 1
          };

          EDL.pushEvent('remove_from_cart', {
            currency: currencyCode,
            items: EDL.normalizeItems([item])
          });
        });
      }
    });

    // 2. Native Combination Switch on PDP via PrestaShop Core Event Bus
    window.prestashop.on('updatedProduct', function (event) {
      if (!event || !event.id_product_attribute || event.id_product_attribute === '0') {
        return;
      }

      var now = new Date().getTime();
      if (now - lastAddToCartTime < 1500) {
        return;
      }

      var idAttr = String(event.id_product_attribute);
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onPrestashopReady);
  } else {
    onPrestashopReady();
  }

})(window, document, window.EcommerceDataLayer);
