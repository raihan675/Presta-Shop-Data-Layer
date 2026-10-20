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

  function getBaseProductIdFromDOM() {
    var elem = document.querySelector('#product_page_product_id, input[name="id_product"], [data-id-product]');
    if (elem) {
      var val = elem.value || elem.getAttribute('data-id-product');
      if (val) return String(val).trim();
    }
    var details = document.querySelector('#product-details[data-product]');
    if (details) {
      try {
        var data = JSON.parse(details.getAttribute('data-product'));
        if (data && (data.id_product || data.id)) {
          return String(data.id_product || data.id).trim();
        }
      } catch (e) {}
    }
    return '';
  }

  function getVariantIdFromDOM() {
    var elem = document.querySelector('#product_attribute_id, input[name="id_product_attribute"], [data-id-product-attribute]');
    if (elem) {
      var val = elem.value || elem.getAttribute('data-id-product-attribute');
      if (val && parseInt(val, 10) > 0) return String(val).trim();
    }
    var details = document.querySelector('#product-details[data-product]');
    if (details) {
      try {
        var data = JSON.parse(details.getAttribute('data-product'));
        if (data && data.id_product_attribute && parseInt(data.id_product_attribute, 10) > 0) {
          return String(data.id_product_attribute).trim();
        }
      } catch (e) {}
    }
    return '';
  }

  function getReferenceFromDOM() {
    var refElem = document.querySelector('.product-reference span, [itemprop="sku"], .product-features .data-sheet dd');
    if (refElem) {
      var text = (refElem.innerText || refElem.getAttribute('content') || '').trim();
      if (text) return text;
    }
    var details = document.querySelector('#product-details[data-product]');
    if (details) {
      try {
        var data = JSON.parse(details.getAttribute('data-product'));
        if (data && data.reference) {
          return String(data.reference).trim();
        }
      } catch (e) {}
    }
    return '';
  }

  function getPriceFromDOM() {
    var priceElem = document.querySelector('.product-price.current-price-value, .current-price [itemprop="price"], .product-prices [itemprop="price"]');
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
    var details = document.querySelector('#product-details[data-product]');
    if (details) {
      try {
        var data = JSON.parse(details.getAttribute('data-product'));
        if (data && data.price_amount) {
          return parseFloat(data.price_amount);
        }
      } catch (e) {}
    }
    return 0.0;
  }

  function getProductNameFromDOM() {
    var titleElem = document.querySelector('h1[itemprop="name"], .product-detail-name');
    if (titleElem && titleElem.innerText) {
      return titleElem.innerText.trim();
    }
    return 'Product';
  }

  function getProductBrandFromDOM() {
    var brandElem = document.querySelector('[itemprop="brand"], .product-manufacturer a');
    return brandElem ? brandElem.innerText.trim() : '';
  }

  function getProductCategoryFromDOM() {
    var catElem = document.querySelector('.breadcrumb li:nth-last-child(2) a span, .breadcrumb li:nth-last-child(2) a');
    return catElem ? catElem.innerText.trim() : '';
  }

  function getSelectedVariantFromDOM() {
    var activeVariants = document.querySelectorAll('.product-variants-item input:checked, .product-variants-item select');
    var parts = [];
    activeVariants.forEach(function (el) {
      if (el.tagName === 'SELECT') {
        var opt = el.options[el.selectedIndex];
        if (opt && opt.text) parts.push(opt.text.trim());
      } else {
        var container = el.closest('li') || el.closest('.product-variants-item');
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
    var baseProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
    var domBaseId = getBaseProductIdFromDOM();
    var domVariantId = getVariantIdFromDOM();
    var domRef = getReferenceFromDOM();
    var domPrice = getPriceFromDOM();
    var domVariant = getSelectedVariantFromDOM();

    var baseId = (rawItem && rawItem.id_product)
      ? String(rawItem.id_product)
      : (domBaseId || (baseProd && (baseProd.base_product_id || baseProd.item_id)) || '');

    var variantId = (rawItem && rawItem.id_product_attribute)
      ? String(rawItem.id_product_attribute)
      : (domVariantId || (baseProd ? baseProd.variant_id : '') || '');

    var skuId = domRef || (rawItem && rawItem.reference) || (baseProd ? baseProd.sku_id : '') || '';
    var variantName = domVariant || (baseProd ? baseProd.item_variant : '') || '';

    var resolvedItemName = (baseProd && baseProd.item_name) ? baseProd.item_name : getProductNameFromDOM();
    var resolvedBrand = (baseProd && baseProd.item_brand) ? baseProd.item_brand : getProductBrandFromDOM();
    var resolvedCategory = (baseProd && baseProd.item_category) ? baseProd.item_category : getProductCategoryFromDOM();
    var resolvedPrice = (domPrice > 0) ? domPrice : ((baseProd && baseProd.price > 0) ? baseProd.price : (rawItem && rawItem.price ? parseFloat(rawItem.price) : 0.0));
    var resolvedQuantity = (rawItem && rawItem.quantity) ? parseInt(rawItem.quantity, 10) : 1;

    var itemIdMapping = (window.psGtmConfig && window.psGtmConfig.itemIdMapping) || 'base_id';
    var gmcMapping = (window.psGtmConfig && window.psGtmConfig.gmcIdMapping) || 'sku';
    var metaMapping = (window.psGtmConfig && window.psGtmConfig.metaIdMapping) || 'sku';

    var resolvedItemId = EDL.resolveMappedIdentifier(itemIdMapping, baseId, variantId, skuId);
    var resolvedGmcId = EDL.resolveMappedIdentifier(gmcMapping, baseId, variantId, skuId);
    var resolvedMetaId = EDL.resolveMappedIdentifier(metaMapping, baseId, variantId, skuId);

    var addedItem = {
      item_id: resolvedItemId,
      variant_id: variantId,
      sku_id: skuId,
      item_name: resolvedItemName,
      item_brand: resolvedBrand,
      item_category: resolvedCategory,
      item_variant: variantName,
      price: resolvedPrice,
      quantity: resolvedQuantity,
      item_id_gmc: resolvedGmcId,
      item_id_meta: resolvedMetaId
    };

    var normalizedItems = EDL.normalizeItems([addedItem]);
    var currency = cartCurrency || (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR';

    EDL.pushEvent('add_to_cart', {
      currency: currency,
      value: EDL.calculateCartValue(normalizedItems),
      content_type: 'product',
      meta_content_ids: [resolvedMetaId],
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
          id_product: reason.idProduct || '',
          id_product_attribute: reason.idProductAttribute || '',
          price: reason.price || 0.0,
          quantity: parseInt(reason.quantity, 10) || 1
        };
        handleAddToCart(raw, currencyCode);
      } else if (action === 'delete-from-cart' || action === 'remove-from-cart') {
        var removedItem = {
          item_id: String(reason.idProduct || ''),
          variant_id: reason.idProductAttribute ? String(reason.idProductAttribute) : '',
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

      if (!event.id_product_attribute) {
        return;
      }

      syncConfig();

      setTimeout(function () {
        var currentProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
        var domBaseId = getBaseProductIdFromDOM() || (currentProd ? (currentProd.base_product_id || currentProd.item_id) : '');
        var domVariantId = String(event.id_product_attribute || getVariantIdFromDOM() || '');
        var domPrice = getPriceFromDOM();
        var domRef = getReferenceFromDOM();
        var domVariant = getSelectedVariantFromDOM();
        var title = getProductNameFromDOM() || (currentProd ? currentProd.item_name : 'Product');
        var brand = (currentProd ? currentProd.item_brand : '') || getProductBrandFromDOM();
        var category = (currentProd ? currentProd.item_category : '') || getProductCategoryFromDOM();

        var itemIdMapping = (window.psGtmConfig && window.psGtmConfig.itemIdMapping) || 'base_id';
        var gmcMapping = (window.psGtmConfig && window.psGtmConfig.gmcIdMapping) || 'sku';
        var metaMapping = (window.psGtmConfig && window.psGtmConfig.metaIdMapping) || 'sku';

        var resolvedItemId = EDL.resolveMappedIdentifier(itemIdMapping, domBaseId, domVariantId, domRef);
        var resolvedGmcId = EDL.resolveMappedIdentifier(gmcMapping, domBaseId, domVariantId, domRef);
        var resolvedMetaId = EDL.resolveMappedIdentifier(metaMapping, domBaseId, domVariantId, domRef);

        var item = {
          item_id: resolvedItemId,
          variant_id: domVariantId,
          sku_id: domRef,
          item_name: title,
          item_brand: brand,
          item_category: category,
          item_variant: domVariant || '',
          price: domPrice > 0 ? domPrice : (currentProd ? currentProd.price : 0.0),
          quantity: 1,
          item_id_gmc: resolvedGmcId,
          item_id_meta: resolvedMetaId
        };

        // Cache active product combination in memory for subsequent add-to-cart actions
        if (window.psGtmConfig) {
          window.psGtmConfig.currentProduct = item;
        }

        var items = EDL.normalizeItems([item]);
        if (items.length > 0) {
          EDL.pushEvent('view_item', {
            currency: (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR',
            value: items[0].price,
            content_type: 'product',
            meta_content_ids: [resolvedMetaId],
            items: items
          });
        }
      }, 100);
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
