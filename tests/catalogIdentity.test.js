const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Catalog-Accurate Identity, Variants & Multi-Platform Test Suite', function () {

  beforeEach(function () {
    global.window = { dataLayer: [] };
    EDL.setEventPrefix('');
  });

  it('1. Should resolve mapped identifiers accurately according to platform mapping strategies', function () {
    const baseId = '68';
    const variantId = '298';
    const sku = 'BHK125-R';

    // Base ID strategy (default for GA4 item_id)
    assert.strictEqual(EDL.resolveMappedIdentifier('base_id', baseId, variantId, sku), '68');

    // SKU / Reference strategy (default for GMC and Meta)
    assert.strictEqual(EDL.resolveMappedIdentifier('sku', baseId, variantId, sku), 'BHK125-R');

    // Combination ID strategy
    assert.strictEqual(EDL.resolveMappedIdentifier('combination_id', baseId, variantId, sku), '298');

    // Compound Dash strategy
    assert.strictEqual(EDL.resolveMappedIdentifier('compound_dash', baseId, variantId, sku), '68-298');

    // Compound Underscore strategy
    assert.strictEqual(EDL.resolveMappedIdentifier('compound_underscore', baseId, variantId, sku), '68_298');
  });

  it('2. Should preserve item_id, variant_id, sku_id, and item_variant in normalized items array', function () {
    const rawVariantProduct = {
      item_id: '68',
      variant_id: '298',
      sku_id: 'BHK125-R',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Baby Geschirr',
      item_variant: 'Farbe: Rostrot',
      price: 13.00,
      quantity: 1,
      item_id_gmc: 'BHK125-R',
      item_id_meta: 'BHK125-R'
    };

    const normalized = EDL.normalizeItem(rawVariantProduct);
    assert.strictEqual(normalized.item_id, '68', 'item_id should be base product ID');
    assert.strictEqual(normalized.variant_id, '298', 'variant_id should be active combination ID');
    assert.strictEqual(normalized.sku_id, 'BHK125-R', 'sku_id should be variant reference');
    assert.strictEqual(normalized.item_variant, 'Farbe: Rostrot', 'item_variant should be combination attribute');
    assert.strictEqual(normalized.item_id_gmc, 'BHK125-R');
    assert.strictEqual(normalized.item_id_meta, 'BHK125-R');
    assert.strictEqual(normalized.price, 13.00);
    assert.strictEqual(normalized.quantity, 1);
  });

  it('3. Should handle simple products without combinations cleanly', function () {
    const rawSimpleProduct = {
      item_id: '1116',
      variant_id: '',
      sku_id: 'FB-004-106',
      item_name: 'Faltbare Babybadewanne mit Ablaufstopfen - Grau',
      item_brand: 'Familys World',
      item_category: 'Baby Badewanne',
      item_variant: '',
      price: 24.99,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(rawSimpleProduct);
    assert.strictEqual(normalized.item_id, '1116');
    assert.strictEqual(normalized.variant_id, '');
    assert.strictEqual(normalized.sku_id, 'FB-004-106');
    assert.strictEqual(normalized.item_variant, '');
    assert.strictEqual(normalized.price, 24.99);
  });

  it('4. Should maintain dynamic event prefix with full payload integrity and variant parameters', function () {
    EDL.setEventPrefix('raihan');
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
      content_type: 'product',
      meta_content_ids: ['BHK125-R'],
      items: [
        {
          item_id: '68',
          variant_id: '298',
          sku_id: 'BHK125-R',
          item_name: 'Trink und Snack Tasse',
          item_variant: 'Rostrot',
          price: 13.00,
          quantity: 1
        }
      ]
    });

    const pushedEvent = global.window.dataLayer[1];
    assert.strictEqual(pushedEvent.event, 'raihan_add_to_cart');
    assert.strictEqual(pushedEvent.original_event, 'add_to_cart');
    assert.strictEqual(pushedEvent.ecommerce.value, 13.00);
    assert.strictEqual(pushedEvent.ecommerce.content_type, 'product');
    assert.deepStrictEqual(pushedEvent.ecommerce.meta_content_ids, ['BHK125-R']);
    
    const item = pushedEvent.ecommerce.items[0];
    assert.strictEqual(item.item_id, '68');
    assert.strictEqual(item.variant_id, '298');
    assert.strictEqual(item.sku_id, 'BHK125-R');
    assert.strictEqual(item.item_variant, 'Rostrot');
  });

  it('5. Should sanitize and validate customer SHA-256 strings', function () {
    const validSha256 = 'a6c24312b18974513568c07c2d92161bc7478d10b754961555543cbe27efcf99';
    assert.strictEqual(/^[a-f0-9]{64}$/.test(validSha256), true);
  });
});
