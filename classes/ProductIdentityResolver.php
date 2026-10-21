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
     * Exact decimal-based commercial rounding (ROUND_HALF_UP).
     *
     * @param float|int|string $value
     * @param int $precision
     * @return float
     */
    public static function roundHalfUp($value, $precision = 2)
    {
        return round((float)$value, (int)$precision, PHP_ROUND_HALF_UP);
    }

    /**
     * Resolves the authoritative customer-facing price exactly as displayed on the storefront,
     * respecting specific prices (promotions/catalog discounts), taxes, currency precision,
     * and ROUND_HALF_UP commercial rounding.
     *
     * @param Product $product
     * @param int $idAttribute
     * @param int $quantity
     * @param Context|null $context
     * @return float
     */
    public static function getCustomerPrice(Product $product, $idAttribute = 0, $quantity = 1, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $idProduct = (int)$product->id;
        $idAttr = (int)$idAttribute;
        $idCustomer = ($context && $context->customer) ? (int)$context->customer->id : 0;
        $idGroup = ($context && $context->customer && !empty($context->customer->id_default_group)) 
            ? (int)$context->customer->id_default_group 
            : (class_exists('Group') ? (int)Group::getCurrent()->id : 1);
        $idShop = ($context && $context->shop) ? (int)$context->shop->id : 1;
        $idCurrency = ($context && $context->currency) ? (int)$context->currency->id : (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $idCountry = ($context && $context->country) ? (int)$context->country->id : (int)Configuration::get('PS_COUNTRY_DEFAULT');

        $precision = ($context && $context->currency && isset($context->currency->precision)) 
            ? (int)$context->currency->precision 
            : 2;
        if ($precision <= 0) {
            $precision = 2;
        }

        $useTax = true;
        if (class_exists('Product') && method_exists('Product', 'getTaxCalculationMethod')) {
            $useTax = !Product::getTaxCalculationMethod($idCustomer);
        }

        // 1. Authoritative regular price without reduction, keeping full precision (6 decimals)
        $specificPriceOutput = null;
        $regularGross = (float)Product::getPriceStatic(
            $idProduct,
            $useTax,
            $idAttr ? $idAttr : null,
            6,
            null,
            false,
            false, // usereduc = false (regular price)
            $quantity,
            false,
            $idCustomer,
            ($context && $context->cart) ? (int)$context->cart->id : null,
            ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
            $specificPriceOutput,
            true,
            true,
            $context
        );

        $regularRounded = self::roundHalfUp($regularGross, $precision);

        // 2. Query for active specific price (discount/promotion)
        $specificPrice = null;
        if (class_exists('SpecificPrice')) {
            $specificPrice = SpecificPrice::getSpecificPrice(
                $idProduct,
                $idShop,
                $idCurrency,
                $idCountry,
                $idGroup,
                $quantity,
                $idAttr,
                $idCustomer,
                ($context && $context->cart) ? (int)$context->cart->id : 0
            );
        }

        if (!empty($specificPrice)) {
            if ($specificPrice['reduction_type'] === 'percentage') {
                $reduction = (float)$specificPrice['reduction'];
                // Apply percentage discount directly to the customer-facing gross regular price
                $finalPrice = $regularRounded * (1.0 - $reduction);
                return self::roundHalfUp($finalPrice, $precision);
            } elseif ($specificPrice['reduction_type'] === 'amount') {
                $reduction = (float)$specificPrice['reduction'];
                if (!$specificPrice['reduction_tax'] && $useTax) {
                    $taxRate = method_exists($product, 'getTaxesRate') 
                        ? (float)$product->getTaxesRate(new Address(($context && $context->cart) ? (int)$context->cart->id_address_delivery : 0)) 
                        : 0.0;
                    $reduction = $reduction * (1 + ($taxRate / 100));
                }
                $finalPrice = max(0.0, $regularRounded - $reduction);
                return self::roundHalfUp($finalPrice, $precision);
            } elseif ((float)$specificPrice['price'] >= 0) {
                $fixedPrice = (float)$specificPrice['price'];
                if ($useTax) {
                    $taxRate = method_exists($product, 'getTaxesRate') 
                        ? (float)$product->getTaxesRate(new Address(($context && $context->cart) ? (int)$context->cart->id_address_delivery : 0)) 
                        : 0.0;
                    $finalPrice = $fixedPrice * (1 + ($taxRate / 100));
                } else {
                    $finalPrice = $fixedPrice;
                }
                return self::roundHalfUp($finalPrice, $precision);
            }
        }

        // Standard calculation fallback with 6 decimals then round half-up
        $staticPrice = (float)Product::getPriceStatic(
            $idProduct,
            $useTax,
            $idAttr ? $idAttr : null,
            6,
            null,
            false,
            true,
            $quantity,
            false,
            $idCustomer,
            ($context && $context->cart) ? (int)$context->cart->id : null,
            ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
            $specificPriceOutput,
            true,
            true,
            $context
        );

        return self::roundHalfUp($staticPrice, $precision);
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
                        $price = self::getCustomerPrice($product, (int)$idAttr, 1);
                        $map[$idAttr] = [
                            'item_id' => (string)$product->id,
                            'variant_id' => $idAttr,
                            'sku_id' => $ref,
                            'item_name' => (string)$product->name,
                            'item_variant' => $label,
                            'price' => (float)$price,
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
