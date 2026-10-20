/**
 * PrestaShop Front-Office Adapter: Captures AJAX cart actions and dynamic DOM checkout steps.
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

  // Helper to extract reliable reference / SKU from the DOM
  function getReferenceFromDOM() {
    var refElem = document.querySelector('.product-reference span, [itemprop="sku"]');
    if (refElem) {
      var text = (refElem.innerText || refElem.getAttribute('content') || '').trim();
      if (text) return text;
    }
    return '';
  }

  // Helper to extract reliable price from the DOM
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

  function onPrestashopReady() {
    if (!window.prestashop) return;
    syncConfig();

    // 1. Intercept PrestaShop's core updateCart event (AJAX add/remove)
    window.prestashop.on('updateCart', function (event) {
      if (!event || !event.reason) return;

      syncConfig();
      var action = event.reason.action;
      var cart = event.resp ? event.resp.cart : null;
      var currencyCode = (cart && cart.currency) ? cart.currency.iso_code : ((window.psGtmConfig && window.psGtmConfig.currency) || 'EUR');

      if (action === 'add-to-cart') {
        var baseProduct = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
        var domRef = getReferenceFromDOM();
        var domPrice = getPriceFromDOM();

        var resolvedItemId = '';
        if (domRef) {
          resolvedItemId = domRef;
        } else if (baseProduct && baseProduct.item_id) {
          resolvedItemId = baseProduct.item_id;
        } else {
          resolvedItemId = event.reason.idProductAttribute ? (event.reason.idProduct + '-' + event.reason.idProductAttribute) : String(event.reason.idProduct);
        }

        var resolvedItemName = '';
        if (baseProduct && baseProduct.item_name) {
          resolvedItemName = baseProduct.item_name;
        } else {
          var titleElem = document.querySelector('h1[itemprop="name"], .product-detail-name');
          resolvedItemName = titleElem ? titleElem.innerText.trim() : 'Product';
        }

        var resolvedPrice = 0.0;
        if (baseProduct && baseProduct.price > 0) {
          resolvedPrice = baseProduct.price;
        } else if (domPrice > 0) {
          resolvedPrice = domPrice;
        } else if (event.reason.price) {
          resolvedPrice = parseFloat(event.reason.price);
        }

        var addedItem = {
          item_id: resolvedItemId,
          item_name: resolvedItemName,
          item_brand: baseProduct ? baseProduct.item_brand : '',
          item_category: baseProduct ? baseProduct.item_category : '',
          item_variant: baseProduct ? baseProduct.item_variant : '',
          price: resolvedPrice,
          quantity: parseInt(event.reason.quantity, 10) || 1
        };

        var normalizedItems = EDL.normalizeItems([addedItem]);
        EDL.pushEvent('add_to_cart', {
          currency: currencyCode,
          value: EDL.calculateCartValue(normalizedItems),
          items: normalizedItems
        });
      } else if (action === 'remove-from-cart') {
        var removedItem = {
          item_id: String(event.reason.idProduct),
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

    // 2. Intercept Variant/Attribute Changes on PDP
    // ONLY fire view_item if an actual user attribute change occurred, NOT during cart updates
    window.prestashop.on('updatedProduct', function (event) {
      if (!event || !event.product_minimal_quantity) return;

      // Guard: do not re-fire view_item if this event is triggered during an add-to-cart operation
      if (event.reason && (event.reason.action === 'add-to-cart' || event.reason.action === 'update-quantity')) {
        return;
      }

      syncConfig();
      var domPrice = getPriceFromDOM();
      var domRef = getReferenceFromDOM();
      var titleElem = document.querySelector('h1[itemprop="name"]');
      var title = titleElem ? titleElem.innerText.trim() : '';

      var item = {
        item_id: domRef || String(event.id_product_attribute ? (event.id_product + '-' + event.id_product_attribute) : event.id_product),
        item_name: title,
        price: domPrice,
        quantity: 1
      };

      var items = EDL.normalizeItems([item]);
      EDL.pushEvent('view_item', {
        value: domPrice,
        items: items
      });
    });
  }

  // 3. Checkout Form Step Listeners
  function initCheckoutListeners() {
    syncConfig();

    var deliveryForm = document.querySelector('#js-delivery');
    if (deliveryForm) {
      deliveryForm.addEventListener('submit', function () {
        syncConfig();
        var selectedCarrier = document.querySelector('input[name^="delivery_option"]:checked');
        var carrierLabel = 'Standard';
        if (selectedCarrier) {
          var labelElem = selectedCarrier.closest('.delivery-option');
          if (labelElem && labelElem.querySelector('.carrier-name')) {
            carrierLabel = labelElem.querySelector('.carrier-name').innerText.trim();
          }
        }

        EDL.pushEvent('add_shipping_info', {
          shipping_tier: carrierLabel
        });
      });
    }

    var paymentOptions = document.querySelectorAll('input[name="payment-option"]');
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
      initCheckoutListeners();
    });
  } else {
    onPrestashopReady();
    initCheckoutListeners();
  }

})(window, document, window.EcommerceDataLayer);
