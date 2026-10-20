<?php
/**
 * ProductIdentityResolver: Resolves the single, authoritative item_id
 * based on the product database record and user configuration.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductIdentityResolver
{
    /**
     * Resolves the exact single item_id for an item.
     * Respects the back-office setting: SKU / Reference vs Product ID.
     */
    public static function resolveItemId(Product $product, $idProductAttribute = 0)
    {
        $idProduct = (int)$product->id;
        $idAttr = (int)$idProductAttribute;

        $useSku = (bool)Configuration::get('PS_GTM_USE_SKU');

        if ($useSku) {
            // Check combination reference first if variant exists
            if ($idAttr > 0) {
                $combination = new Combination($idAttr);
                if (Validate::isLoadedObject($combination) && !empty($combination->reference)) {
                    return trim((string)$combination->reference);
                }
            }

            // Fallback to product main reference
            if (!empty($product->reference)) {
                return trim((string)$product->reference);
            }
        }

        // Default: Exact database Product ID (or compound ID-Attribute if variant exists)
        if ($idAttr > 0) {
            return "{$idProduct}-{$idAttr}";
        }

        return (string)$idProduct;
    }
}
