<?php
/**
 * Constructs catalog-accurate GA4 ecommerce payloads across catalog, cart, and checkout states.
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

        $product = new Product($idProduct, true, $this->context->language->id, $this->context->shop->id);
        if (!Validate::isLoadedObject($product)) {
            return null;
        }

        $idProductAttribute = (int)Product::getDefaultAttribute($idProduct);
        $selectedAttribute = (int)Tools::getValue('id_product_attribute', $idProductAttribute);

        $item = $this->formatItem($product, $selectedAttribute, 1);
        $price = (float)$item['price'];

        return [
            'event' => $this->getEventName('view_item'),
            'original_event' => 'view_item',
            'ecommerce' => [
                'currency' => $this->context->currency->iso_code,
                'value' => round($price, 2),
                'items' => [$item],
            ],
        ];
    }

    public function buildViewItemListPayload($controller)
    {
        $listId = 'list_' . $controller;
        $listName = ucfirst($controller) . ' Page';

        if ($controller === 'category') {
            $idCategory = (int)Tools::getValue('id_category');
            if ($idCategory) {
                $category = new Category($idCategory, $this->context->language->id);
                if (Validate::isLoadedObject($category)) {
                    $listId = 'category_' . $idCategory;
                    $listName = (string)$category->name;
                }
            }
        } elseif ($controller === 'search') {
            $query = Tools::getValue('s');
            if ($query) {
                $listId = 'search_results';
                $listName = 'Search: ' . $query;
            }
        }

        return [
            'event' => $this->getEventName('view_item_list'),
            'original_event' => 'view_item_list',
            'ecommerce' => [
                'item_list_id' => $listId,
                'item_list_name' => $listName,
                'items' => [],
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

        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $this->context->language->id);
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        return [
            'event' => $this->getEventName('view_cart'),
            'original_event' => 'view_cart',
            'ecommerce' => [
                'currency' => $this->context->currency->iso_code,
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
        foreach ($cartProducts as $p) {
            $productObj = new Product((int)$p['id_product'], false, $this->context->language->id);
            $items[] = $this->formatItem($productObj, (int)$p['id_product_attribute'], (int)$p['cart_quantity'], (float)$p['price_wt']);
        }

        return [
            'event' => $this->getEventName('begin_checkout'),
            'original_event' => 'begin_checkout',
            'ecommerce' => [
                'currency' => $this->context->currency->iso_code,
                'value' => round((float)$cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), 2),
                'items' => $items,
            ],
        ];
    }

    public function buildPurchasePayload(Order $order)
    {
        $orderProducts = $order->getProducts();
        $items = [];

        foreach ($orderProducts as $p) {
            $productObj = new Product((int)$p['product_id'], false, $this->context->language->id);
            $idAttr = isset($p['product_attribute_id']) ? (int)$p['product_attribute_id'] : 0;
            $identifiers = ProductIdentityResolver::resolveIdentifiers($productObj, $idAttr);

            $unitPrice = round((float)$p['unit_price_tax_incl'], 2);
            $qty = (int)$p['product_quantity'];

            $items[] = array_merge($identifiers, [
                'item_name' => (string)$p['product_name'],
                'price' => $unitPrice,
                'quantity' => $qty,
            ]);
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

        return [
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
    }

    public function formatItem(Product $product, $idAttribute = 0, $quantity = 1, $overridePrice = null)
    {
        $idLanguage = $this->context->language->id;
        $price = $overridePrice !== null 
            ? $overridePrice 
            : Product::getPriceStatic($product->id, true, $idAttribute ? $idAttribute : null, 2);

        $variant = '';
        if ($idAttribute > 0) {
            $attributes = $product->getAttributesResume($idLanguage);
            if (!empty($attributes)) {
                foreach ($attributes as $attr) {
                    if ((int)$attr['id_product_attribute'] === (int)$idAttribute) {
                        $variant = $attr['attribute'];
                        break;
                    }
                }
            }
        }

        $category = new Category((int)$product->id_category_default, $idLanguage);
        $identifiers = ProductIdentityResolver::resolveIdentifiers($product, $idAttribute);

        return array_merge($identifiers, [
            'item_name' => (string)$product->name,
            'item_brand' => (string)$product->manufacturer_name,
            'item_category' => Validate::isLoadedObject($category) ? (string)$category->name : '',
            'item_variant' => $variant,
            'price' => round((float)$price, 2),
            'quantity' => (int)$quantity,
        ]);
    }
}
