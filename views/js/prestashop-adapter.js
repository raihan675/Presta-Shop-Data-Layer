/**
 * PrestaShop Front-Office Adapter: Robust capture of AJAX cart actions and dynamic DOM events.
 * Compatible with Classic theme, Warehouse theme, and One-Page Checkout modules.
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

  function getReferenceFromDOM() {
    var refElem = document.querySelector('.product-reference span, [itemprop="sku"], .product-features .data-sheet dd');
    if (refElem) {
      var text = (refElem.innerText || refElem.getAttribute('content') || '').trim();
      if (text) return text;
    }
    return '';
  }

  function getPriceFromDOM() {
    var priceElem = document.querySelector('.current-price [itemprop="price"], .product-prices [itemprop="price"], .current-price-value');
    if (priceElem) {
      var contentVal = priceElem.getAttribute('content');
      if (contentVal && !isNaN(parseFloat(contentVal))) {
        return parseFloat(contentVal);
      }
      var textVal = (priceElem.innerText || '').replace(/[^0-9,\.]/g, '').replace(',', '.');
      if (!isNaN(parseFloat(textVal))) {
        return parseFloat(textVal);
      }
    }
    return 0.0;
  }

  function getProductNameFromDOM() {
    var titleElem = document.querySelector('h1[itemprop="name"], .product-detail-name');
    return titleElem ? titleElem.innerText.trim() : 'Product';
  }

  function getSelectedVariantFromDOM() {
    var activeVariants = document.querySelectorAll('.product-variants-item input:checked, .product-variants-item select');
    var parts = [];
    activeVariants.forEach(function (el) {
      if (el.tagName === 'SELECT') {
        var opt = el.options[el.selectedIndex];
        if (opt && opt.text) parts.push(opt.text.trim());
      } else {
        var container = el.closest('li');
        var label = container ? (container.getAttribute('title') || (container.querySelector('.attribute-name') ? container.querySelector('.attribute-name').innerText : '')) : '';
        if (label) parts.push(label.trim());
      }
    });
    return parts.join(' / ');
  }

  var lastAddToCartTimestamp = 0;
  function handleAddToCart(rawItem, cartCurrency) {
    var now = new Date().getTime();
    if (now - lastAddToCartTimestamp < 800) {
      return;
    }
    lastAddToCartTimestamp = now;

    syncConfig();
    var baseProduct = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
    var domRef = getReferenceFromDOM();
    var domPrice = getPriceFromDOM();
    var domVariant = getSelectedVariantFromDOM();

    var resolvedItemId = '';
    if (domRef) {
      resolvedItemId = domRef;
    } else if (baseProduct && baseProduct.item_id) {
      resolvedItemId = baseProduct.item_id;
    } else if (rawItem && rawItem.item_id) {
      resolvedItemId = String(rawItem.item_id);
    } else {
      resolvedItemId = 'unknown';
    }

    var resolvedItemName = (baseProduct && baseProduct.item_name) ? baseProduct.item_name : getProductNameFromDOM();
    var resolvedPrice = (domPrice > 0) ? domPrice : ((baseProduct && baseProduct.price > 0) ? baseProduct.price : (rawItem && rawItem.price ? parseFloat(rawItem.price) : 0.0));
    var resolvedQuantity = (rawItem && rawItem.quantity) ? parseInt(rawItem.quantity, 10) : 1;
    var resolvedVariant = domVariant || (baseProduct ? baseProduct.item_variant : '');

    var addedItem = {
      item_id: resolvedItemId,
      item_name: resolvedItemName,
      item_brand: baseProduct ? baseProduct.item_brand : '',
      item_category: baseProduct ? baseProduct.item_category : '',
      item_variant: resolvedVariant || '',
      price: resolvedPrice,
      quantity: resolvedQuantity || 1
    };

    var normalizedItems = EDL.normalizeItems([addedItem]);
    var currency = cartCurrency || (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';

    EDL.pushEvent('add_to_cart', {
      currency: currency,
      value: EDL.calculateCartValue(normalizedItems),
      items: normalizedItems
    });
  }

  function onPrestashopReady() {
    if (!window.prestashop) return;
    syncConfig();

    window.prestashop.on('updateCart', function (event) {
      if (!event) return;

      var reason = event.reason || {};
      var action = reason.action || reason.linkAction || (event.resp && event.resp.cartAction) || '';
      var cart = event.resp ? event.resp.cart : null;
      var currencyCode = (cart && cart.currency) ? cart.currency.iso_code : ((window.psGtmConfig && window.psGtmConfig.currency) || 'EUR');

      if (action === 'add-to-cart') {
        var raw = {
          item_id: reason.idProductAttribute ? (reason.idProduct + '-' + reason.idProductAttribute) : String(reason.idProduct || ''),
          price: reason.price || 0.0,
          quantity: parseInt(reason.quantity, 10) || 1
        };
        handleAddToCart(raw, currencyCode);
      } else if (action === 'delete-from-cart' || action === 'remove-from-cart') {
        var removedItem = {
          item_id: String(reason.idProduct || ''),
          item_name: 'Product',
          price: 0.0,
          quantity: 1
        };
        EDL.pushEvent('remove_from_cart', {
          currency: currencyCode,
          items: EDL.normalizeItems([removedItem])
        });
      }
    });

    window.prestashop.on('updatedProduct', function (event) {
      if (!event) return;

      var now = new Date().getTime();
      if (now - lastAddToCartTimestamp < 1500) {
        return;
      }

      var currentProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
      if (!event.id_product_attribute) {
        return;
      }

      syncConfig();
      var domPrice = getPriceFromDOM();
      var domRef = getReferenceFromDOM();
      var domVariant = getSelectedVariantFromDOM();
      var title = getProductNameFromDOM();

      var item = {
        item_id: domRef || (currentProd ? currentProd.item_id : String(event.id_product_attribute)),
        item_name: title,
        item_brand: currentProd ? currentProd.item_brand : '',
        item_category: currentProd ? currentProd.item_category : '',
        item_variant: domVariant || '',
        price: domPrice > 0 ? domPrice : (currentProd ? currentProd.price : 0.0),
        quantity: 1
      };

      var items = EDL.normalizeItems([item]);
      if (items.length > 0) {
        EDL.pushEvent('view_item', {
          currency: (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR',
          value: items[0].price,
          items: items
        });
      }
    });
  }

  function initAddToCartButtonFallback() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-button-action="add-to-cart"], .add-to-cart, button.ajax_add_to_cart_button');
      if (btn) {
        var form = btn.closest('form');
        var qtyInput = form ? form.querySelector('input[name="qty"], #quantity_wanted') : null;
        var qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;

        setTimeout(function () {
          handleAddToCart({ quantity: qty });
        }, 300);
      }
    }, true);
  }

  function initCheckoutListeners() {
    syncConfig();

    var deliveryForm = document.querySelector('#js-delivery, #form_ets_onepagecheckout');
    if (deliveryForm) {
      deliveryForm.addEventListener('submit', function () {
        syncConfig();
        var selectedCarrier = document.querySelector('input[name^="delivery_option"]:checked');
        var carrierLabel = 'Standard';
        if (selectedCarrier) {
          var labelElem = selectedCarrier.closest('.delivery-option, .carrier-item');
          if (labelElem && labelElem.querySelector('.carrier-name')) {
            carrierLabel = labelElem.querySelector('.carrier-name').innerText.trim();
          }
        }

        EDL.pushEvent('add_shipping_info', {
          shipping_tier: carrierLabel
        });
      });
    }

    var paymentOptions = document.querySelectorAll('input[name="payment-option"], input[name="payment_method"]');
    paymentOptions.forEach(function (radio) {
      radio.addEventListener('change', function () {
        syncConfig();
        var selectedMethod = this.getAttribute('data-module-name') || this.id;
        EDL.pushEvent('add_payment_info', {
          payment_type: selectedMethod
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      onPrestashopReady();
      initAddToCartButtonFallback();
      initCheckoutListeners();
    });
  } else {
    onPrestashopReady();
    initAddToCartButtonFallback();
    initCheckoutListeners();
  }

})(window, document, window.EcommerceDataLayer);
