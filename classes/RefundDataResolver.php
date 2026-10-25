<?php
/**
 * RefundDataResolver: Extracts authoritative refund details, line items,
 * financial values, and normalized customer identifiers from PrestaShop models.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/ProductIdentityResolver.php';
require_once __DIR__ . '/CanonicalDataModel.php';

class RefundDataResolver
{
    /**
     * Resolves refund payload data from hookActionOrderSlipAdd parameters.
     *
     * @param Order $order
     * @param array $productList
     * @param array $qtyList
     * @return array
     */
    public static function resolveFromOrderSlipParams(Order $order, array $productList, array $qtyList = [])
    {
        $idCurrency = (int)$order->id_currency;
        $currency = new Currency($idCurrency);
        $currencyIso = Validate::isLoadedObject($currency) ? $currency->iso_code : 'EUR';

        $catalogStrategy = class_exists('Configuration') && Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')
            ? (string)Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')
            : 'composite';

        $items = [];
        $totalRefundValue = 0.0;
        $totalShippingRefunded = 0.0;

        $idLanguage = (int)Context::getContext()->language->id ?: (int)Configuration::get('PS_LANG_DEFAULT');

        // Resolve each refunded line item
        foreach ($productList as $idOrderDetail => $itemData) {
            $idDetail = (int)$idOrderDetail;
            $orderDetail = new OrderDetail($idDetail);
            if (!Validate::isLoadedObject($orderDetail)) {
                continue;
            }

            $idProduct = (int)$orderDetail->product_id;
            $idProductAttribute = (int)$orderDetail->product_attribute_id;

            // Resolve quantity
            $quantity = 1;
            if (isset($qtyList[$idDetail])) {
                $quantity = max(1, (int)$qtyList[$idDetail]);
            } elseif (isset($itemData['quantity'])) {
                $quantity = max(1, (int)$itemData['quantity']);
            }

            // Resolve price
            $unitPrice = 0.0;
            if (isset($itemData['unit_price_tax_incl']) && (float)$itemData['unit_price_tax_incl'] > 0) {
                $unitPrice = (float)$itemData['unit_price_tax_incl'];
            } elseif (isset($itemData['amount_tax_incl']) && (float)$itemData['amount_tax_incl'] > 0) {
                $unitPrice = (float)$itemData['amount_tax_incl'] / $quantity;
            } elseif (isset($orderDetail->unit_price_tax_incl)) {
                $unitPrice = (float)$orderDetail->unit_price_tax_incl;
            }

            $lineTotal = round($unitPrice * $quantity, 2);
            $totalRefundValue += $lineTotal;

            // Resolve Catalog Product ID according to configured strategy
            $productObj = new Product($idProduct, false, $idLanguage);
            $catalogItemId = self::resolveCatalogIdentifier($productObj, $idProduct, $idProductAttribute, $orderDetail->product_reference, $catalogStrategy);

            // Brand & Category
            $brand = !empty($productObj->manufacturer_name) ? (string)$productObj->manufacturer_name : '';
            if (empty($brand) && $productObj->id_manufacturer > 0) {
                $brand = (string)Manufacturer::getNameById((int)$productObj->id_manufacturer);
            }

            $category = '';
            if ($productObj->id_category_default > 0) {
                $catObj = new Category((int)$productObj->id_category_default, $idLanguage);
                if (Validate::isLoadedObject($catObj) && !empty($catObj->name)) {
                    $category = (string)$catObj->name;
                }
            }

            // Variant label
            $variantLabel = ProductIdentityResolver::resolveVariantName($productObj, $idProductAttribute, $idLanguage);

            $items[] = [
                'item_id' => $catalogItemId,
                'item_name' => (string)$orderDetail->product_name,
                'item_brand' => $brand,
                'item_category' => $category,
                'item_variant' => $variantLabel,
                'price' => round($unitPrice, 2),
                'quantity' => $quantity,
            ];
        }

        // Check if shipping was refunded (from latest OrderSlip record)
        $idOrderSlip = self::getLatestOrderSlipId((int)$order->id);
        if ($idOrderSlip > 0) {
            $slip = new OrderSlip($idOrderSlip);
            if (Validate::isLoadedObject($slip)) {
                if ((float)$slip->total_shipping_tax_incl > 0) {
                    $totalShippingRefunded = round((float)$slip->total_shipping_tax_incl, 2);
                    $totalRefundValue += $totalShippingRefunded;
                } elseif ((float)$slip->total_products_tax_incl > 0 && empty($items)) {
                    // Fallback if productList was empty in params
                    $totalRefundValue = round((float)$slip->total_products_tax_incl + (float)$slip->total_shipping_tax_incl, 2);
                }
            }
        }

        // Customer contact & address details
        $userData = self::extractUserData($order);

        return [
            'transaction_id' => (string)$order->id,
            'order_reference' => (string)$order->reference,
            'id_order_slip' => $idOrderSlip,
            'currency' => $currencyIso,
            'value' => round($totalRefundValue, 2),
            'shipping' => $totalShippingRefunded,
            'items' => $items,
            'user_data' => $userData,
        ];
    }

    /**
     * Resolves the exact catalog identifier for the refunded product line.
     *
     * @param Product $product
     * @param int $idProduct
     * @param int $idProductAttribute
     * @param string $fallbackReference
     * @param string $strategy
     * @return string
     */
    public static function resolveCatalogIdentifier(Product $product, $idProduct, $idProductAttribute, $fallbackReference = '', $strategy = 'composite')
    {
        $idProductStr = (string)$idProduct;
        $idAttr = (int)$idProductAttribute;

        if ($strategy === 'reference_sku') {
            $sku = '';
            if (Validate::isLoadedObject($product)) {
                $sku = ProductIdentityResolver::resolveVariantReference($product, $idAttr);
            }
            if (empty($sku)) {
                $sku = trim((string)$fallbackReference);
            }
            if (!empty($sku)) {
                return $sku;
            }
            // Fallback cleanly to composite or base ID if SKU is missing
            return $idAttr > 0 ? "{$idProductStr}_{$idAttr}" : $idProductStr;
        }

        if ($strategy === 'base_id') {
            return $idProductStr;
        }

        // Default 'composite': {id_product}_{id_product_attribute}
        return $idAttr > 0 ? "{$idProductStr}_{$idAttr}" : $idProductStr;
    }

    /**
     * Extracts and normalizes customer data into SHA-256 hashes for Enhanced Conversions / Meta CAPI.
     *
     * @param Order $order
     * @return array
     */
    public static function extractUserData(Order $order)
    {
        $userData = [];

        $customer = new Customer((int)$order->id_customer);
        if (Validate::isLoadedObject($customer)) {
            if (!empty($customer->email)) {
                $cleanEmail = strtolower(trim((string)$customer->email));
                $userData['email_address'] = hash('sha256', $cleanEmail);
            }
            if (!empty($customer->firstname)) {
                $userData['first_name'] = hash('sha256', strtolower(trim((string)$customer->firstname)));
            }
            if (!empty($customer->lastname)) {
                $userData['last_name'] = hash('sha256', strtolower(trim((string)$customer->lastname)));
            }
        }

        $idAddress = (int)$order->id_address_delivery ?: (int)$order->id_address_invoice;
        if ($idAddress > 0) {
            $address = new Address($idAddress);
            if (Validate::isLoadedObject($address)) {
                $phone = !empty($address->phone) ? $address->phone : $address->phone_mobile;
                if (!empty($phone)) {
                    $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
                    if (!empty($cleanPhone)) {
                        $userData['phone_number'] = hash('sha256', $cleanPhone);
                    }
                }

                $countryIso = '';
                if ($address->id_country > 0) {
                    $countryIso = (string)Country::getIsoById((int)$address->id_country);
                }

                $addressData = [];
                if (!empty($address->city)) {
                    $addressData['city'] = trim((string)$address->city);
                }
                if (!empty($address->postcode)) {
                    $addressData['postal_code'] = trim((string)$address->postcode);
                }
                if (!empty($countryIso)) {
                    $addressData['country'] = strtoupper($countryIso);
                }

                if (!empty($addressData)) {
                    $userData['address'] = $addressData;
                }
            }
        }

        return $userData;
    }

    /**
     * Gets the latest order slip ID for an order.
     *
     * @param int $idOrder
     * @return int
     */
    public static function getLatestOrderSlipId($idOrder)
    {
        try {
            $sql = 'SELECT MAX(`id_order_slip`) FROM `' . _DB_PREFIX_ . 'order_slip` WHERE `id_order` = ' . (int)$idOrder;
            return (int)Db::getInstance()->getValue($sql);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
