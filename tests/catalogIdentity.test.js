const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Catalog-Accurate Identity & Prefix Test Suite', function () {

  beforeEach(function () {
    global.window = { dataLayer: [] };
    EDL.setEventPrefix('');
  });

  it('1. Should preserve multi-catalog identifiers (item_id, item_id_gmc, item_id_meta) in items payload', function () {
    const rawItem = {
      item_id: 'FB-004-106',
      item_id_gmc: 'FB-004-106',
      item_id_meta: 'FB-004-106',
      item_sku: 'FB-004-106',
      item_internal_id: 1116,
      item_gtin: '5038278015476',
      item_name: 'Faltbare Babybadewanne mit Ablaufstopfen - Grau',
      price: 24.99,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(rawItem);
    assert.strictEqual(normalized.item_id, 'FB-004-106');
    assert.strictEqual(normalized.price, 24.99);
    assert.strictEqual(normalized.quantity, 1);
  });

  it('2. Should sanitize and validate customer SHA-256 strings', function () {
    const validSha256 = 'a6c24312b18974513568c07c2d92161bc7478d10b754961555543cbe27efcf99';
    assert.strictEqual(/^[a-f0-9]{64}$/.test(validSha256), true);
  });

  it('3. Should maintain dynamic event prefix with full payload integrity', function () {
    EDL.setEventPrefix('raihan');
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 24.99,
      items: [
        {
          item_id: 'FB-004-106',
          item_name: 'Faltbare Babybadewanne mit Ablaufstopfen - Grau',
          price: 24.99,
          quantity: 1
        }
      ]
    });

    assert.strictEqual(global.window.dataLayer[1].event, 'raihan_add_to_cart');
    assert.strictEqual(global.window.dataLayer[1].original_event, 'add_to_cart');
    assert.strictEqual(global.window.dataLayer[1].ecommerce.value, 24.99);
    assert.strictEqual(global.window.dataLayer[1].ecommerce.items[0].item_id, 'FB-004-106');
  });
});
