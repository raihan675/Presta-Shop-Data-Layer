<?php
/**
 * Constructs catalog-accurate GA4 ecommerce payloads across catalog, cart, and checkout states.
 * 100% authoritative backend data sourcing — zero DOM dependency.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/ProductIdentityResolver.php';
require_once __DIR__ . '/CustomerDataEngine.php';

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
                'value' => round($price, 2),
                'items' => [$item],
            ],
        ];
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
                    $prods = $category->getProducts($idLanguage, 1, 24, 'position', 'asc', false, true);
                    if (!empty($prods) && is_array($prods)) {
                        $idx = 1;
                        foreach ($prods as $p) {
                            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
                            if (!empty($p['manufacturer_name'])) {
                                $productObj->manufacturer_name = $p['manufacturer_name'];
                            }
                            $price = isset($p['price_wt']) ? (float)$p['price_wt'] : (isset($p['price']) ? (float)$p['price'] : null);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
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
                    $prods = $manufacturer->getProducts($idManufacturer, $idLanguage, 1, 24, 'name', 'asc');
                    if (!empty($prods) && is_array($prods)) {
                        $idx = 1;
                        foreach ($prods as $p) {
                            $productObj = new Product((int)$p['id_product'], false, $idLanguage);
                            $productObj->manufacturer_name = (string)$manufacturer->name;
                            $price = isset($p['price_wt']) ? (float)$p['price_wt'] : (isset($p['price']) ? (float)$p['price'] : null);
                            $item = $this->formatItem($productObj, 0, 1, $price);
                            $item['index'] = $idx++;
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
                $searchRes = Search::find($idLanguage, $query, 1, 24, 'position', 'desc');
                if (!empty($searchRes['result']) && is_array($searchRes['result'])) {
                    $idx = 1;
                    foreach ($searchRes['result'] as $p) {
                        $productObj = new Product((int)$p['id_product'], false, $idLanguage);
                        if (!empty($p['manufacturer_name'])) {
                            $productObj->manufacturer_name = $p['manufacturer_name'];
                        }
                        $price = isset($p['price_wt']) ? (float)$p['price_wt'] : (isset($p['price']) ? (float)$p['price'] : null);
                        $item = $this->formatItem($productObj, 0, 1, $price);
                        $item['index'] = $idx++;
                        $items[] = $item;
                    }
                }
            }
        }

        return [
            'event' => $this->getEventName('view_item_list'),
            'original_event' => 'view_item_list',
            'ecommerce' => [
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

        $coupon = '';
        $cartRules = $order->getCartRules();
        if (!empty($cartRules) && isset($cartRules[0]['name'])) {
            $coupon = (string)$cartRules[0]['name'];
        }

        $purchasePayload = [
            'event' => $this->getEventName('purchase'),
            'original_event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => !empty($order->reference) ? (string)$order->reference : (string)$order->id,
                'value' => $orderTotal,
                'tax' => $orderTax,
                'shipping' => $orderShipping,
                'currency' => Currency::getIsoCodeById((int)$order->id_currency),
                'coupon' => $coupon,
                'items' => $items,
            ],
            'user_data' => $userData,
        ];

        return $purchasePayload;
    }

    public function formatItem(Product $product, $idAttribute = 0, $quantity = 1, $overridePrice = null)
    {
        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $price = $overridePrice !== null 
            ? $overridePrice 
            : Product::getPriceStatic($product->id, true, $idAttribute ? $idAttribute : null, 2);

        $identifiers = ProductIdentityResolver::resolveProductIdentifiers($product, $idAttribute, $idLanguage);
        $category = new Category((int)$product->id_category_default, $idLanguage);

        // Authoritative brand resolution directly from database
        $brand = '';
        if (!empty($product->manufacturer_name)) {
            $brand = (string)$product->manufacturer_name;
        } elseif (!empty($product->id_manufacturer)) {
            $brand = (string)Manufacturer::getNameById((int)$product->id_manufacturer);
        }

        return [
            'item_id' => (string)$product->id, // Canonical base product ID
            'variant_id' => $identifiers['variant_id'],
            'sku_id' => $identifiers['sku_id'],
            'item_name' => (string)$product->name,
            'item_brand' => $brand,
            'item_category' => Validate::isLoadedObject($category) ? (string)$category->name : '',
            'item_variant' => $identifiers['item_variant'],
            'price' => round((float)$price, 2),
            'quantity' => (int)$quantity,
        ];
    }
}
