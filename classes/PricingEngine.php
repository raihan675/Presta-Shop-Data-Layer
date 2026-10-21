<?php
/**
 * PricingEngine: Universal PrestaShop Dynamic Pricing & Commercial Rounding Engine.
 * Resolves authoritative customer-facing retail prices dynamically across any store,
 * currency, catalog price rule, tax regime, and combination.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/PlatformAdapter.php';

class PricingEngine
{
    /**
     * Exact decimal-based commercial rounding (ROUND_HALF_UP).
     *
     * @param float|int|string $value
     * @param int|null $precision
     * @return float
     */
    public static function roundHalfUp($value, $precision = null)
    {
        if ($precision === null) {
            $precision = PlatformAdapter::getCurrencyPrecision();
        }

        $mode = PlatformAdapter::getRoundingMode();
        return round((float)$value, (int)$precision, $mode);
    }

    /**
     * Dynamically calculates the authoritative customer-facing product price exactly as
     * displayed on the storefront, factoring in specific prices, catalog rules, taxes,
     * currency precision, and commercial rounding.
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
        $qty = ($quantity > 0) ? (int)$quantity : 1;

        $idCustomer = ($context && $context->customer) ? (int)$context->customer->id : 0;
        $idGroup = ($context && $context->customer && !empty($context->customer->id_default_group)) 
            ? (int)$context->customer->id_default_group 
            : (class_exists('Group') ? (int)Group::getCurrent()->id : 1);
        $idShop = ($context && $context->shop) ? (int)$context->shop->id : 1;
        $idCurrency = ($context && $context->currency) ? (int)$context->currency->id : (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $idCountry = ($context && $context->country) ? (int)$context->country->id : (int)Configuration::get('PS_COUNTRY_DEFAULT');

        $precision = PlatformAdapter::getCurrencyPrecision($context);
        $useTax = PlatformAdapter::isTaxIncluded($context);

        // 1. Query PrestaShop Core pricing engine directly (authoritative net-base tax & reduction resolution)
        $specificPriceOutput = null;
        $coreCustomerPrice = (float)Product::getPriceStatic(
            $idProduct,
            $useTax,
            $idAttr ? $idAttr : null,
            6,
            null,
            false,
            true, // usereduc = true (authoritative discounted customer price)
            $qty,
            false,
            $idCustomer,
            ($context && $context->cart) ? (int)$context->cart->id : null,
            ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
            $specificPriceOutput,
            true,
            true,
            $context
        );

        if ($coreCustomerPrice > 0) {
            return self::roundHalfUp($coreCustomerPrice, $precision);
        }

        // 2. Authoritative base retail price without reductions, evaluated with full 6-decimal precision
        $regularGross = (float)Product::getPriceStatic(
            $idProduct,
            $useTax,
            $idAttr ? $idAttr : null,
            6,
            null,
            false,
            false, // usereduc = false (authoritative regular catalog price)
            $qty,
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

        // 3. Query for active specific price rules (discounts/promotions)
        $specificPrice = null;
        if (class_exists('SpecificPrice')) {
            $specificPrice = SpecificPrice::getSpecificPrice(
                $idProduct,
                $idShop,
                $idCurrency,
                $idCountry,
                $idGroup,
                $qty,
                $idAttr,
                $idCustomer,
                ($context && $context->cart) ? (int)$context->cart->id : 0
            );
        }

        if (!empty($specificPrice)) {
            if ($specificPrice['reduction_type'] === 'percentage') {
                $reduction = (float)$specificPrice['reduction'];
                // Apply percentage discount directly to the customer-facing retail price
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

        // Standard calculation fallback with full precision then round half-up
        $staticPrice = (float)Product::getPriceStatic(
            $idProduct,
            $useTax,
            $idAttr ? $idAttr : null,
            6,
            null,
            false,
            true,
            $qty,
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
}
