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
      } catch (e) {}

      return origSend.apply(this, arguments);
    };
  })();

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
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onPrestashopReady);
  } else {
    onPrestashopReady();
  }

})(window, document, window.EcommerceDataLayer);
