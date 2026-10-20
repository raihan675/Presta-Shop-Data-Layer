<?php
/**
 * ProductIdentityResolver: Resolves catalog-accurate product and variant identifiers
 * for GA4, Google Merchant Center, and Meta Catalog.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductIdentityResolver
{
    /**
     * Resolves the exact variant/combination reference (SKU).
     * If combination has a specific reference, returns it.
     * Otherwise falls back to base product reference.
     *
     * @param Product $product
     * @param int $idProductAttribute
     * @return string
     */
    public static function resolveVariantReference(Product $product, $idProductAttribute = 0)
    {
        $idAttr = (int)$idProductAttribute;

        if ($idAttr > 0) {
            $combination = new Combination($idAttr);
            if (Validate::isLoadedObject($combination) && !empty($combination->reference)) {
                return trim((string)$combination->reference);
            }

            // Fallback check through attribute combination query
            if (method_exists($product, 'getAttributeCombinationsById')) {
                $langId = (Context::getContext() && Context::getContext()->language) 
                    ? (int)Context::getContext()->language->id 
                    : 1;
                $combos = $product->getAttributeCombinationsById($idAttr, $langId);
                if (!empty($combos) && !empty($combos[0]['reference'])) {
                    return trim((string)$combos[0]['reference']);
                }
            }
        }

        if (!empty($product->reference)) {
            return trim((string)$product->reference);
        }

        return '';
    }

    /**
     * Resolves human-readable variant attribute string (e.g. "Rostrot" or "Farbe: Rostrot")
     *
     * @param Product $product
     * @param int $idProductAttribute
     * @param int|null $idLanguage
     * @return string
     */
    public static function resolveVariantName(Product $product, $idProductAttribute = 0, $idLanguage = null)
    {
        $idAttr = (int)$idProductAttribute;
        if ($idAttr <= 0) {
            return '';
        }

        if (!$idLanguage) {
            $idLanguage = (Context::getContext() && Context::getContext()->language) 
                ? (int)Context::getContext()->language->id 
                : 1;
        }

        $variant = '';
        if (method_exists($product, 'getAttributeCombinationsById')) {
            $combinations = $product->getAttributeCombinationsById($idAttr, $idLanguage);
            if (!empty($combinations)) {
                $attrs = [];
                foreach ($combinations as $c) {
                    $groupName = !empty($c['group_name']) ? $c['group_name'] : '';
                    $attrName = !empty($c['attribute_name']) ? $c['attribute_name'] : '';
                    if ($groupName && $attrName) {
                        $attrs[] = "{$groupName}: {$attrName}";
                    } elseif ($attrName) {
                        $attrs[] = $attrName;
                    }
                }
                $variant = implode(' / ', $attrs);
            }
        }

        if (empty($variant) && method_exists($product, 'getAttributesResume')) {
            $resume = $product->getAttributesResume($idLanguage);
            if (!empty($resume)) {
                foreach ($resume as $r) {
                    if ((int)$r['id_product_attribute'] === $idAttr && !empty($r['attribute'])) {
                        $variant = (string)$r['attribute'];
                        break;
                    }
                }
            }
        }

        return $variant;
    }

    /**
     * Resolves an identifier based on a mapping strategy rule.
     *
     * @param string $mappingStrategy ('base_id', 'sku', 'combination_id', 'compound_dash', 'compound_underscore')
     * @param Product $product
     * @param int $idProductAttribute
     * @param string $sku
     * @return string
     */
    public static function resolveMappedId($mappingStrategy, Product $product, $idProductAttribute = 0, $sku = '')
    {
        $idProduct = (string)$product->id;
        $idAttr = (int)$idProductAttribute;

        if (empty($sku)) {
            $sku = self::resolveVariantReference($product, $idAttr);
        }

        switch ($mappingStrategy) {
            case 'sku':
                return !empty($sku) ? $sku : $idProduct;
            case 'combination_id':
                return $idAttr > 0 ? (string)$idAttr : $idProduct;
            case 'compound_dash':
                return $idAttr > 0 ? "{$idProduct}-{$idAttr}" : $idProduct;
            case 'compound_underscore':
                return $idAttr > 0 ? "{$idProduct}_{$idAttr}" : $idProduct;
            case 'base_id':
            default:
                return $idProduct;
        }
    }

    /**
     * Resolves the full structured identifier set for any product or combination.
     *
     * @param Product $product
     * @param int $idProductAttribute
     * @param int|null $idLanguage
     * @return array
     */
    public static function resolveProductIdentifiers(Product $product, $idProductAttribute = 0, $idLanguage = null)
    {
        $idProduct = (string)$product->id;
        $idAttr = (int)$idProductAttribute;

        $sku = self::resolveVariantReference($product, $idAttr);
        $variantName = self::resolveVariantName($product, $idAttr, $idLanguage);

        $itemIdMapping = Configuration::get('PS_GTM_ITEM_ID_MAPPING');
        if (empty($itemIdMapping)) {
            // Check legacy setting: if USE_SKU was enabled, default to sku, else base_id
            $itemIdMapping = Configuration::get('PS_GTM_USE_SKU') ? 'sku' : 'base_id';
        }

        $gmcMapping = Configuration::get('PS_GTM_GMC_ID_MAPPING');
        if (empty($gmcMapping)) {
            $gmcMapping = 'sku';
        }

        $metaMapping = Configuration::get('PS_GTM_META_ID_MAPPING');
        if (empty($metaMapping)) {
            $metaMapping = 'sku';
        }

        return [
            'item_id' => self::resolveMappedId($itemIdMapping, $product, $idAttr, $sku),
            'base_product_id' => $idProduct,
            'variant_id' => $idAttr > 0 ? (string)$idAttr : '',
            'sku_id' => $sku,
            'item_variant' => $variantName,
            'item_id_gmc' => self::resolveMappedId($gmcMapping, $product, $idAttr, $sku),
            'item_id_meta' => self::resolveMappedId($metaMapping, $product, $idAttr, $sku),
        ];
    }

    /**
     * Backward-compatible helper method.
     *
     * @param Product $product
     * @param int $idProductAttribute
     * @return string
     */
    public static function resolveItemId(Product $product, $idProductAttribute = 0)
    {
        $identifiers = self::resolveProductIdentifiers($product, $idProductAttribute);
        return $identifiers['item_id'];
    }
}
