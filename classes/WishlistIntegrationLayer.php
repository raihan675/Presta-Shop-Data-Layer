<?php
/**
 * WishlistIntegrationLayer: Universal Backend Wishlist Architecture for PrestaShop.
 * Detects wishlist modules, verifies successful operations, and builds canonical GA4 payloads.
 * ZERO DOM SCRAPING — 100% authoritative database ObjectModel resolution.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/PlatformAdapter.php';
require_once __DIR__ . '/PricingEngine.php';
require_once __DIR__ . '/CanonicalDataModel.php';
require_once __DIR__ . '/ProductIdentityResolver.php';

class WishlistIntegrationLayer
{
    /**
     * Known supported PrestaShop wishlist module providers.
     */
    const PROVIDER_BLOCKWISHLIST = 'blockwishlist'; // PrestaShop Official Module
    const PROVIDER_PS_WISHLIST   = 'ps_wishlist';   // PrestaShop 8 Native / Fork
    const PROVIDER_IQITWISHLIST  = 'iqitwishlist';  // Warehouse Theme Wishlist
    const PROVIDER_LEOWISHLIST   = 'leowishlist';   // LeoTheme Wishlist
    const PROVIDER_WTWISHLIST    = 'wtwishlist';    // WaterTheme Wishlist

    /**
     * Detects installed and active wishlist modules in current PrestaShop instance.
     *
     * @return array Metadata on active wishlist capabilities
     */
    public static function getWishlistCapabilities()
    {
        $providers = [
            self::PROVIDER_BLOCKWISHLIST,
            self::PROVIDER_PS_WISHLIST,
            self::PROVIDER_IQITWISHLIST,
            self::PROVIDER_LEOWISHLIST,
            self::PROVIDER_WTWISHLIST,
        ];

        $activeProvider = null;
        foreach ($providers as $provider) {
            if (Module::isInstalled($provider) && Module::isEnabled($provider)) {
                $activeProvider = $provider;
                break;
            }
        }

        return [
            'has_wishlist' => ($activeProvider !== null),
            'provider' => $activeProvider,
            'supports_combinations' => true,
            'supports_guests' => in_array($activeProvider, [self::PROVIDER_BLOCKWISHLIST, self::PROVIDER_PS_WISHLIST]),
        ];
    }

    /**
     * Builds authoritative canonical GA4 add_to_wishlist payload from backend ObjectModels.
     *
     * @param Context $context PrestaShop context
     * @param int $idProduct Base Product ID
     * @param int $idProductAttribute Combination ID (0 if simple)
     * @param int $quantity Quantity added (default 1)
     * @param string $prefix Custom event prefix
     * @return array|null Canonical event payload or null on validation failure
     */
    public static function buildAddToWishlistPayload(Context $context, $idProduct, $idProductAttribute = 0, $quantity = 1, $prefix = '')
    {
        $idProd = (int)$idProduct;
        $idAttr = (int)$idProductAttribute;
        $qty = max(1, (int)$quantity);

        if ($idProd <= 0) {
            return null;
        }

        $idLanguage = $context->language ? (int)$context->language->id : 1;
        $idShop = $context->shop ? (int)$context->shop->id : 1;

        $productObj = new Product($idProd, true, $idLanguage, $idShop);
        if (!Validate::isLoadedObject($productObj) || !$productObj->active) {
            return null;
        }

        $price = PricingEngine::getCustomerPrice($productObj, $idAttr, $qty, $context);
        $identifiers = ProductIdentityResolver::resolveProductIdentifiers($productObj, $idAttr, $idLanguage);
        $category = new Category((int)$productObj->id_category_default, $idLanguage);

        $brand = '';
        if (!empty($productObj->manufacturer_name)) {
            $brand = (string)$productObj->manufacturer_name;
        } elseif (!empty($productObj->id_manufacturer)) {
            $brand = (string)Manufacturer::getNameById((int)$productObj->id_manufacturer);
        }

        $currencyCode = ($context->currency && !empty($context->currency->iso_code))
            ? (string)$context->currency->iso_code
            : 'EUR';
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($context);

        $formattedItem = CanonicalDataModel::formatItem([
            'item_id' => (string)$productObj->id,
            'variant_id' => $identifiers['variant_id'],
            'sku_id' => $identifiers['sku_id'],
            'item_name' => (string)$productObj->name,
            'item_brand' => $brand,
            'item_category' => Validate::isLoadedObject($category) ? (string)$category->name : '',
            'item_variant' => $identifiers['item_variant'],
            'price' => (float)$price,
            'quantity' => $qty,
        ]);

        $totalValue = PricingEngine::roundHalfUp($price * $qty, $currencyPrecision);
        $eventName = Ps_Gtm_DataLayer::formatEventName('add_to_wishlist', $prefix);

        return [
            'event' => $eventName,
            'original_event' => 'add_to_wishlist',
            'ecommerce' => [
                'currency' => $currencyCode,
                'value' => (float)$totalValue,
                'items' => [$formattedItem],
            ],
        ];
    }
}
