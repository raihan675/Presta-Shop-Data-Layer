/**
 * PrestaShop Front-Office Adapter: Captures AJAX cart actions and dynamic DOM checkout steps.
 */
(function (window, document, EDL) {
  'use strict';

  if (!EDL) return;

  function onPrestashopReady() {
    if (!window.prestashop) return;

    // 1. Intercept PrestaShop's core updateCart event (AJAX add/remove)
    window.prestashop.on('updateCart', function (event) {
      if (!event || !event.reason) return;

      var action = event.reason.action;
      var cart = event.resp ? event.resp.cart : null;
      var currencyCode = (cart && cart.currency) ? cart.currency.iso_code : 'EUR';

      if (action === 'add-to-cart') {
        var addedItem = {
          item_id: event.reason.idProductAttribute ? (event.reason.idProduct + '-' + event.reason.idProductAttribute) : String(event.reason.idProduct),
          item_name: document.querySelector('h1[itemprop="name"]') ? document.querySelector('h1[itemprop="name"]').innerText.trim() : 'Product',
          price: event.reason.price || (document.querySelector('.current-price [itemprop="price"]') ? parseFloat(document.querySelector('.current-price [itemprop="price"]').getAttribute('content')) : 0.0),
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

    // 2. Intercept Dynamic Product Attribute Changes on PDP
    window.prestashop.on('updatedProduct', function (event) {
      if (!event || !event.product_minimal_quantity) return;

      var priceElem = document.querySelector('.current-price [itemprop="price"]');
      var price = priceElem ? parseFloat(priceElem.getAttribute('content')) : 0.0;
      var titleElem = document.querySelector('h1[itemprop="name"]');
      var title = titleElem ? titleElem.innerText.trim() : '';

      var item = {
        item_id: String(event.id_product_attribute ? (event.id_product + '-' + event.id_product_attribute) : event.id_product),
        item_name: title,
        price: price,
        quantity: 1
      };

      var items = EDL.normalizeItems([item]);
      EDL.pushEvent('view_item', {
        value: price,
        items: items
      });
    });
  }

  // 3. Checkout Form Step Listeners
  function initCheckoutListeners() {
    // Shipping step
    var deliveryForm = document.querySelector('#js-delivery');
    if (deliveryForm) {
      deliveryForm.addEventListener('submit', function () {
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

    // Payment step
    var paymentOptions = document.querySelectorAll('input[name="payment-option"]');
    paymentOptions.forEach(function (radio) {
      radio.addEventListener('change', function () {
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
