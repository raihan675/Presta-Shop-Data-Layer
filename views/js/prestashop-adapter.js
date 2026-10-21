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

  function getBaseProductIdFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature, [data-id-product]');
      if (card && card.getAttribute('data-id-product')) {
        return String(card.getAttribute('data-id-product')).trim();
      }
      var form = contextElement.closest('form');
      if (form) {
        var formInput = form.querySelector('#product_page_product_id, input[name="id_product"]');
        if (formInput && formInput.value) {
          return String(formInput.value).trim();
        }
      }
    }

    // Scoped strictly to the primary product wrapper on PDP
    var mainContainer = document.querySelector('#main-product-wrapper');
    if (mainContainer) {
      var mainInput = mainContainer.querySelector('#product_page_product_id, input[name="id_product"]');
      if (mainInput && mainInput.value) {
        return String(mainInput.value).trim();
      }
    }

    var ppid = document.querySelector('#product_page_product_id');
    if (ppid && ppid.value) {
      return String(ppid.value).trim();
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

  function getVariantIdFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature, [data-id-product-attribute]');
      if (card && card.getAttribute('data-id-product-attribute')) {
        var cardAttr = card.getAttribute('data-id-product-attribute');
        if (cardAttr && parseInt(cardAttr, 10) > 0) {
          return String(cardAttr).trim();
        }
      }
      var form = contextElement.closest('form');
      if (form) {
        var formAttrInput = form.querySelector('#product_attribute_id, input[name="id_product_attribute"]');
        if (formAttrInput && formAttrInput.value && parseInt(formAttrInput.value, 10) > 0) {
          return String(formAttrInput.value).trim();
        }
      }
    }

    // Check strictly within main product wrapper or product details
    var mainContainer = document.querySelector('#main-product-wrapper');
    if (mainContainer) {
      var mainAttrInput = mainContainer.querySelector('#product_attribute_id, input[name="id_product_attribute"]');
      if (mainAttrInput && mainAttrInput.value && parseInt(mainAttrInput.value, 10) > 0) {
        return String(mainAttrInput.value).trim();
      }
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

  function getReferenceFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature');
      if (card) {
        var cardRef = card.querySelector('.product-reference a, .product-reference');
        if (cardRef) {
          var crText = (cardRef.innerText || '').trim();
          if (crText) return crText;
        }
      }
    }

    var details = document.querySelector('#product-details[data-product]');
    if (details) {
      try {
        var data = JSON.parse(details.getAttribute('data-product'));
        if (data && data.attributes) {
          for (var k in data.attributes) {
            if (data.attributes[k] && data.attributes[k].reference) {
              var attrRef = String(data.attributes[k].reference).trim();
              if (attrRef) return attrRef;
            }
          }
        }
      } catch (e) {}
    }

    var refElem = document.querySelector('#main-product-wrapper .product-reference span, #main-product-wrapper .product-reference .value, #main-product-wrapper .product-reference dd, .product-reference span, .product-reference .value');
    if (refElem) {
      var text = (refElem.innerText || refElem.getAttribute('content') || '').trim();
      if (text) return text;
    }

    if (details) {
      try {
        var dataRoot = JSON.parse(details.getAttribute('data-product'));
        if (dataRoot && dataRoot.reference) {
          return String(dataRoot.reference).trim();
        }
      } catch (e) {}
    }

    return '';
  }

  function getPriceFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature');
      if (card) {
        var cardPrice = card.querySelector('.product-price, .price');
        if (cardPrice) {
          var cVal = cardPrice.getAttribute('content');
          if (cVal && !isNaN(parseFloat(cVal))) return parseFloat(cVal);
          var tVal = (cardPrice.innerText || '').replace(/[^0-9,\.]/g, '').replace(',', '.');
          if (!isNaN(parseFloat(tVal))) return parseFloat(tVal);
        }
      }
    }

    var priceElem = document.querySelector('#main-product-wrapper .product-price.current-price-value, #main-product-wrapper .current-price [itemprop="price"], .product-price.current-price-value, .current-price [itemprop="price"]');
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

  function getProductNameFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature');
      if (card) {
        var cardTitle = card.querySelector('.product-title a, h2.product-title, .product-title, .product-name a');
        if (cardTitle && cardTitle.innerText.trim()) return cardTitle.innerText.trim();
      }
    }

    if (window.psGtmConfig && window.psGtmConfig.currentProduct && window.psGtmConfig.currentProduct.item_name) {
      var cached = String(window.psGtmConfig.currentProduct.item_name).trim();
      if (cached && cached !== 'Product') {
        return cached;
      }
    }

    var details = document.querySelector('#product-details[data-product]');
    if (details) {
      try {
        var data = JSON.parse(details.getAttribute('data-product'));
        if (data && data.name) {
          return String(data.name).trim();
        }
      } catch (e) {}
    }

    var titleElem = document.querySelector('#main-product-wrapper h1.page-title span, #main-product-wrapper h1.page-title, h1.page-title span, h1.page-title, h1[itemprop="name"], h1.product-title');
    if (titleElem) {
      var text = (titleElem.innerText || '').trim();
      if (text) return text;
    }

    if (document.title) {
      var docTitle = document.title.split('-')[0].split('|')[0].trim();
      if (docTitle) return docTitle;
    }

    return 'Product';
  }

  function getProductBrandFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature');
      if (card) {
        var cBrand = card.querySelector('.product-brand a, .product-brand');
        if (cBrand && cBrand.innerText.trim()) return cBrand.innerText.trim();
      }
    }
    var baseProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
    if (baseProd && baseProd.item_brand) {
      return baseProd.item_brand;
    }
    var brandElem = document.querySelector('#main-product-wrapper [itemprop="brand"], #main-product-wrapper .product-brand a, #main-product-wrapper .product-brand, [itemprop="brand"], .product-brand a, .product-brand, .product-manufacturer a');
    return brandElem ? brandElem.innerText.trim() : '';
  }

  function getProductCategoryFromDOM(contextElement) {
    if (contextElement) {
      var card = contextElement.closest('article.product-miniature, .js-product-miniature');
      if (card) {
        var cCat = card.querySelector('.product-category-name');
        if (cCat && cCat.innerText.trim()) return cCat.innerText.trim();
      }
    }
    var baseProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
    if (baseProd && baseProd.item_category) {
      return baseProd.item_category;
    }
    var catElem = document.querySelector('.product-category-name, .breadcrumb li:nth-last-child(2) a span, .breadcrumb li:nth-last-child(2) a');
    return catElem ? catElem.innerText.trim() : '';
  }

  function getSelectedVariantFromDOM() {
    var activeVariants = document.querySelectorAll('#main-product-wrapper .product-variants-item input:checked, #main-product-wrapper .product-variants-item select, .product-variants-item input:checked, .product-variants-item select');
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

  function extractItemFromCard(card) {
    if (!card) return null;

    var idProduct = card.getAttribute('data-id-product') || '';
    if (!idProduct) return null;

    var idAttr = card.getAttribute('data-id-product-attribute') || '';
    var titleElem = card.querySelector('.product-title a, h2.product-title, .product-title, .product-name a');
    var title = titleElem ? titleElem.innerText.trim() : 'Product';

    var priceElem = card.querySelector('.product-price, .price');
    var price = 0.0;
    if (priceElem) {
      var cVal = priceElem.getAttribute('content');
      if (cVal && !isNaN(parseFloat(cVal))) {
        price = parseFloat(cVal);
      } else {
        var tVal = (priceElem.innerText || '').replace(/[^0-9,\.]/g, '').replace(',', '.');
        if (!isNaN(parseFloat(tVal))) price = parseFloat(tVal);
      }
    }

    var brandElem = card.querySelector('.product-brand a, .product-brand');
    var brand = brandElem ? brandElem.innerText.trim() : '';

    var catElem = card.querySelector('.product-category-name');
    var cat = catElem ? catElem.innerText.trim() : '';

    var refElem = card.querySelector('.product-reference a, .product-reference');
    var sku = refElem ? refElem.innerText.trim() : '';

    return {
      item_id: String(idProduct),
      variant_id: (idAttr && parseInt(idAttr, 10) > 0) ? String(idAttr) : '',
      sku_id: sku,
      item_name: title,
      item_brand: brand,
      item_category: cat,
      item_variant: '',
      price: price,
      quantity: 1
    };
  }

  var lastAddToCartTimestamp = 0;
  function handleAddToCart(rawItem, cartCurrency, contextElement) {
    var now = new Date().getTime();
    if (now - lastAddToCartTimestamp < 800) {
      return;
    }
    lastAddToCartTimestamp = now;

    syncConfig();
    var baseProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
    var pdpId = baseProd ? String(baseProd.base_product_id || baseProd.item_id || '') : '';
    var rawId = (rawItem && rawItem.id_product) ? String(rawItem.id_product).trim() : '';
    
    // Check if adding the main PDP product
    var isMainPdp = Boolean(pdpId && (!rawId || rawId === pdpId) && (!contextElement || !contextElement.closest('article.product-miniature, .js-product-miniature')));

    var baseId = rawId || (isMainPdp ? pdpId : getBaseProductIdFromDOM(contextElement));
    var rawAttr = (rawItem && rawItem.id_product_attribute !== undefined && rawItem.id_product_attribute !== null)
      ? String(rawItem.id_product_attribute).trim()
      : '';

    // Determine variant ID with strict scoping
    var variantId = '';
    if (rawAttr && parseInt(rawAttr, 10) > 0) {
      variantId = rawAttr;
    } else if (rawAttr === '' && isMainPdp && baseProd && baseProd.variant_id) {
      variantId = String(baseProd.variant_id);
    } else if (!rawItem) {
      variantId = getVariantIdFromDOM(contextElement);
    }

    var cardItem = null;
    if (!isMainPdp && contextElement) {
      var matchingCard = contextElement.closest('article.product-miniature, .js-product-miniature');
      if (matchingCard) {
        cardItem = extractItemFromCard(matchingCard);
      }
    }

    // Lookup combination if valid
    var combo = null;
    if (variantId && window.psGtmConfig && window.psGtmConfig.productCombinations && window.psGtmConfig.productCombinations[variantId]) {
      combo = window.psGtmConfig.productCombinations[variantId];
    }

    var finalBaseId = isMainPdp ? (pdpId || baseId) : ((combo && combo.item_id) ? combo.item_id : (cardItem ? cardItem.item_id : baseId));
    var finalVariantId = isMainPdp ? (variantId ? variantId : (baseProd ? (baseProd.variant_id || '') : '')) : ((combo && combo.variant_id) ? combo.variant_id : variantId);

    var domRef = getReferenceFromDOM(contextElement);
    var domPrice = getPriceFromDOM(contextElement);
    var domVariant = getSelectedVariantFromDOM();

    var finalSkuId = (combo && combo.sku_id)
      ? combo.sku_id
      : (isMainPdp && baseProd && baseProd.sku_id ? baseProd.sku_id : (domRef || (rawItem && rawItem.reference) || (cardItem ? cardItem.sku_id : '') || ''));

    var finalVariantName = (combo && combo.item_variant)
      ? combo.item_variant
      : (isMainPdp && baseProd ? (baseProd.item_variant || domVariant || '') : (domVariant || ''));

    var resolvedItemName = (combo && combo.item_name)
      ? combo.item_name
      : (isMainPdp && baseProd && baseProd.item_name ? baseProd.item_name : ((cardItem && cardItem.item_name) ? cardItem.item_name : (getProductNameFromDOM(contextElement) || 'Product')));

    var resolvedBrand = (cardItem && cardItem.item_brand)
      ? cardItem.item_brand
      : (isMainPdp && baseProd && baseProd.item_brand ? baseProd.item_brand : (getProductBrandFromDOM(contextElement) || ''));

    var resolvedCategory = (cardItem && cardItem.item_category)
      ? cardItem.item_category
      : (isMainPdp && baseProd && baseProd.item_category ? baseProd.item_category : (getProductCategoryFromDOM(contextElement) || ''));

    var resolvedPrice = (combo && combo.price > 0)
      ? combo.price
      : (isMainPdp && baseProd && baseProd.price > 0 ? baseProd.price : (domPrice > 0 ? domPrice : (cardItem && cardItem.price > 0 ? cardItem.price : (rawItem && rawItem.price ? parseFloat(rawItem.price) : 0.0))));

    var resolvedQuantity = (rawItem && rawItem.quantity) ? parseInt(rawItem.quantity, 10) : 1;

    var addedItem = {
      item_id: String(finalBaseId),
      variant_id: String(finalVariantId),
      sku_id: String(finalSkuId),
      item_name: resolvedItemName,
      item_brand: resolvedBrand,
      item_category: resolvedCategory,
      item_variant: finalVariantName,
      price: resolvedPrice,
      quantity: resolvedQuantity
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
        var addedIdProduct = reason.idProduct || reason.id_product || '';
        var addedIdAttr = (reason.idProductAttribute !== undefined) 
          ? reason.idProductAttribute 
          : (reason.id_product_attribute !== undefined ? reason.id_product_attribute : '');

        var matchedProduct = null;
        if (cart && Array.isArray(cart.products) && addedIdProduct) {
          for (var i = 0; i < cart.products.length; i++) {
            var cp = cart.products[i];
            if (String(cp.id_product) === String(addedIdProduct) &&
                (!addedIdAttr || String(cp.id_product_attribute) === String(addedIdAttr))) {
              matchedProduct = cp;
              break;
            }
          }
        }

        // If matched from server cart response, use authoritative cart database product record
        if (matchedProduct) {
          var resolvedPrice = parseFloat(matchedProduct.price_wt || matchedProduct.price_amount || matchedProduct.price || 0.0);
          var brand = String(matchedProduct.manufacturer_name || '');
          if (!brand && window.psGtmConfig && window.psGtmConfig.currentProduct && String(window.psGtmConfig.currentProduct.item_id) === String(matchedProduct.id_product)) {
            brand = window.psGtmConfig.currentProduct.item_brand || '';
          }

          var item = {
            item_id: String(matchedProduct.id_product),
            variant_id: (matchedProduct.id_product_attribute && parseInt(matchedProduct.id_product_attribute, 10) > 0) ? String(matchedProduct.id_product_attribute) : '',
            sku_id: String(matchedProduct.reference || ''),
            item_name: String(matchedProduct.name || ''),
            item_brand: brand,
            item_category: String(matchedProduct.category_name || matchedProduct.category || ''),
            item_variant: String(matchedProduct.attributes || ''),
            price: Math.round(resolvedPrice * 100) / 100,
            quantity: parseInt(reason.quantity, 10) || parseInt(matchedProduct.quantity_wanted, 10) || 1
          };

          var normItems = EDL.normalizeItems([item]);
          if (normItems.length > 0) {
            lastAddToCartTimestamp = new Date().getTime();
            EDL.pushEvent('add_to_cart', {
              currency: currencyCode,
              value: EDL.calculateCartValue(normItems),
              items: normItems
            });
            return;
          }
        }

        var raw = {
          id_product: addedIdProduct,
          id_product_attribute: addedIdAttr,
          price: reason.price || 0.0,
          quantity: parseInt(reason.quantity, 10) || 1
        };
        handleAddToCart(raw, currencyCode);
      } else if (action === 'delete-from-cart' || action === 'remove-from-cart') {
        var removedItem = {
          item_id: String(reason.idProduct || reason.id_product || ''),
          variant_id: (reason.idProductAttribute || reason.id_product_attribute) ? String(reason.idProductAttribute || reason.id_product_attribute) : '',
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

      var variantId = String(event.id_product_attribute || getVariantIdFromDOM() || '');
      if (!variantId || variantId === '0') {
        return;
      }

      syncConfig();

      setTimeout(function () {
        var currentProd = (window.psGtmConfig && window.psGtmConfig.currentProduct) ? window.psGtmConfig.currentProduct : null;
        var domBaseId = getBaseProductIdFromDOM() || (currentProd ? (currentProd.base_product_id || currentProd.item_id) : '');

        var combo = (window.psGtmConfig && window.psGtmConfig.productCombinations && window.psGtmConfig.productCombinations[variantId])
          ? window.psGtmConfig.productCombinations[variantId]
          : null;

        var finalBaseId = (combo && combo.item_id) ? combo.item_id : domBaseId;
        var title = (combo && combo.item_name) ? combo.item_name : getProductNameFromDOM();
        var brand = (combo && combo.item_brand) ? combo.item_brand : (getProductBrandFromDOM() || (currentProd ? currentProd.item_brand : ''));
        var category = (combo && combo.item_category) ? combo.item_category : (getProductCategoryFromDOM() || (currentProd ? currentProd.item_category : ''));

        var domPrice = getPriceFromDOM();
        var domRef = getReferenceFromDOM();
        var domVariant = getSelectedVariantFromDOM();

        var resolvedSku = (combo && combo.sku_id) ? combo.sku_id : (domRef || (currentProd ? currentProd.sku_id : ''));
        var resolvedVariantName = (combo && combo.item_variant) ? combo.item_variant : (domVariant || '');
        var resolvedPrice = (combo && combo.price > 0) ? combo.price : (domPrice > 0 ? domPrice : (currentProd ? currentProd.price : 0.0));

        var item = {
          item_id: String(finalBaseId),
          variant_id: String(variantId),
          sku_id: String(resolvedSku),
          item_name: title,
          item_brand: brand,
          item_category: category,
          item_variant: resolvedVariantName,
          price: resolvedPrice,
          quantity: 1
        };

        if (window.psGtmConfig) {
          window.psGtmConfig.currentProduct = item;
        }

        var items = EDL.normalizeItems([item]);
        if (items.length > 0) {
          EDL.pushEvent('view_item', {
            currency: (window.psGtmConfig && window.psGtmConfig.currency) || 'EUR',
            value: items[0].price,
            items: items
          });
        }
      }, 150);
    });

    window.prestashop.on('quickviewOpened', function () {
      setTimeout(function () {
        var baseId = getBaseProductIdFromDOM();
        var variantId = getVariantIdFromDOM();
        var title = getProductNameFromDOM();
        var sku = getReferenceFromDOM();
        var price = getPriceFromDOM();
        var brand = getProductBrandFromDOM();
        var category = getProductCategoryFromDOM();
        var variant = getSelectedVariantFromDOM();

        if (baseId) {
          var item = {
            item_id: String(baseId),
            variant_id: String(variantId),
            sku_id: String(sku),
            item_name: title,
            item_brand: brand,
            item_category: category,
            item_variant: variant,
            price: price,
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
        }
      }, 300);
    });
  }

  function initListingListeners() {
    var cards = document.querySelectorAll('article.product-miniature, .js-product-miniature');
    if (!cards || cards.length === 0) return;

    if (document.querySelector('#product-details') && document.querySelector('#product_page_product_id')) {
      return;
    }

    var items = [];
    cards.forEach(function (card, index) {
      var item = extractItemFromCard(card);
      if (item && item.item_id) {
        item.index = index + 1;
        items.push(item);
      }

      card.addEventListener('click', function (e) {
        if (e.target.closest('.add-to-cart, [data-button-action="add-to-cart"], .btn-iqitwishlist-add, .js-quick-view-iqit')) {
          return;
        }
        var clicked = extractItemFromCard(card);
        if (clicked) {
          clicked.index = index + 1;
          var norm = EDL.normalizeItems([clicked]);
          if (norm.length > 0) {
            EDL.pushEvent('select_item', {
              item_list_id: window.location.pathname,
              item_list_name: document.title.split('-')[0].trim(),
              items: norm
            });
          }
        }
      });
    });

    if (items.length > 0) {
      var normalized = EDL.normalizeItems(items);
      EDL.pushEvent('view_item_list', {
        item_list_id: window.location.pathname,
        item_list_name: document.title.split('-')[0].trim(),
        items: normalized
      });
    }
  }

  function initAddToCartButtonFallback() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-button-action="add-to-cart"], .add-to-cart, button.ajax_add_to_cart_button');
      if (btn) {
        var card = btn.closest('article.product-miniature, .js-product-miniature');
        var form = btn.closest('form');
        var qtyInput = form ? form.querySelector('input[name="qty"], #quantity_wanted') : null;
        var qty = qtyInput ? parseInt(qtyInput.value, 10) : 1;
        var idProd = (form && form.querySelector('input[name="id_product"]')) 
          ? form.querySelector('input[name="id_product"]').value 
          : (card ? card.getAttribute('data-id-product') : '');
        var idAttr = (form && form.querySelector('input[name="id_product_attribute"]')) 
          ? form.querySelector('input[name="id_product_attribute"]').value 
          : (card ? card.getAttribute('data-id-product-attribute') : '');

        setTimeout(function () {
          var now = new Date().getTime();
          if (now - lastAddToCartTimestamp < 800) {
            return;
          }
          handleAddToCart({
            id_product: idProd,
            id_product_attribute: idAttr,
            quantity: qty
          }, null, btn);
        }, 500);
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
      initListingListeners();
    });
  } else {
    onPrestashopReady();
    initAddToCartButtonFallback();
    initCheckoutListeners();
    initListingListeners();
  }

})(window, document, window.EcommerceDataLayer);
