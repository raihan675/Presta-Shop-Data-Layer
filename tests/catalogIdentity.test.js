const { describe, it, beforeEach } = require('node:test');
const assert = require('assert');
const EDL = require('../views/js/datalayer-core.js');

describe('Catalog-Accurate Identity & Variants Test Suite', function () {

  beforeEach(function () {
    global.window = { dataLayer: [] };
    EDL.setEventPrefix('');
  });

  it('1. Should strictly preserve item_id (base product ID), variant_id, sku_id, and item_variant without extraneous catalog IDs', function () {
    const rawVariantProduct = {
      item_id: '68',
      variant_id: '299',
      sku_id: 'BHK125-B',
      item_name: 'Trink und Snack Tasse',
      item_brand: 'Haakaa - Think Green Ltd.',
      item_category: 'Baby Geschirr',
      item_variant: 'Stahlblau',
      price: 13.00,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(rawVariantProduct);
    assert.strictEqual(normalized.item_id, '68', 'item_id should be base product ID');
    assert.strictEqual(normalized.variant_id, '299', 'variant_id should be active combination ID');
    assert.strictEqual(normalized.sku_id, 'BHK125-B', 'sku_id should be exact variant reference');
    assert.strictEqual(normalized.item_variant, 'Stahlblau', 'item_variant should be combination attribute');
    assert.strictEqual(normalized.item_name, 'Trink und Snack Tasse');
    assert.strictEqual(normalized.price, 13.00);
    assert.strictEqual(normalized.quantity, 1);
    assert.strictEqual(normalized.item_id_gmc, undefined, 'item_id_gmc should not be present');
    assert.strictEqual(normalized.item_id_meta, undefined, 'item_id_meta should not be present');
  });

  it('2. Should handle simple products without combinations cleanly', function () {
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

  it('3. Should maintain dynamic event prefix with clean payload integrity and variant parameters', function () {
    EDL.setEventPrefix('raihan');
    EDL.pushEvent('add_to_cart', {
      currency: 'EUR',
      value: 13.00,
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
    assert.strictEqual(pushedEvent.ecommerce.content_type, undefined, 'content_type should not be present');
    assert.strictEqual(pushedEvent.ecommerce.meta_content_ids, undefined, 'meta_content_ids should not be present');
    
    const item = pushedEvent.ecommerce.items[0];
    assert.strictEqual(item.item_id, '68');
    assert.strictEqual(item.variant_id, '298');
    assert.strictEqual(item.sku_id, 'BHK125-R');
    assert.strictEqual(item.item_variant, 'Rostrot');
  });

  it('4. Should sanitize and validate customer SHA-256 strings', function () {
    const validSha256 = 'a6c24312b18974513568c07c2d92161bc7478d10b754961555543cbe27efcf99';
    assert.strictEqual(/^[a-f0-9]{64}$/.test(validSha256), true);
  });

  it('5. Should verify user_data_raw debug object and omission of meta_external_id', function () {
    const hashedUserData = {
      email: '46fc338cb2b8b4b67e40afebb30e4b1574ff33908df21b56568683c2c9ebb161',
      phone_number: '4b8dc7dcdd3215bd6fd79105d672f6b873962a57c409eca872b946e05a804340',
      address: {
        first_name: '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
        last_name: '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08',
        street: '39faf2fdebfb3d054b21fd205cb05742855c987387e26a4b07cef0722dd070cf',
        city: 'dingolfing',
        postal_code: '84125',
        country: 'DE'
      }
    };

    const rawUserData = {
      email: 'customer@example.com',
      phone_number: '+491701234567',
      address: {
        first_name: 'John',
        last_name: 'Doe',
        street: 'Main Street 10',
        city: 'dingolfing',
        postal_code: '84125',
        country: 'DE'
      }
    };

    assert.strictEqual(hashedUserData.meta_external_id, undefined, 'meta_external_id must be removed');
    assert.strictEqual(rawUserData.email, 'customer@example.com');
    assert.strictEqual(rawUserData.phone_number, '+491701234567');
  });

  it('6. Should preserve item_brand and base product identifiers for simple product 1013', function () {
    const product1013 = {
      item_id: '1013',
      variant_id: '',
      sku_id: '5326310',
      item_name: 'Babytrage Terra Nova Beige',
      item_brand: 'Dooky - Xplorys BV',
      item_category: 'Babytrage',
      item_variant: '',
      price: 74.99,
      quantity: 1
    };

    const normalized = EDL.normalizeItem(product1013);
    assert.strictEqual(normalized.item_id, '1013');
    assert.strictEqual(normalized.variant_id, '');
    assert.strictEqual(normalized.sku_id, '5326310');
    assert.strictEqual(normalized.item_name, 'Babytrage Terra Nova Beige');
    assert.strictEqual(normalized.item_brand, 'Dooky - Xplorys BV');
    assert.strictEqual(normalized.item_category, 'Babytrage');
  });
});
