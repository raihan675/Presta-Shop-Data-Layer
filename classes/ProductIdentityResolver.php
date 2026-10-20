<?php
/**
 * ProductIdentityResolver: Resolves catalog-accurate product and variant identifiers
 * directly from the PrestaShop database models.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductIdentityResolver
{
    /**
     * Resolves the exact variant/combination reference (SKU) from database records.
     * Checks ps_product_attribute first. If empty, falls back to ps_product.reference.
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

            if (method_exists($product, 'getAttributeCombinationsById')) {
                $langId = (Context::getContext() && Context::getContext()->language) 
                    ? (int)Context::getContext()->language->id 
                    : 1;
                $combos = $product->getAttributeCombinationsById($idAttr, $langId);
                if (!empty($combos)) {
                    foreach ($combos as $c) {
                        if (!empty($c['reference'])) {
                            return trim((string)$c['reference']);
                        }
                    }
                }
            }
        }

        if (!empty($product->reference)) {
            return trim((string)$product->reference);
        }

        return '';
    }

    /**
     * Resolves human-readable variant attribute string from database records.
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
     * Returns a database-sourced dictionary of all combinations for this product.
     * Each entry contains the exact item_id, variant_id, sku_id, item_name, and price.
     *
     * @param Product $product
     * @param int|null $idLanguage
     * @return array
     */
    public static function getProductCombinationsMap(Product $product, $idLanguage = null)
    {
        if (!$idLanguage) {
            $idLanguage = (Context::getContext() && Context::getContext()->language) 
                ? (int)Context::getContext()->language->id 
                : 1;
        }

        $map = [];
        if (method_exists($product, 'getAttributeCombinations')) {
            $combos = $product->getAttributeCombinations($idLanguage);
            if (!empty($combos)) {
                foreach ($combos as $c) {
                    $idAttr = (string)$c['id_product_attribute'];
                    $groupName = !empty($c['group_name']) ? $c['group_name'] : '';
                    $attrName = !empty($c['attribute_name']) ? $c['attribute_name'] : '';
                    $label = ($groupName && $attrName) ? "{$groupName}: {$attrName}" : $attrName;
                    $ref = !empty($c['reference']) ? trim($c['reference']) : trim((string)$product->reference);

                    if (!isset($map[$idAttr])) {
                        $price = Product::getPriceStatic($product->id, true, (int)$idAttr, 2);
                        $map[$idAttr] = [
                            'item_id' => (string)$product->id,
                            'variant_id' => $idAttr,
                            'sku_id' => $ref,
                            'item_name' => (string)$product->name,
                            'item_variant' => $label,
                            'price' => round((float)$price, 2),
                        ];
                    } else {
                        if ($label) {
                            $map[$idAttr]['item_variant'] .= ' / ' . $label;
                        }
                        if (!empty($c['reference'])) {
                            $map[$idAttr]['sku_id'] = trim($c['reference']);
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Resolves the full structured identifier set directly from database ObjectModels.
     * item_id is strictly the primary key from ps_product.
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

        return [
            'item_id' => $idProduct,
            'variant_id' => $idAttr > 0 ? (string)$idAttr : '',
            'sku_id' => $sku,
            'item_variant' => $variantName,
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
        return (string)$product->id;
    }
}
