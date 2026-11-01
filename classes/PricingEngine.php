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
     * Resolves the authoritative line total for a given product and quantity,
     * taking into account PrestaShop's PS_ROUND_TYPE setting (item, line, or total).
     *
     * @param Product $product
     * @param int $idAttribute
     * @param int $quantity
     * @param Context|null $context
     * @return float
     */
    public static function getLinePrice(Product $product, $idAttribute = 0, $quantity = 1, Context $context = null)
    {
        $model = self::resolvePriceModel($product, $idAttribute, $quantity, $context);
        return isset($model['line_total']) ? (float)$model['line_total'] : self::getCustomerPrice($product, $idAttribute, $quantity, $context) * $quantity;
    }

    /**
     * Shared Internal Price Model: Resolves a complete, multi-dimensional price model
     * directly from PrestaShop's core calculation engine without DOM scraping.
     *
     * @param Product|int $product
     * @param int $idAttribute
     * @param int $quantity
     * @param Context|null $context
     * @return array
     */
    public static function resolvePriceModel($product, $idAttribute = 0, $quantity = 1, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $idProduct = is_object($product) ? (int)$product->id : (int)$product;
        $idAttr = (int)$idAttribute;
        $qty = ($quantity > 0) ? (int)$quantity : 1;

        $idCustomer = ($context && $context->customer) ? (int)$context->customer->id : 0;
        $idShop = ($context && $context->shop) ? (int)$context->shop->id : 1;
        $idCurrency = ($context && $context->currency) ? (int)$context->currency->id : (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $currencyCode = ($context && $context->currency) ? (string)$context->currency->iso_code : 'EUR';
        $precision = PlatformAdapter::getCurrencyPrecision($context);
        $isTaxIncl = PlatformAdapter::isTaxIncluded($context);

        $roundType = (class_exists('Configuration') && Configuration::get('PS_ROUND_TYPE'))
            ? (int)Configuration::get('PS_ROUND_TYPE')
            : 2; // Default Order::ROUND_LINE

        // 1. Unrounded base discounted prices (6 decimals)
        $rawDiscountedIncl = 0.0;
        $rawDiscountedExcl = 0.0;
        $rawRegularIncl = 0.0;
        $rawRegularExcl = 0.0;

        try {
            $specOut = null;
            $rawDiscountedIncl = (float)Product::getPriceStatic(
                $idProduct, true, $idAttr ?: null, 6, null, false, true, $qty, false,
                $idCustomer, ($context && $context->cart) ? (int)$context->cart->id : null,
                ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
                $specOut, true, true, $context
            );
            $rawDiscountedExcl = (float)Product::getPriceStatic(
                $idProduct, false, $idAttr ?: null, 6, null, false, true, $qty, false,
                $idCustomer, ($context && $context->cart) ? (int)$context->cart->id : null,
                ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
                $specOut, true, true, $context
            );

            // Regular base price without reductions
            $rawRegularIncl = (float)Product::getPriceStatic(
                $idProduct, true, $idAttr ?: null, 6, null, false, false, $qty, false,
                $idCustomer, ($context && $context->cart) ? (int)$context->cart->id : null,
                ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
                $specOut, true, true, $context
            );
            $rawRegularExcl = (float)Product::getPriceStatic(
                $idProduct, false, $idAttr ?: null, 6, null, false, false, $qty, false,
                $idCustomer, ($context && $context->cart) ? (int)$context->cart->id : null,
                ($context && $context->cart) ? (int)$context->cart->id_address_delivery : null,
                $specOut, true, true, $context
            );
        } catch (\Throwable $e) {}

        // Fallback if core calculation returned 0
        if ($rawDiscountedIncl <= 0 && is_object($product) && isset($product->price)) {
            $rawDiscountedExcl = (float)$product->price;
            $rawDiscountedIncl = $rawDiscountedExcl;
            $rawRegularExcl = $rawDiscountedExcl;
            $rawRegularIncl = $rawDiscountedExcl;
        }

        $unitPriceTaxIncl = self::roundHalfUp($rawDiscountedIncl, $precision);
        $unitPriceTaxExcl = self::roundHalfUp($rawDiscountedExcl, $precision);
        $regularPriceTaxIncl = self::roundHalfUp($rawRegularIncl, $precision);
        $regularPriceTaxExcl = self::roundHalfUp($rawRegularExcl, $precision);

        // Calculate line totals adhering strictly to PS_ROUND_TYPE:
        // Round Type 1: ROUND_ITEM (round unit price first, then multiply)
        // Round Type 2: ROUND_LINE (multiply unrounded price by quantity, then round)
        // Round Type 3: ROUND_TOTAL (unrounded multiplication, rounded at final line/total)
        if ($roundType === 1) {
            $lineTotalTaxIncl = self::roundHalfUp($unitPriceTaxIncl * $qty, $precision);
            $lineTotalTaxExcl = self::roundHalfUp($unitPriceTaxExcl * $qty, $precision);
        } else {
            $lineTotalTaxIncl = self::roundHalfUp($rawDiscountedIncl * $qty, $precision);
            $lineTotalTaxExcl = self::roundHalfUp($rawDiscountedExcl * $qty, $precision);
        }

        $unitPrice = $isTaxIncl ? $unitPriceTaxIncl : $unitPriceTaxExcl;
        $lineTotal = $isTaxIncl ? $lineTotalTaxIncl : $lineTotalTaxExcl;
        $taxAmount = max(0.0, self::roundHalfUp($lineTotalTaxIncl - $lineTotalTaxExcl, $precision));
        $discountAmount = max(0.0, self::roundHalfUp(($regularPriceTaxIncl - $unitPriceTaxIncl) * $qty, $precision));

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttr,
            'quantity' => $qty,
            'currency' => $currencyCode,
            'precision' => $precision,
            'is_tax_incl' => $isTaxIncl,
            'unit_price_tax_incl' => $unitPriceTaxIncl,
            'unit_price_tax_excl' => $unitPriceTaxExcl,
            'unit_price' => $unitPrice,
            'regular_price_tax_incl' => $regularPriceTaxIncl,
            'regular_price_tax_excl' => $regularPriceTaxExcl,
            'regular_price' => $isTaxIncl ? $regularPriceTaxIncl : $regularPriceTaxExcl,
            'line_total_tax_incl' => $lineTotalTaxIncl,
            'line_total_tax_excl' => $lineTotalTaxExcl,
            'line_total' => $lineTotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Extracts authoritative Cart totals from PrestaShop core models.
     *
     * @param Cart $cart
     * @param Context|null $context
     * @return array
     */
    public static function getCartTotalsModel(Cart $cart, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        $isTaxIncl = PlatformAdapter::isTaxIncluded($context);
        $precision = PlatformAdapter::getCurrencyPrecision($context);
        $currencyCode = ($context && $context->currency) ? (string)$context->currency->iso_code : 'EUR';

        $productsSubtotal = 0.0;
        $discountsTotal = 0.0;
        $shippingTotal = 0.0;
        $wrappingTotal = 0.0;
        $grandTotal = 0.0;
        $taxTotal = 0.0;

        try {
            $productsSubtotal = (float)$cart->getOrderTotal($isTaxIncl, Cart::ONLY_PRODUCTS);
            $discountsTotal = (float)$cart->getOrderTotal($isTaxIncl, Cart::ONLY_DISCOUNTS);
            $shippingTotal = (float)$cart->getOrderTotal($isTaxIncl, Cart::ONLY_SHIPPING);
            $wrappingTotal = (float)$cart->getOrderTotal($isTaxIncl, Cart::ONLY_WRAPPING);
            $grandTotal = (float)$cart->getOrderTotal($isTaxIncl, Cart::BOTH);

            $grandTotalTaxIncl = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $grandTotalTaxExcl = (float)$cart->getOrderTotal(false, Cart::BOTH);
            $taxTotal = max(0.0, $grandTotalTaxIncl - $grandTotalTaxExcl);
        } catch (\Throwable $e) {}

        return [
            'currency' => $currencyCode,
            'precision' => $precision,
            'is_tax_incl' => $isTaxIncl,
            'products_subtotal' => self::roundHalfUp($productsSubtotal, $precision),
            'discounts_total' => self::roundHalfUp($discountsTotal, $precision),
            'shipping_total' => self::roundHalfUp($shippingTotal, $precision),
            'wrapping_total' => self::roundHalfUp($wrappingTotal, $precision),
            'grand_total' => self::roundHalfUp($grandTotal, $precision),
            'tax_total' => self::roundHalfUp($taxTotal, $precision),
        ];
    }

    /**
     * Extracts recorded historical monetary totals from PrestaShop Order model.
     * Guaranteed 100% historical accuracy — never recalculates from current catalog.
     *
     * @param Order $order
     * @return array
     */
    public static function getOrderTotalsModel(Order $order)
    {
        $idCurrency = (int)$order->id_currency;
        $currency = new Currency($idCurrency);
        $currencyCode = Validate::isLoadedObject($currency) ? (string)$currency->iso_code : 'EUR';
        $precision = method_exists($currency, 'getPrecision') ? (int)$currency->getPrecision() : 2;

        $productsSubtotalTaxIncl = (isset($order->total_products_wt) && (float)$order->total_products_wt > 0)
            ? (float)$order->total_products_wt
            : (float)$order->total_products;
        $productsSubtotalTaxExcl = (float)$order->total_products;

        $shippingTaxIncl = (float)$order->total_shipping_tax_incl;
        $shippingTaxExcl = (float)$order->total_shipping_tax_excl;

        $discountsTaxIncl = (float)$order->total_discounts_tax_incl;
        $discountsTaxExcl = (float)$order->total_discounts_tax_excl;

        $wrappingTaxIncl = (float)$order->total_wrapping_tax_incl;
        $wrappingTaxExcl = (float)$order->total_wrapping_tax_excl;

        $grandTotal = (float)$order->total_paid_tax_incl;
        $grandTotalExcl = (float)$order->total_paid_tax_excl;
        $taxTotal = max(0.0, $grandTotal - $grandTotalExcl);

        return [
            'currency' => $currencyCode,
            'precision' => $precision,
            'products_subtotal_tax_incl' => self::roundHalfUp($productsSubtotalTaxIncl, $precision),
            'products_subtotal_tax_excl' => self::roundHalfUp($productsSubtotalTaxExcl, $precision),
            'shipping_tax_incl' => self::roundHalfUp($shippingTaxIncl, $precision),
            'shipping_tax_excl' => self::roundHalfUp($shippingTaxExcl, $precision),
            'discounts_tax_incl' => self::roundHalfUp($discountsTaxIncl, $precision),
            'discounts_tax_excl' => self::roundHalfUp($discountsTaxExcl, $precision),
            'wrapping_tax_incl' => self::roundHalfUp($wrappingTaxIncl, $precision),
            'wrapping_tax_excl' => self::roundHalfUp($wrappingTaxExcl, $precision),
            'grand_total' => self::roundHalfUp($grandTotal, $precision),
            'grand_total_tax_excl' => self::roundHalfUp($grandTotalExcl, $precision),
            'tax_total' => self::roundHalfUp($taxTotal, $precision),
        ];
    }

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
        try {
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
        } catch (\Throwable $e) {}

        // 2. Authoritative base retail price without reductions, evaluated with full 6-decimal precision
        $regularGross = 0.0;
        try {
            $specificPriceOutput = null;
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
        } catch (\Throwable $e) {
            $regularGross = isset($product->price) ? (float)$product->price : 0.0;
        }

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
        try {
            $specificPriceOutput = null;
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

            if ($staticPrice > 0) {
                return self::roundHalfUp($staticPrice, $precision);
            }
        } catch (\Throwable $e) {}

        return self::roundHalfUp(isset($product->price) ? (float)$product->price : 0.0, $precision);
    }
}
