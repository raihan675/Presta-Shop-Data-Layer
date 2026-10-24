<?php
/**
 * Constructs catalog-accurate GA4 ecommerce payloads across catalog, cart, and checkout states.
 * 100% authoritative backend data sourcing — zero DOM dependency.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/PlatformAdapter.php';
require_once __DIR__ . '/PricingEngine.php';
require_once __DIR__ . '/CanonicalDataModel.php';
require_once __DIR__ . '/ProductIdentityResolver.php';
require_once __DIR__ . '/CustomerDataEngine.php';
require_once __DIR__ . '/WishlistIntegrationLayer.php';

class PayloadBuilder
{
    private $context;
    private $prefix;

    public function __construct(Context $context, $prefix = '')
    {
        $this->context = $context;
        $this->prefix = Ps_Gtm_DataLayer::normalizePrefix($prefix);
    }

    private function getEventName($canonicalName)
    {
        return Ps_Gtm_DataLayer::formatEventName($canonicalName, $this->prefix);
    }

    /**
     * Resolves the exact catalog item identifier according to catalog matching strategy.
     * Default: composite convention {id_product}_{id_product_attribute} (e.g. 68_299)
     *
     * @param array $item
     * @param string|null $strategy
     * @return string
     */
    public function resolveCatalogItemId(array $item, $strategy = null)
    {
        if (!$strategy) {
            $strategy = (class_exists('Configuration') && Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')) 
                ? (string)Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') 
                : 'composite';
        }

        if ($strategy === 'reference_sku' && !empty($item['sku_id'])) {
            return trim((string)$item['sku_id']);
        }
        if ($strategy === 'id_product') {
            return trim((string)$item['item_id']);
        }

        $itemId = isset($item['item_id']) ? trim((string)$item['item_id']) : '';
        $variantId = isset($item['variant_id']) ? trim((string)$item['variant_id']) : '';

        // If item_id already contains composite separator, preserve it
        if (strpos($itemId, '_') !== false) {
            return $itemId;
        }

        if ($variantId !== '' && $variantId !== '0' && (int)$variantId > 0) {
            return $itemId . '_' . $variantId;
        }

        return $itemId;
    }

    /**
     * Authoritative search keyword extractor across native PrestaShop and third-party modules
     * (Warehouse/iqitsearch, Leo Search, Jolisearch, PM AdvancedSearch4, etc.)
     *
     * @return string
     */
    public static function getSearchQuery()
    {
        $keys = ['s', 'search_query', 'q', 'query', 'tag'];
        foreach ($keys as $k) {
            if (class_exists('Tools')) {
                $val = Tools::getValue($k);
                if ($val !== false && $val !== null && is_string($val) && trim($val) !== '') {
                    return trim($val);
                }
            } elseif (isset($_GET[$k]) && is_string($_GET[$k]) && trim($_GET[$k]) !== '') {
                return trim($_GET[$k]);
            }
        }
        return '';
    }

    /**
     * Determines whether the current controller represents a search results page
     * across native PrestaShop and third-party modules.
     *
     * @param string $controller
     * @param Context|null $context
     * @return bool
     */
    public static function isSearchController($controller, $context = null)
    {
        if (empty($controller)) {
            if ($context && isset($context->controller->php_self)) {
                $controller = (string)$context->controller->php_self;
            } elseif (class_exists('Tools')) {
                $controller = (string)Tools::getValue('controller', '');
            }
        }

        $ctrlLower = strtolower(trim((string)$controller));

        if ($ctrlLower === 'search' || $ctrlLower === 'searchiqit') {
            return true;
        }
        if (strpos($ctrlLower, 'search') !== false) {
            return true;
        }

        if ($context && is_object($context->controller)) {
            $class = strtolower(get_class($context->controller));
            if (strpos($class, 'search') !== false) {
                return true;
            }
        }

        $query = self::getSearchQuery();
        if ($query !== '' && (strpos($ctrlLower, 'module-') === 0 || $ctrlLower === '')) {
            return true;
        }

        return false;
    }

    /**
     * Universal Advertising Platform Namespace Builder:
     * Generates exact, catalog-matching payload parameters for Meta, TikTok, Google Ads, and Pinterest.
     *
     * @param string $canonicalEvent
     * @param array $items
     * @param string $currency
     * @param float $totalValue
     * @param array $options
     * @return array
     */
    public function buildPlatformNamespaces($canonicalEvent, array $items, $currency, $totalValue, array $options = [])
    {
        $currencyCode = !empty($currency) ? (string)$currency : 'EUR';
        $val = (float)$totalValue;
        $strategy = (class_exists('Configuration') && Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')) 
            ? (string)Configuration::get('PS_GTM_CATALOG_ID_STRATEGY') 
            : 'composite';

        $isList = ($canonicalEvent === 'view_item_list');
        $isSingleProduct = in_array($canonicalEvent, ['view_item', 'select_item', 'add_to_wishlist']) && count($items) === 1;

        $pageType = 'other';
        if ($canonicalEvent === 'view_search_results' || $canonicalEvent === 'search') {
            $pageType = 'searchresults';
        } elseif ($canonicalEvent === 'view_item_list') {
            $pageType = (!empty($options['list_id']) && strpos((string)$options['list_id'], 'search') !== false) ? 'searchresults' : 'category';
        } elseif ($canonicalEvent === 'view_item' || $canonicalEvent === 'select_item') {
            $pageType = 'product';
        } elseif (in_array($canonicalEvent, ['view_cart', 'add_to_cart', 'remove_from_cart', 'begin_checkout', 'add_shipping_info', 'add_payment_info'])) {
            $pageType = 'cart';
        } elseif ($canonicalEvent === 'purchase') {
            $pageType = 'purchase';
        }

        $metaContentIds = [];
        $metaContents = [];
        $tiktokContents = [];
        $googleProdIds = [];
        $googleDynamicItems = [];
        $googleCartDataItems = [];
        $pinterestProdIds = [];
        $pinterestLineItems = [];
        $microsoftProdIds = [];
        $microsoftItems = [];
        $totalQuantity = 0;

        foreach ($items as $it) {
            $catId = $this->resolveCatalogItemId($it, $strategy);
            $qty = isset($it['quantity']) ? (int)$it['quantity'] : 1;
            if ($qty <= 0) {
                $qty = 1;
            }
            $price = isset($it['price']) ? (float)$it['price'] : 0.0;
            $totalQuantity += $qty;

            // In view_item_list, Meta & TikTok prefer parent base ID with product_group
            $metaId = $isList ? (string)$it['item_id'] : $catId;
            if (!in_array($metaId, $metaContentIds)) {
                $metaContentIds[] = $metaId;
            }
            // Meta official contents schema: id, quantity, item_price
            $metaContents[] = [
                'id' => $metaId,
                'quantity' => $qty,
                'item_price' => $price,
            ];

            // TikTok official contents schema: content_id, content_type, content_name, content_category, quantity, price
            $ttId = $isList ? (string)$it['item_id'] : $catId;
            $tiktokContents[] = [
                'content_id' => $ttId,
                'content_type' => 'product',
                'content_name' => isset($it['item_name']) ? (string)$it['item_name'] : '',
                'content_category' => isset($it['item_category']) ? (string)$it['item_category'] : '',
                'quantity' => $qty,
                'price' => $price,
            ];

            if (!in_array($catId, $googleProdIds)) {
                $googleProdIds[] = $catId;
            }
            // Google Ads Retail Dynamic Remarketing items schema
            $googleDynamicItems[] = [
                'id' => $catId,
                'google_business_vertical' => 'retail',
            ];
            // Google Ads Conversions with Cart Data items schema
            $googleCartDataItems[] = [
                'id' => $catId,
                'quantity' => $qty,
                'price' => $price,
            ];

            if (!in_array($catId, $pinterestProdIds)) {
                $pinterestProdIds[] = $catId;
            }
            // Pinterest Web Tag official line_items schema
            $pinterestLineItems[] = [
                'product_id' => $catId,
                'product_name' => isset($it['item_name']) ? (string)$it['item_name'] : '',
                'product_price' => $price,
                'product_quantity' => $qty,
                'product_category' => isset($it['item_category']) ? (string)$it['item_category'] : '',
                'product_brand' => isset($it['item_brand']) ? (string)$it['item_brand'] : '',
                'product_variant' => isset($it['item_variant']) ? (string)$it['item_variant'] : '',
                'product_variant_id' => (isset($it['variant_id']) && (string)$it['variant_id'] !== '0') ? (string)$it['variant_id'] : '',
            ];

            if (!in_array($catId, $microsoftProdIds)) {
                $microsoftProdIds[] = $catId;
            }
            // Microsoft Advertising official items schema
            $msftItem = [
                'id' => $catId,
                'name' => isset($it['item_name']) ? (string)$it['item_name'] : '',
                'brand' => isset($it['item_brand']) ? (string)$it['item_brand'] : '',
                'category' => isset($it['item_category']) ? (string)$it['item_category'] : '',
                'variant' => isset($it['item_variant']) ? (string)$it['item_variant'] : '',
                'price' => $price,
                'quantity' => $qty,
            ];
            if (!empty($it['list_name'])) {
                $msftItem['list_name'] = (string)$it['list_name'];
            }
            if (isset($it['list_position'])) {
                $msftItem['list_position'] = (int)$it['list_position'];
            }
            $microsoftItems[] = $msftItem;
        }

        // Meta Event Name Resolution
        $metaEventName = 'Custom';
        $metaCustomName = null;

        switch ($canonicalEvent) {
            case 'view_item':
            case 'select_item':
                $metaEventName = 'ViewContent';
                break;
            case 'view_cart':
                $metaEventName = 'Custom';
                $metaCustomName = 'ViewCart';
                break;
            case 'view_item_list':
                $metaEventName = 'ViewCategory';
                break;
            case 'add_to_cart':
                $metaEventName = 'AddToCart';
                break;
            case 'purchase':
                $metaEventName = 'Purchase';
                break;
            case 'search':
            case 'view_search_results':
                $metaEventName = 'Search';
                break;
            case 'add_to_wishlist':
                $metaEventName = 'AddToWishlist';
                break;
            case 'begin_checkout':
                $metaEventName = 'InitiateCheckout';
                break;
            case 'add_shipping_info':
                $metaEventName = 'AddShippingInfo';
                break;
            case 'add_payment_info':
                $metaEventName = 'AddPaymentInfo';
                break;
            case 'remove_from_cart':
                $metaEventName = 'Custom';
                $metaCustomName = 'RemoveFromCart';
                break;
            default:
                $metaEventName = 'Custom';
                $metaCustomName = $canonicalEvent;
                break;
        }

        $meta = [
            'event_name' => $metaEventName,
            'content_type' => $isList ? 'product_group' : 'product',
            'content_ids' => $metaContentIds,
            'contents' => $metaContents,
            'currency' => $currencyCode,
            'value' => $val,
            'num_items' => ($totalQuantity > 0) ? $totalQuantity : 1,
        ];
        if (!empty($metaCustomName)) {
            $meta['custom_event_name'] = $metaCustomName;
        }
        if (!empty($options['order_id'])) {
            $meta['order_id'] = (string)$options['order_id'];
        }
        if (!empty($options['search_term'])) {
            $meta['search_string'] = (string)$options['search_term'];
        }
        if (!empty($options['category_name'])) {
            $meta['content_category'] = (string)$options['category_name'];
        }
        if ($isSingleProduct && !empty($items[0]['item_name'])) {
            $meta['content_name'] = (string)$items[0]['item_name'];
            if (empty($meta['content_category']) && !empty($items[0]['item_category'])) {
                $meta['content_category'] = (string)$items[0]['item_category'];
            }
        }
        if (!empty($options['meta_user_data']) && is_array($options['meta_user_data'])) {
            $meta['user_data'] = $options['meta_user_data'];
        } elseif (!empty($options['user_data']) && is_array($options['user_data'])) {
            $hashedMetaUser = CustomerDataEngine::hashMetaUserData($options['user_data']);
            if (!empty($hashedMetaUser)) {
                $meta['user_data'] = $hashedMetaUser;
            }
        } elseif (isset($this->context->customer) && Validate::isLoadedObject($this->context->customer)) {
            $addr = null;
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart) && !empty($this->context->cart->id_address_delivery)) {
                $addr = new Address((int)$this->context->cart->id_address_delivery);
            }
            $mUserData = CustomerDataEngine::buildMetaUserData($this->context->customer, $addr);
            if (!empty($mUserData)) {
                $meta['user_data'] = $mUserData;
            }
        }

        // TikTok Event Name Resolution
        $tiktokEventName = 'Custom';
        $tiktokCustomName = null;

        switch ($canonicalEvent) {
            case 'view_item':
            case 'select_item':
            case 'view_cart':
            case 'view_item_list':
                $tiktokEventName = 'ViewContent';
                break;
            case 'add_to_cart':
                $tiktokEventName = 'AddToCart';
                break;
            case 'purchase':
                $tiktokEventName = 'CompletePayment';
                break;
            case 'search':
            case 'view_search_results':
                $tiktokEventName = 'Search';
                break;
            case 'add_to_wishlist':
                $tiktokEventName = 'AddToWishlist';
                break;
            case 'begin_checkout':
                $tiktokEventName = 'InitiateCheckout';
                break;
            case 'add_shipping_info':
                $tiktokEventName = 'AddShippingInfo';
                break;
            case 'add_payment_info':
                $tiktokEventName = 'AddPaymentInfo';
                break;
            case 'remove_from_cart':
                $tiktokEventName = 'Custom';
                $tiktokCustomName = 'RemoveFromCart';
                break;
            default:
                $tiktokEventName = 'Custom';
                $tiktokCustomName = $canonicalEvent;
                break;
        }

        $tiktok = [
            'event_name' => $tiktokEventName,
            'content_type' => $isList ? 'product_group' : 'product',
            'content_id' => !empty($metaContentIds) ? $metaContentIds[0] : '',
            'content_ids' => $metaContentIds,
            'contents' => $tiktokContents,
            'currency' => $currencyCode,
            'value' => $val,
            'quantity' => ($totalQuantity > 0) ? $totalQuantity : 1,
        ];
        if (!empty($tiktokCustomName)) {
            $tiktok['custom_event_name'] = $tiktokCustomName;
        }
        if (!empty($options['order_id'])) {
            $tiktok['order_id'] = (string)$options['order_id'];
        }
        if (!empty($options['search_term'])) {
            $tiktok['search_string'] = (string)$options['search_term'];
            $tiktok['query'] = (string)$options['search_term'];
        }
        if (!empty($options['category_name'])) {
            $tiktok['content_category'] = (string)$options['category_name'];
        }
        if ($isSingleProduct && !empty($items[0]['item_name'])) {
            $tiktok['content_name'] = (string)$items[0]['item_name'];
            if (empty($tiktok['content_category']) && !empty($items[0]['item_category'])) {
                $tiktok['content_category'] = (string)$items[0]['item_category'];
            }
        }
        if (!empty($options['tiktok_user_data']) && is_array($options['tiktok_user_data'])) {
            $tiktok['user_data'] = $options['tiktok_user_data'];
        } elseif (!empty($options['user_data']) && is_array($options['user_data'])) {
            $hashedTikTokUser = CustomerDataEngine::hashTikTokUserData($options['user_data']);
            if (!empty($hashedTikTokUser)) {
                $tiktok['user_data'] = $hashedTikTokUser;
            }
        } elseif (isset($this->context->customer) && Validate::isLoadedObject($this->context->customer)) {
            $addr = null;
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart) && !empty($this->context->cart->id_address_delivery)) {
                $addr = new Address((int)$this->context->cart->id_address_delivery);
            }
            $tUserData = CustomerDataEngine::buildTikTokUserData($this->context->customer, $addr);
            if (!empty($tUserData)) {
                $tiktok['user_data'] = $tUserData;
            }
        }

        // Google Ads Event Name Resolution
        $gadsEventName = $canonicalEvent;
        switch ($canonicalEvent) {
            case 'view_item':
            case 'select_item':
                $gadsEventName = 'view_item';
                break;
            case 'view_cart':
                $gadsEventName = 'view_cart';
                break;
            case 'view_item_list':
                $gadsEventName = 'view_item_list';
                break;
            case 'search':
            case 'view_search_results':
                $gadsEventName = 'view_search_results';
                break;
            case 'add_to_cart':
                $gadsEventName = 'add_to_cart';
                break;
            case 'remove_from_cart':
                $gadsEventName = 'remove_from_cart';
                break;
            case 'begin_checkout':
                $gadsEventName = 'begin_checkout';
                break;
            case 'add_shipping_info':
                $gadsEventName = 'add_shipping_info';
                break;
            case 'add_payment_info':
                $gadsEventName = 'add_payment_info';
                break;
            case 'purchase':
                $gadsEventName = 'purchase';
                break;
            case 'add_to_wishlist':
                $gadsEventName = 'add_to_wishlist';
                break;
            default:
                $gadsEventName = $canonicalEvent;
                break;
        }

        $dynamicRemarketing = [
            'event_name' => $gadsEventName,
            'value' => $val,
            'currency' => $currencyCode,
            'items' => $googleDynamicItems,
            'ecomm_pagetype' => $pageType,
            'ecomm_prodid' => $isSingleProduct ? (isset($googleProdIds[0]) ? $googleProdIds[0] : '') : $googleProdIds,
            'ecomm_totalvalue' => $val,
        ];
        if (!empty($options['category_name'])) {
            $dynamicRemarketing['ecomm_category'] = (string)$options['category_name'];
        }
        if (!empty($options['search_term'])) {
            $dynamicRemarketing['search_term'] = (string)$options['search_term'];
        }

        $googleAds = [
            'event_name' => $gadsEventName,
            'dynamic_remarketing' => $dynamicRemarketing,
        ];

        $cartDataEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_GADS_CART_DATA') !== '0') : true;
        if ($cartDataEnabled && !empty($googleCartDataItems)) {
            $cartData = [
                'event_name' => $gadsEventName,
                'value' => $val,
                'currency' => $currencyCode,
                'items' => $googleCartDataItems,
            ];
            $txId = !empty($options['transaction_id']) ? (string)$options['transaction_id'] : (!empty($options['order_id']) ? (string)$options['order_id'] : '');
            if (!empty($txId)) {
                $cartData['transaction_id'] = $txId;
            }
            if (isset($options['discount']) && (float)$options['discount'] > 0) {
                $cartData['discount'] = (float)$options['discount'];
            }
            $gadsMerchantId = !empty($options['aw_merchant_id']) 
                ? (string)$options['aw_merchant_id'] 
                : (class_exists('Configuration') ? (string)Configuration::get('PS_GTM_ADV_GADS_MERCHANT_ID') : '');
            if (!empty($gadsMerchantId)) {
                $cartData['aw_merchant_id'] = $gadsMerchantId;
            }
            if (!empty($options['aw_feed_country'])) {
                $cartData['aw_feed_country'] = (string)$options['aw_feed_country'];
            }
            if (!empty($options['aw_feed_language'])) {
                $cartData['aw_feed_language'] = (string)$options['aw_feed_language'];
            }
            if (isset($options['new_customer'])) {
                $cartData['new_customer'] = (bool)$options['new_customer'];
            }
            if (isset($options['customer_lifetime_value'])) {
                $cartData['customer_lifetime_value'] = (float)$options['customer_lifetime_value'];
            }
            $googleAds['cart_data'] = $cartData;
        }

        // Pinterest Event Name and Schema Resolution
        $pinterestEventName = 'Custom';
        $pinterestCustomName = null;

        switch ($canonicalEvent) {
            case 'view_item':
            case 'select_item':
                $pinterestEventName = 'PageVisit';
                break;
            case 'view_cart':
                $pinterestEventName = 'Custom';
                $pinterestCustomName = 'ViewCart';
                break;
            case 'view_item_list':
                $pinterestEventName = 'ViewCategory';
                break;
            case 'add_to_cart':
                $pinterestEventName = 'AddToCart';
                break;
            case 'purchase':
                $pinterestEventName = 'Checkout';
                break;
            case 'search':
            case 'view_search_results':
                $pinterestEventName = 'Search';
                break;
            case 'begin_checkout':
                $pinterestEventName = 'InitiateCheckout';
                break;
            case 'add_payment_info':
                $pinterestEventName = 'AddPaymentInfo';
                break;
            case 'add_to_wishlist':
                $pinterestEventName = 'AddToWishList';
                break;
            case 'remove_from_cart':
                $pinterestEventName = 'Custom';
                $pinterestCustomName = 'RemoveFromCart';
                break;
            case 'add_shipping_info':
                $pinterestEventName = 'Custom';
                $pinterestCustomName = 'AddShippingInfo';
                break;
            default:
                $pinterestEventName = 'Custom';
                $pinterestCustomName = $canonicalEvent;
                break;
        }

        $pinterest = [
            'event_name' => $pinterestEventName,
            'currency' => $currencyCode,
            'value' => $val,
            'order_quantity' => ($totalQuantity > 0) ? $totalQuantity : 1,
            'product_ids' => $pinterestProdIds,
            'line_items' => $pinterestLineItems,
        ];
        if (!empty($pinterestCustomName)) {
            $pinterest['custom_event_name'] = $pinterestCustomName;
        }
        if (!empty($options['order_id'])) {
            $pinterest['order_id'] = (string)$options['order_id'];
        }
        if (!empty($options['search_term'])) {
            $pinterest['search_query'] = (string)$options['search_term'];
        }
        if (!empty($options['category_name'])) {
            $pinterest['category_name'] = (string)$options['category_name'];
            $pinterest['product_category'] = (string)$options['category_name'];
        }
        if (!empty($options['property'])) {
            $pinterest['property'] = (string)$options['property'];
        }
        if (!empty($options['pinterest_user_data']) && is_array($options['pinterest_user_data'])) {
            $pinterest['user_data'] = $options['pinterest_user_data'];
        } elseif (!empty($options['user_data']) && is_array($options['user_data'])) {
            $hashedUserData = CustomerDataEngine::hashPinterestUserData($options['user_data']);
            if (!empty($hashedUserData)) {
                $pinterest['user_data'] = $hashedUserData;
            }
        } elseif (isset($this->context->customer) && Validate::isLoadedObject($this->context->customer)) {
            $addr = null;
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart) && !empty($this->context->cart->id_address_delivery)) {
                $addr = new Address((int)$this->context->cart->id_address_delivery);
            }
            $pUserData = CustomerDataEngine::buildPinterestUserData($this->context->customer, $addr);
            if (!empty($pUserData)) {
                $pinterest['user_data'] = $pUserData;
            }
        }

        // Microsoft Advertising (Bing Ads & UET) Event Action and Schema Resolution
        $msftEventAction = $canonicalEvent;
        switch ($canonicalEvent) {
            case 'view_item':
            case 'select_item':
                $msftEventAction = 'view_item';
                break;
            case 'view_cart':
                $msftEventAction = 'view_cart';
                break;
            case 'view_item_list':
                $msftEventAction = 'view_item_list';
                break;
            case 'search':
            case 'view_search_results':
                $msftEventAction = 'view_search_results';
                break;
            case 'add_to_cart':
                $msftEventAction = 'add_to_cart';
                break;
            case 'remove_from_cart':
                $msftEventAction = 'remove_from_cart';
                break;
            case 'begin_checkout':
                $msftEventAction = 'begin_checkout';
                break;
            case 'add_shipping_info':
                $msftEventAction = 'checkout_progress';
                break;
            case 'add_payment_info':
                $msftEventAction = 'add_payment_info';
                break;
            case 'purchase':
                $msftEventAction = 'purchase';
                break;
            case 'add_to_wishlist':
                $msftEventAction = 'add_to_wishlist';
                break;
            default:
                $msftEventAction = $canonicalEvent;
                break;
        }

        $microsoftAds = [
            'event_action' => $msftEventAction,
            'event_category' => 'ecommerce',
            'revenue_value' => $val,
            'currency' => $currencyCode,
            'items' => $microsoftItems,
        ];

        if (!empty($options['order_id'])) {
            $microsoftAds['event_label'] = 'Order ' . (string)$options['order_id'];
        }

        if (!empty($options['search_term'])) {
            $microsoftAds['search_term'] = (string)$options['search_term'];
        }

        if ($canonicalEvent === 'begin_checkout') {
            $microsoftAds['checkout_step'] = 1;
        } elseif ($canonicalEvent === 'add_shipping_info') {
            $microsoftAds['checkout_step'] = 3;
            if (!empty($options['property'])) {
                $microsoftAds['checkout_option'] = (string)$options['property'];
            }
        } elseif ($canonicalEvent === 'add_payment_info') {
            $microsoftAds['checkout_step'] = 4;
            if (!empty($options['property'])) {
                $microsoftAds['checkout_option'] = (string)$options['property'];
            }
        }

        if (!empty($options['coupon'])) {
            $microsoftAds['coupon'] = (string)$options['coupon'];
        }

        if ($canonicalEvent === 'purchase') {
            $txId = !empty($options['transaction_id']) ? (string)$options['transaction_id'] : (!empty($options['order_id']) ? (string)$options['order_id'] : '');
            if (!empty($txId)) {
                $microsoftAds['transaction_id'] = $txId;
                $microsoftAds['event_id'] = $txId . '_' . (isset($options['timestamp']) ? $options['timestamp'] : time());
            }
            if (isset($options['tax'])) {
                $microsoftAds['tax'] = (float)$options['tax'];
            }
            if (isset($options['shipping'])) {
                $microsoftAds['shipping'] = (float)$options['shipping'];
            }
            if (!empty($options['affiliation'])) {
                $microsoftAds['affiliation'] = (string)$options['affiliation'];
            }
        }

        $msftRemarketing = [
            'ecomm_pagetype' => $pageType,
            'ecomm_prodid' => !empty($microsoftProdIds) ? array_values(array_map('strval', $microsoftProdIds)) : [],
            'ecomm_totalvalue' => $val,
        ];
        if (!empty($options['category_name'])) {
            $msftRemarketing['ecomm_category'] = (string)$options['category_name'];
        }
        if (!empty($options['search_term'])) {
            $msftRemarketing['ecomm_query'] = (string)$options['search_term'];
        }
        $microsoftAds['dynamic_remarketing'] = $msftRemarketing;

        if (!empty($options['microsoft_user_data']) && is_array($options['microsoft_user_data'])) {
            $microsoftAds['pid'] = isset($options['microsoft_user_data']['pid']) ? $options['microsoft_user_data']['pid'] : $options['microsoft_user_data'];
        } elseif (!empty($options['user_data']) && is_array($options['user_data'])) {
            $msftPid = CustomerDataEngine::hashMicrosoftUserData($options['user_data']);
            if (!empty($msftPid['pid'])) {
                $microsoftAds['pid'] = $msftPid['pid'];
            }
        } elseif (isset($this->context->customer) && Validate::isLoadedObject($this->context->customer)) {
            $addr = null;
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart) && !empty($this->context->cart->id_address_delivery)) {
                $addr = new Address((int)$this->context->cart->id_address_delivery);
            }
            $msftPid = CustomerDataEngine::buildMicrosoftUserData($this->context->customer, $addr);
            if (!empty($msftPid['pid'])) {
                $microsoftAds['pid'] = $msftPid['pid'];
            }
        }

        $masterEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_MASTER_ENABLED') !== '0') : true;
        if (!$masterEnabled) {
            return [];
        }

        $metaEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_META_ENABLED') !== '0') : true;
        $tiktokEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_TIKTOK_ENABLED') !== '0') : true;
        $gadsEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_GADS_ENABLED') !== '0') : true;
        $pinterestEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') !== '0') : true;
        $microsoftEnabled = class_exists('Configuration') ? (Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') !== '0') : true;

        $metaEvents = (class_exists('Configuration') && Configuration::get('PS_GTM_ADV_META_EVENTS'))
            ? json_decode(Configuration::get('PS_GTM_ADV_META_EVENTS'), true)
            : null;
        $tiktokEvents = (class_exists('Configuration') && Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS'))
            ? json_decode(Configuration::get('PS_GTM_ADV_TIKTOK_EVENTS'), true)
            : null;
        $gadsEvents = (class_exists('Configuration') && Configuration::get('PS_GTM_ADV_GADS_EVENTS'))
            ? json_decode(Configuration::get('PS_GTM_ADV_GADS_EVENTS'), true)
            : null;
        $pinterestEvents = (class_exists('Configuration') && Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS'))
            ? json_decode(Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS'), true)
            : null;
        $microsoftEvents = (class_exists('Configuration') && Configuration::get('PS_GTM_ADV_MICROSOFT_EVENTS'))
            ? json_decode(Configuration::get('PS_GTM_ADV_MICROSOFT_EVENTS'), true)
            : null;

        $isSearchListing = ($canonicalEvent === 'view_item_list' && $pageType === 'searchresults');

        $result = [];
        $isMetaActive = ($metaEvents === null) ? true : (is_array($metaEvents) && in_array($canonicalEvent, $metaEvents));
        if (!$isSearchListing && $metaEnabled && $isMetaActive) {
            $result['meta'] = $meta;
        }

        $isTiktokActive = ($tiktokEvents === null) ? true : (is_array($tiktokEvents) && in_array($canonicalEvent, $tiktokEvents));
        if (!$isSearchListing && $tiktokEnabled && $isTiktokActive) {
            $result['tiktok'] = $tiktok;
        }

        $isGadsActive = ($gadsEvents === null) ? true : (is_array($gadsEvents) && in_array($canonicalEvent, $gadsEvents));
        if ($gadsEnabled && $isGadsActive) {
            $result['google_ads'] = $googleAds;
        }

        $isPinterestActive = ($pinterestEvents === null) ? true : (is_array($pinterestEvents) && in_array($canonicalEvent, $pinterestEvents));
        if (!$isSearchListing && $pinterestEnabled && $isPinterestActive) {
            $result['pinterest'] = $pinterest;
        }

        $isMicrosoftActive = ($microsoftEvents === null) ? true : (is_array($microsoftEvents) && in_array($canonicalEvent, $microsoftEvents));
        if ($microsoftEnabled && $isMicrosoftActive) {
            $result['microsoft_ads'] = $microsoftAds;
        }

        return $result;
    }

    public function buildPagePayload($controller, $params)
    {
        if (self::isSearchController($controller, $this->context)) {
            $query = self::getSearchQuery();
            return $this->buildViewSearchResultsPayload($query, null, [], true);
        }

        switch ($controller) {
            case 'product':
                return $this->buildViewItemPayload();
            case 'category':
            case 'manufacturer':
            case 'index':
            case 'prices-drop':
            case 'new-products':
            case 'best-sales':
                return $this->buildViewItemListPayload($controller);
            case 'cart':
                return $this->buildViewCartPayload();
            case 'order':
                return $this->buildBeginCheckoutPayload();
            default:
                return null;
        }
    }

    public function buildViewItemPayload()
    {
        $idProduct = (int)Tools::getValue('id_product');
        if (!$idProduct) {
            return null;
        }

        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $idShop = $this->context->shop ? (int)$this->context->shop->id : 1;

        $product = new Product($idProduct, true, $idLanguage, $idShop);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        // Accurately determine the selected combination/attribute from backend parameters
        $idProductAttribute = (int)Tools::getValue('id_product_attribute');
        if (!$idProductAttribute) {
            $idProductAttribute = (int)Product::getDefaultAttribute($idProduct);
        }

        $item = $this->formatItem($product, $idProductAttribute, 1);
        $price = (float)$item['price'];
        $currency = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

        $platformNamespaces = $this->buildPlatformNamespaces('view_item', [$item], $currency, $price, [
            'category_name' => !empty($item['item_category']) ? $item['item_category'] : '',
        ]);

        return array_merge([
            'event' => $this->getEventName('view_item'),
            'original_event' => 'view_item',
            'ecommerce' => [
                'currency' => $currency,
                'value' => (float)$price,
                'items' => [$item],
            ],
        ], $platformNamespaces);
    }

    public function buildAddToWishlistPayload($idProduct, $idProductAttribute = 0, $quantity = 1)
    {
        return WishlistIntegrationLayer::buildAddToWishlistPayload($this->context, $idProduct, $idProductAttribute, $quantity, $this->prefix);
    }

    public function buildViewItemListPayload($controller)
    {
        $listId = 'list_' . $controller;
        $listName = ucfirst($controller) . ' Page';

        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);
        $items = [];

        if ($controller === 'category') {
            $category = (isset($this->context->controller->category) && Validate::isLoadedObject($this->context->controller->category))
                ? $this->context->controller->category
                : (method_exists($this->context->controller, 'getCategory') ? $this->context->controller->getCategory() : null);

            $idCategory = ($category && Validate::isLoadedObject($category)) ? (int)$category->id : (int)Tools::getValue('id_category');
            if ($idCategory) {
                if (!$category || !Validate::isLoadedObject($category)) {
                    $category = new Category($idCategory, $idLanguage);
                }
                if (Validate::isLoadedObject($category)) {
                    $listId = 'category_' . $idCategory;
                    $listName = (string)$category->name;
                    $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
                    $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
                    $prods = $category->getProducts($idLanguage, $p, $n, 'position', 'asc', false, true);
                    if (!empty($prods) && is_array($prods)) {
                        $idx = 1;
                        foreach ($prods as $pItem) {
                            $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                            if (!empty($pItem['manufacturer_name'])) {
                                $productObj->manufacturer_name = $pItem['manufacturer_name'];
                            }
                            $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
                            $item['item_list_id'] = $listId;
                            $item['item_list_name'] = $listName;
                            $items[] = $item;
                        }
                    }
                }
            }
        } elseif ($controller === 'index') {
            $listId = 'home_featured';
            $listName = 'Home Page - Featured Products';
            $idHomeCategory = (int)Configuration::get('PS_HOME_CATEGORY') ?: (int)$this->context->shop->getCategory();
            if ($idHomeCategory) {
                $category = new Category($idHomeCategory, $idLanguage);
                if (Validate::isLoadedObject($category)) {
                    $n = (int)Configuration::get('HOME_FEATURED_NBR') > 0 ? (int)Configuration::get('HOME_FEATURED_NBR') : 16;
                    $prods = $category->getProducts($idLanguage, 1, $n, 'position', 'asc', false, true);
                    if (!empty($prods) && is_array($prods)) {
                        $idx = 1;
                        foreach ($prods as $pItem) {
                            $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                            if (!empty($pItem['manufacturer_name'])) {
                                $productObj->manufacturer_name = $pItem['manufacturer_name'];
                            }
                            $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
                            $item['item_list_id'] = $listId;
                            $item['item_list_name'] = $listName;
                            $items[] = $item;
                        }
                    }
                }
            }
        } elseif ($controller === 'prices-drop') {
            $listId = 'on_sale';
            $listName = 'On Sale Products';
            $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
            $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
            if (class_exists('Product') && method_exists('Product', 'getPricesDrop')) {
                $prods = Product::getPricesDrop($idLanguage, $p, $n);
                if (!empty($prods) && is_array($prods)) {
                    $idx = 1;
                    foreach ($prods as $pItem) {
                        $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                        if (!empty($pItem['manufacturer_name'])) {
                            $productObj->manufacturer_name = $pItem['manufacturer_name'];
                        }
                        $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                        $item = $this->formatItem($productObj, 0, 1, $price);
                        $item['index'] = $idx++;
                        $item['item_list_id'] = $listId;
                        $item['item_list_name'] = $listName;
                        $items[] = $item;
                    }
                }
            }
        } elseif ($controller === 'new-products') {
            $listId = 'new_products';
            $listName = 'New Products';
            $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
            $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
            if (class_exists('Product') && method_exists('Product', 'getNewProducts')) {
                $prods = Product::getNewProducts($idLanguage, $p, $n);
                if (!empty($prods) && is_array($prods)) {
                    $idx = 1;
                    foreach ($prods as $pItem) {
                        $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                        if (!empty($pItem['manufacturer_name'])) {
                            $productObj->manufacturer_name = $pItem['manufacturer_name'];
                        }
                        $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                        $item = $this->formatItem($productObj, 0, 1, $price);
                        $item['index'] = $idx++;
                        $item['item_list_id'] = $listId;
                        $item['item_list_name'] = $listName;
                        $items[] = $item;
                    }
                }
            }
        } elseif ($controller === 'best-sales') {
            $listId = 'best_sellers';
            $listName = 'Best Sellers';
            $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
            $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
            if (class_exists('ProductSale') && method_exists('ProductSale', 'getBestSales')) {
                $prods = ProductSale::getBestSales($idLanguage, $p, $n);
                if (!empty($prods) && is_array($prods)) {
                    $idx = 1;
                    foreach ($prods as $pItem) {
                        $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                        if (!empty($pItem['manufacturer_name'])) {
                            $productObj->manufacturer_name = $pItem['manufacturer_name'];
                        }
                        $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                        $item = $this->formatItem($productObj, 0, 1, $price);
                        $item['index'] = $idx++;
                        $item['item_list_id'] = $listId;
                        $item['item_list_name'] = $listName;
                        $items[] = $item;
                    }
                }
            }
        } elseif ($controller === 'manufacturer') {
            $idManufacturer = (int)Tools::getValue('id_manufacturer');
            if ($idManufacturer) {
                $manufacturer = new Manufacturer($idManufacturer, $idLanguage);
                if (Validate::isLoadedObject($manufacturer)) {
                    $listId = 'manufacturer_' . $idManufacturer;
                    $listName = (string)$manufacturer->name;
                    $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
                    $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
                    $prods = $manufacturer->getProducts($idManufacturer, $idLanguage, $p, $n, 'name', 'asc');
                    if (!empty($prods) && is_array($prods)) {
                        $idx = 1;
                        foreach ($prods as $pItem) {
                            $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                            $productObj->manufacturer_name = (string)$manufacturer->name;
                            $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
                            $item['item_list_id'] = $listId;
                            $item['item_list_name'] = $listName;
                            $items[] = $item;
                        }
                    }
                }
            }
        }

        if (empty($items)) {
            return null;
        }

        $currencyCode = ($this->context->currency && !empty($this->context->currency->iso_code))
            ? (string)$this->context->currency->iso_code
            : 'EUR';

        $totalListValue = 0.0;
        foreach ($items as $it) {
            $totalListValue += (float)$it['price'];
        }
        $totalListValue = PricingEngine::roundHalfUp($totalListValue, $currencyPrecision);

        $platformNamespaces = $this->buildPlatformNamespaces('view_item_list', $items, $currencyCode, $totalListValue, [
            'list_id' => $listId,
            'list_name' => $listName,
            'category_name' => ($controller === 'category') ? $listName : '',
        ]);

        return array_merge([
            'event' => $this->getEventName('view_item_list'),
            'original_event' => 'view_item_list',
            'ecommerce' => [
                'currency' => $currencyCode,
                'value' => (float)$totalListValue,
                'item_list_id' => $listId,
                'item_list_name' => $listName,
                'items' => $items,
            ],
        ], $platformNamespaces);
    }

    /**
     * Builds official GA4 view_search_results payload with authoritative search_term,
     * search_results_count, matching catalog items, and ad platform namespaces (Meta, TikTok, Pinterest).
     * Google Ads Dynamic Remarketing is strictly excluded per custom remarketing architecture.
     *
     * @param string|null $searchTerm
     * @param int|null $resultsCount
     * @param array $items
     * @param bool $includeEcommerceItems
     * @return array|null
     */
    public function buildViewSearchResultsPayload($searchTerm = null, $resultsCount = null, array $items = [], $includeEcommerceItems = true)
    {
        $query = ($searchTerm !== null && trim((string)$searchTerm) !== '')
            ? trim((string)$searchTerm)
            : self::getSearchQuery();

        if ($query === '') {
            return null;
        }

        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);
        $totalResultsCount = 0;

        if (empty($items)) {
            $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
            $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
            if (class_exists('Search') && method_exists('Search', 'find')) {
                $searchRes = Search::find($idLanguage, $query, $p, $n, 'position', 'desc');
                if (!empty($searchRes['total'])) {
                    $totalResultsCount = (int)$searchRes['total'];
                }
                if (!empty($searchRes['result']) && is_array($searchRes['result'])) {
                    $idx = 1;
                    foreach ($searchRes['result'] as $pItem) {
                        $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                        if (!empty($pItem['manufacturer_name'])) {
                            $productObj->manufacturer_name = $pItem['manufacturer_name'];
                        }
                        $price = isset($pItem['price_wt']) ? PricingEngine::roundHalfUp($pItem['price_wt'], $currencyPrecision) : PricingEngine::getCustomerPrice($productObj, 0, 1, $this->context);
                        $item = $this->formatItem($productObj, 0, 1, $price);
                        $item['index'] = $idx++;
                        $items[] = $item;
                    }
                }
            }
        }

        if ($resultsCount !== null) {
            $totalResultsCount = (int)$resultsCount;
        } elseif ($totalResultsCount === 0 && !empty($items)) {
            $totalResultsCount = count($items);
        }

        $currencyCode = ($this->context->currency && !empty($this->context->currency->iso_code))
            ? (string)$this->context->currency->iso_code
            : 'EUR';

        $totalValue = 0.0;
        foreach ($items as $it) {
            $totalValue += (float)$it['price'];
        }
        $totalValue = PricingEngine::roundHalfUp($totalValue, $currencyPrecision);

        $platformNamespaces = $this->buildPlatformNamespaces('view_search_results', $items, $currencyCode, $totalValue, [
            'search_term' => $query,
            'results_count' => $totalResultsCount,
        ]);

        $ecommerceData = [
            'currency' => $currencyCode,
            'search_term' => $query,
        ];
        if ($includeEcommerceItems && !empty($items)) {
            $ecommerceData['items'] = $items;
        }

        return array_merge([
            'event' => $this->getEventName('view_search_results'),
            'original_event' => 'view_search_results',
            'search_term' => $query,
            'search_results_count' => (int)$totalResultsCount,
            'ecommerce' => $ecommerceData,
        ], $platformNamespaces);
    }

    public function buildViewCartPayload()
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return null;
        }

        $cartProducts = $cart->getProducts(true);
        $items = [];
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
            if (!empty($p['manufacturer_name'])) {
                $productObj->manufacturer_name = $p['manufacturer_name'];
            }
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        $itemsSum = 0.0;
        foreach ($items as $it) {
            $itemsSum += (float)$it['price'] * (int)$it['quantity'];
        }
        $totalValue = PricingEngine::roundHalfUp($itemsSum, $currencyPrecision);
        $currencyCode = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

        $platformNamespaces = $this->buildPlatformNamespaces('view_cart', $items, $currencyCode, $totalValue);

        return array_merge([
            'event' => $this->getEventName('view_cart'),
            'original_event' => 'view_cart',
            'ecommerce' => [
                'currency' => $currencyCode,
                'value' => $totalValue,
                'items' => $items,
            ],
        ], $platformNamespaces);
    }

    public function buildBeginCheckoutPayload()
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return null;
        }

        $cartProducts = $cart->getProducts(true);
        $items = [];
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
            if (!empty($p['manufacturer_name'])) {
                $productObj->manufacturer_name = $p['manufacturer_name'];
            }
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        $itemsSum = 0.0;
        foreach ($items as $it) {
            $itemsSum += (float)$it['price'] * (int)$it['quantity'];
        }
        $totalValue = PricingEngine::roundHalfUp($itemsSum, $currencyPrecision);
        $currencyCode = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

        $platformNamespaces = $this->buildPlatformNamespaces('begin_checkout', $items, $currencyCode, $totalValue);

        $ecommerce = [
            'currency' => $currencyCode,
            'value' => $totalValue,
            'items' => $items,
        ];

        $cartRules = $cart->getCartRules();
        if (!empty($cartRules)) {
            $codes = array_filter(array_map(function($r) { return !empty($r['code']) ? trim($r['code']) : ''; }, $cartRules));
            if (!empty($codes)) {
                $ecommerce['coupon'] = implode(', ', $codes);
            }
        }

        return array_merge([
            'event' => $this->getEventName('begin_checkout'),
            'original_event' => 'begin_checkout',
            'ecommerce' => $ecommerce,
        ], $platformNamespaces);
    }

    public function buildAddShippingInfoPayload($shippingTier = '')
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return null;
        }

        $cartProducts = $cart->getProducts(true);
        $items = [];
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
            if (!empty($p['manufacturer_name'])) {
                $productObj->manufacturer_name = $p['manufacturer_name'];
            }
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        $itemsSum = 0.0;
        foreach ($items as $it) {
            $itemsSum += (float)$it['price'] * (int)$it['quantity'];
        }
        $totalValue = PricingEngine::roundHalfUp($itemsSum, $currencyPrecision);
        $currencyCode = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

        if (empty($shippingTier)) {
            $idCarrier = (int)$cart->id_carrier;
            if ($idCarrier > 0) {
                $carrier = new Carrier($idCarrier, $idLanguage);
                if (Validate::isLoadedObject($carrier)) {
                    $shippingTier = (string)$carrier->name;
                }
            }
        }
        if (empty($shippingTier)) {
            $shippingTier = 'Standard Delivery';
        }

        $ecommerce = [
            'currency' => $currencyCode,
            'value' => $totalValue,
            'shipping_tier' => $shippingTier,
            'items' => $items,
        ];

        $cartRules = $cart->getCartRules();
        if (!empty($cartRules)) {
            $codes = array_filter(array_map(function($r) { return !empty($r['code']) ? trim($r['code']) : ''; }, $cartRules));
            if (!empty($codes)) {
                $ecommerce['coupon'] = implode(', ', $codes);
            }
        }

        $platformNamespaces = $this->buildPlatformNamespaces('add_shipping_info', $items, $currencyCode, $totalValue, [
            'property' => !empty($ecommerce['shipping_tier']) ? (string)$ecommerce['shipping_tier'] : '',
        ]);

        return array_merge([
            'event' => $this->getEventName('add_shipping_info'),
            'original_event' => 'add_shipping_info',
            'ecommerce' => $ecommerce,
        ], $platformNamespaces);
    }

    public function buildAddPaymentInfoPayload($paymentType = '')
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return null;
        }

        $cartProducts = $cart->getProducts(true);
        $items = [];
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
            if (!empty($p['manufacturer_name'])) {
                $productObj->manufacturer_name = $p['manufacturer_name'];
            }
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        $itemsSum = 0.0;
        foreach ($items as $it) {
            $itemsSum += (float)$it['price'] * (int)$it['quantity'];
        }
        $totalValue = PricingEngine::roundHalfUp($itemsSum, $currencyPrecision);
        $currencyCode = $this->context->currency ? (string)$this->context->currency->iso_code : 'EUR';

        if (empty($paymentType)) {
            $paymentType = 'Online Payment';
        }

        $ecommerce = [
            'currency' => $currencyCode,
            'value' => $totalValue,
            'payment_type' => $paymentType,
            'items' => $items,
        ];

        $cartRules = $cart->getCartRules();
        if (!empty($cartRules)) {
            $codes = array_filter(array_map(function($r) { return !empty($r['code']) ? trim($r['code']) : ''; }, $cartRules));
            if (!empty($codes)) {
                $ecommerce['coupon'] = implode(', ', $codes);
            }
        }

        $platformNamespaces = $this->buildPlatformNamespaces('add_payment_info', $items, $currencyCode, $totalValue, [
            'property' => !empty($ecommerce['payment_type']) ? (string)$ecommerce['payment_type'] : '',
        ]);

        return array_merge([
            'event' => $this->getEventName('add_payment_info'),
            'original_event' => 'add_payment_info',
            'ecommerce' => $ecommerce,
        ], $platformNamespaces);
    }

    public function buildPurchasePayload(Order $order)
    {
        $orderProducts = $order->getProducts();
        $items = [];
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

        foreach ($orderProducts as $p) {
            $productObj = new Product((int)$p['product_id'], false, $idLanguage);
            $idAttr = isset($p['product_attribute_id']) ? (int)$p['product_attribute_id'] : 0;
            $identifiers = ProductIdentityResolver::resolveProductIdentifiers($productObj, $idAttr, $idLanguage);

            $orderRef = !empty($p['product_reference']) ? trim($p['product_reference']) : '';
            $skuId = !empty($orderRef) ? $orderRef : $identifiers['sku_id'];

            // Authoritative brand resolution: query Manufacturer directly if property not hydrated
            $brand = '';
            if (!empty($productObj->manufacturer_name)) {
                $brand = (string)$productObj->manufacturer_name;
            } elseif (!empty($productObj->id_manufacturer)) {
                $brand = (string)Manufacturer::getNameById((int)$productObj->id_manufacturer);
            }

            $item = [
                'item_id' => (string)$productObj->id, // Base product ID
                'variant_id' => $identifiers['variant_id'],
                'sku_id' => $skuId,
                'item_name' => (string)$p['product_name'],
                'item_brand' => $brand,
                'item_category' => '',
                'item_variant' => $identifiers['item_variant'],
                'price' => PricingEngine::roundHalfUp((float)$p['unit_price_tax_incl'], $currencyPrecision),
                'quantity' => (int)$p['product_quantity'],
            ];

            $category = new Category((int)$productObj->id_category_default, $idLanguage);
            if (Validate::isLoadedObject($category)) {
                $item['item_category'] = (string)$category->name;
            }

            $items[] = $item;
        }

        $customer = new Customer((int)$order->id_customer);
        $address = new Address((int)$order->id_address_invoice);
        $userData = CustomerDataEngine::buildCustomerPayload($customer, $address);
        $lifecycleMetrics = CustomerDataEngine::resolveCustomerLifecycleMetrics($customer, $order);

        $orderTax = PricingEngine::roundHalfUp((float)($order->total_paid_tax_incl - $order->total_paid_tax_excl), $currencyPrecision);
        $orderShipping = PricingEngine::roundHalfUp((float)$order->total_shipping_tax_incl, $currencyPrecision);
        $orderTotal = PricingEngine::roundHalfUp((float)$order->total_paid_tax_incl, $currencyPrecision);

        // PrestaShop authoritative product subtotal
        $productSubtotal = (isset($order->total_products_wt) && (float)$order->total_products_wt > 0)
            ? PricingEngine::roundHalfUp((float)$order->total_products_wt, $currencyPrecision)
            : 0.0;

        // GA4 item value = sum of (item price * quantity), excluding shipping and separate from tax
        $itemsSum = 0.0;
        foreach ($items as $it) {
            $itemsSum += (float)$it['price'] * (int)$it['quantity'];
        }
        $itemsSum = PricingEngine::roundHalfUp($itemsSum, $currencyPrecision);

        $ga4Value = ($itemsSum > 0) ? $itemsSum : ($productSubtotal > 0 ? $productSubtotal : $orderTotal);
        if ($productSubtotal <= 0) {
            $productSubtotal = $ga4Value;
        }

        $orderCurrency = Currency::getIsoCodeById((int)$order->id_currency);
        if (empty($orderCurrency)) {
            $orderCurrency = $this->context->currency ? $this->context->currency->iso_code : 'EUR';
        }

        $coupon = '';
        $cartRules = $order->getCartRules();
        if (!empty($cartRules) && isset($cartRules[0]['name'])) {
            $coupon = (string)$cartRules[0]['name'];
        }

        $orderReference = !empty($order->reference) ? (string)$order->reference : (string)$order->id;
        $orderDiscount = (isset($order->total_discounts_tax_incl) && (float)$order->total_discounts_tax_incl > 0)
            ? PricingEngine::roundHalfUp((float)$order->total_discounts_tax_incl, $currencyPrecision)
            : 0.0;

        $countryIso = ($address && !empty($address->id_country) && class_exists('Country'))
            ? strtoupper((string)Country::getIsoById((int)$address->id_country))
            : '';
        $langIso = '';
        if (!empty($order->id_lang) && class_exists('Language')) {
            $langIso = strtolower((string)Language::getIsoById((int)$order->id_lang));
        }
        if (empty($langIso) && isset($this->context->language) && !empty($this->context->language->iso_code)) {
            $langIso = strtolower((string)$this->context->language->iso_code);
        }

        $platformNamespaces = $this->buildPlatformNamespaces('purchase', $items, $orderCurrency, $ga4Value, [
            'order_id' => $orderReference,
            'transaction_id' => (string)$order->id,
            'discount' => $orderDiscount,
            'tax' => $orderTax,
            'shipping' => $orderShipping,
            'coupon' => $coupon,
            'affiliation' => (class_exists('Configuration') && Configuration::get('PS_SHOP_NAME')) ? (string)Configuration::get('PS_SHOP_NAME') : '',
            'aw_feed_country' => $countryIso,
            'aw_feed_language' => $langIso,
            'new_customer' => $lifecycleMetrics['new_customer'],
            'customer_lifetime_value' => $lifecycleMetrics['customer_lifetime_value'],
            'user_data' => $userData,
            'meta_user_data' => CustomerDataEngine::buildMetaUserData($customer, $address),
            'pinterest_user_data' => CustomerDataEngine::buildPinterestUserData($customer, $address),
            'tiktok_user_data' => CustomerDataEngine::buildTikTokUserData($customer, $address),
            'microsoft_user_data' => CustomerDataEngine::buildMicrosoftUserData($customer, $address),
        ]);

        $purchasePayload = [
            'event' => $this->getEventName('purchase'),
            'original_event' => 'purchase',
            'new_customer' => $lifecycleMetrics['new_customer'],
            'customer_lifetime_value' => $lifecycleMetrics['customer_lifetime_value'],
            'ecommerce' => [
                'transaction_id' => (string)$order->id,
                'value' => $ga4Value, // GA4 item revenue: sum of items, excluding shipping
                'tax' => $orderTax,
                'shipping' => $orderShipping,
                'currency' => $orderCurrency,
                'coupon' => $coupon,
                'items' => $items,
            ],
            // Custom PrestaShop financial breakdown preserving exact order totals
            'order_totals' => [
                'order_id' => (int)$order->id,
                'order_reference' => (string)$order->reference,
                'product_subtotal' => $productSubtotal,
                'shipping_total' => $orderShipping,
                'tax_total' => $orderTax,
                'order_total' => $orderTotal,
                'currency' => $orderCurrency,
            ],
            'user_data' => array_merge($userData, [
                'new_customer' => $lifecycleMetrics['new_customer'],
                'customer_lifetime_value' => $lifecycleMetrics['customer_lifetime_value'],
                'orders_count' => $lifecycleMetrics['orders_count'],
            ]),
        ];

        if (isset($platformNamespaces['meta'])) {
            $purchasePayload['meta'] = $platformNamespaces['meta'];
        }
        if (isset($platformNamespaces['tiktok'])) {
            $purchasePayload['tiktok'] = $platformNamespaces['tiktok'];
        }
        if (isset($platformNamespaces['google_ads'])) {
            $purchasePayload['google_ads'] = $platformNamespaces['google_ads'];
        }
        if (isset($platformNamespaces['pinterest'])) {
            $purchasePayload['pinterest'] = $platformNamespaces['pinterest'];
        }
        if (isset($platformNamespaces['microsoft_ads'])) {
            $purchasePayload['microsoft_ads'] = $platformNamespaces['microsoft_ads'];
        }

        return $purchasePayload;
    }

    public function formatItem(Product $product, $idAttribute = 0, $quantity = 1, $overridePrice = null)
    {
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $price = $overridePrice !== null 
            ? PricingEngine::roundHalfUp($overridePrice)
            : PricingEngine::getCustomerPrice($product, $idAttribute, $quantity, $this->context);

        $identifiers = ProductIdentityResolver::resolveProductIdentifiers($product, $idAttribute, $idLanguage);
        $category = new Category((int)$product->id_category_default, $idLanguage);

        // Authoritative brand resolution directly from database
        $brand = '';
        if (!empty($product->manufacturer_name)) {
            $brand = (string)$product->manufacturer_name;
        } elseif (!empty($product->id_manufacturer)) {
            $brand = (string)Manufacturer::getNameById((int)$product->id_manufacturer);
        }

        return CanonicalDataModel::formatItem([
            'item_id' => (string)$product->id, // Canonical base product ID
            'variant_id' => $identifiers['variant_id'],
            'sku_id' => $identifiers['sku_id'],
            'item_name' => (string)$product->name,
            'item_brand' => $brand,
            'item_category' => Validate::isLoadedObject($category) ? (string)$category->name : '',
            'item_variant' => $identifiers['item_variant'],
            'price' => (float)$price,
            'quantity' => (int)$quantity,
        ]);
    }
}
