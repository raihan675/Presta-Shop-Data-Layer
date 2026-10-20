<?php
/**
 * ProductIdentityResolver: Evaluates and resolves product identifiers 
 * matching Google Merchant Center, Meta Catalog, and GA4 feeds.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductIdentityResolver
{
    const STRATEGY_SKU = 'sku';                   // Reference/SKU
    const STRATEGY_ID = 'id';                     // Product ID
    const STRATEGY_ID_ATTR = 'id_attribute';       // ProductID-AttributeID
    const STRATEGY_EAN = 'ean13';                 // GTIN / EAN13

    public static function resolveIdentifiers(Product $product, $idProductAttribute = 0)
    {
        $idProduct = (int)$product->id;
        $idAttr = (int)$idProductAttribute;

        // 1. Extract raw candidates from database record
        $sku = !empty($product->reference) ? trim((string)$product->reference) : '';
        $ean = !empty($product->ean13) ? trim((string)$product->ean13) : '';
        
        // If variant combination is selected, retrieve combination-specific SKU/EAN
        if ($idAttr > 0) {
            $combination = new Combination($idAttr);
            if (Validate::isLoadedObject($combination)) {
                if (!empty($combination->reference)) {
                    $sku = trim((string)$combination->reference);
                }
                if (!empty($combination->ean13)) {
                    $ean = trim((string)$combination->ean13);
                }
            }
        }

        // 2. Compute canonical compound IDs
        $idCompoundDash = $idAttr > 0 ? "{$idProduct}-{$idAttr}" : (string)$idProduct;

        // 3. Read Merchant Mapping Configuration
        $gmcStrategy = Configuration::get('PS_GTM_GMC_ID_STRATEGY');
        if (empty($gmcStrategy)) {
            $gmcStrategy = Configuration::get('PS_GTM_USE_SKU') ? self::STRATEGY_SKU : self::STRATEGY_ID;
        }

        $metaStrategy = Configuration::get('PS_GTM_META_ID_STRATEGY');
        if (empty($metaStrategy)) {
            $metaStrategy = Configuration::get('PS_GTM_USE_SKU') ? self::STRATEGY_SKU : self::STRATEGY_ID;
        }

        $canonicalStrategy = Configuration::get('PS_GTM_USE_SKU') ? self::STRATEGY_SKU : self::STRATEGY_ID;

        // 4. Resolve exact mapped IDs
        $gmcId = self::applyStrategy($gmcStrategy, $sku, $idProduct, $idCompoundDash, $ean);
        $metaId = self::applyStrategy($metaStrategy, $sku, $idProduct, $idCompoundDash, $ean);
        $canonicalId = self::applyStrategy($canonicalStrategy, $sku, $idProduct, $idCompoundDash, $ean);

        return [
            'item_id' => $canonicalId,
            'item_id_gmc' => $gmcId,
            'item_id_meta' => $metaId,
            'item_sku' => $sku,
            'item_internal_id' => $idProduct,
            'item_variant_id' => $idAttr,
            'item_gtin' => $ean,
        ];
    }

    private static function applyStrategy($strategy, $sku, $idProduct, $idCompound, $ean)
    {
        switch ($strategy) {
            case self::STRATEGY_SKU:
                return !empty($sku) ? $sku : (string)$idCompound;
            case self::STRATEGY_ID_ATTR:
                return (string)$idCompound;
            case self::STRATEGY_EAN:
                return !empty($ean) ? $ean : (!empty($sku) ? $sku : (string)$idProduct);
            case self::STRATEGY_ID:
            default:
                return (string)$idProduct;
        }
    }
}
