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

    public function buildPagePayload($controller, $params)
    {
        switch ($controller) {
            case 'product':
                return $this->buildViewItemPayload();
            case 'category':
            case 'manufacturer':
            case 'search':
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

        return [
            'event' => $this->getEventName('view_item'),
            'original_event' => 'view_item',
            'ecommerce' => [
                'currency' => $this->context->currency ? $this->context->currency->iso_code : 'EUR',
                'value' => (float)$price,
                'items' => [$item],
            ],
        ];
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
        $items = [];

        if ($controller === 'category') {
            $idCategory = (int)Tools::getValue('id_category');
            if ($idCategory) {
                $category = new Category($idCategory, $idLanguage);
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
                            $price = isset($pItem['price_wt']) ? ProductIdentityResolver::roundHalfUp($pItem['price_wt'], 2) : ProductIdentityResolver::getCustomerPrice($productObj, 0, 1, $this->context);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
                            $item['item_list_id'] = $listId;
                            $item['item_list_name'] = $listName;
                            $items[] = $item;
                        }
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
                            $price = isset($pItem['price_wt']) ? ProductIdentityResolver::roundHalfUp($pItem['price_wt'], 2) : ProductIdentityResolver::getCustomerPrice($productObj, 0, 1, $this->context);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
                            $item['item_list_id'] = $listId;
                            $item['item_list_name'] = $listName;
                            $items[] = $item;
                        }
                    }
                }
            }
        } elseif ($controller === 'search') {
            $query = Tools::getValue('s');
            if ($query) {
                $listId = 'search_results';
                $listName = 'Search: ' . $query;
                $p = max(1, (int)Tools::getValue('page', Tools::getValue('p', 1)));
                $n = (int)Configuration::get('PS_PRODUCTS_PER_PAGE') > 0 ? (int)Configuration::get('PS_PRODUCTS_PER_PAGE') : 24;
                $searchRes = Search::find($idLanguage, $query, $p, $n, 'position', 'desc');
                if (!empty($searchRes['result']) && is_array($searchRes['result'])) {
                    $idx = 1;
                    foreach ($searchRes['result'] as $pItem) {
                        $productObj = new Product((int)$pItem['id_product'], false, $idLanguage);
                        if (!empty($pItem['manufacturer_name'])) {
                            $productObj->manufacturer_name = $pItem['manufacturer_name'];
                        }
                        $price = isset($pItem['price_wt']) ? ProductIdentityResolver::roundHalfUp($pItem['price_wt'], 2) : ProductIdentityResolver::getCustomerPrice($productObj, 0, 1, $this->context);
                        $item = $this->formatItem($productObj, 0, 1, $price);
                        $item['index'] = $idx++;
                        $item['item_list_id'] = $listId;
                        $item['item_list_name'] = $listName;
                        $items[] = $item;
                    }
                }
            }
        }

        $currencyCode = ($this->context->currency && !empty($this->context->currency->iso_code))
            ? (string)$this->context->currency->iso_code
            : 'EUR';
        $currencyPrecision = PlatformAdapter::getCurrencyPrecision($this->context);

        $totalListValue = 0.0;
        foreach ($items as $it) {
            $totalListValue += (float)$it['price'];
        }
        $totalListValue = PricingEngine::roundHalfUp($totalListValue, $currencyPrecision);

        return [
            'event' => $this->getEventName('view_item_list'),
            'original_event' => 'view_item_list',
            'ecommerce' => [
                'currency' => $currencyCode,
                'value' => (float)$totalListValue,
                'item_list_id' => $listId,
                'item_list_name' => $listName,
                'items' => $items,
            ],
        ];
    }

    public function buildViewCartPayload()
    {
        $cart = $this->context->cart;
        if (!Validate::isLoadedObject($cart) || $cart->nbProducts() === 0) {
            return null;
        }

        $cartProducts = $cart->getProducts(true);
        $items = [];
        $totalValue = (float)$cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
            if (!empty($p['manufacturer_name'])) {
                $productObj->manufacturer_name = $p['manufacturer_name'];
            }
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        return [
            'event' => $this->getEventName('view_cart'),
            'original_event' => 'view_cart',
            'ecommerce' => [
                'currency' => $this->context->currency ? $this->context->currency->iso_code : 'EUR',
                'value' => round($totalValue, 2),
                'items' => $items,
            ],
        ];
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

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
            if (!empty($p['manufacturer_name'])) {
                $productObj->manufacturer_name = $p['manufacturer_name'];
            }
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        return [
            'event' => $this->getEventName('begin_checkout'),
            'original_event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => $this->context->currency ? $this->context->currency->iso_code : 'EUR',
                'value' => round((float)$cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), 2),
                'items' => $items,
            ],
        ];
    }

    public function buildPurchasePayload(Order $order)
    {
        $orderProducts = $order->getProducts();
        $items = [];
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;

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
                'price' => round((float)$p['unit_price_tax_incl'], 2),
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

        $orderTax = round((float)($order->total_paid_tax_incl - $order->total_paid_tax_excl), 2);
        $orderShipping = round((float)$order->total_shipping_tax_incl, 2);
        $orderTotal = round((float)$order->total_paid_tax_incl, 2);

        // PrestaShop authoritative product subtotal
        $productSubtotal = (isset($order->total_products_wt) && (float)$order->total_products_wt > 0)
            ? round((float)$order->total_products_wt, 2)
            : 0.0;

        // GA4 item value = sum of (item price * quantity), excluding shipping and separate from tax
        $itemsSum = 0.0;
        foreach ($items as $it) {
            $itemsSum += (float)$it['price'] * (int)$it['quantity'];
        }
        $itemsSum = round($itemsSum, 2);

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

        $purchasePayload = [
            'event' => $this->getEventName('purchase'),
            'original_event' => 'purchase',
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
            'user_data' => $userData,
        ];

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
