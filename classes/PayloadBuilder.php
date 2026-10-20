<?php
/**
 * Constructs standard GA4 ecommerce payloads across catalog, cart, and checkout states.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class PayloadBuilder
{
    private $context;
    private $useSku;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->useSku = (bool)Configuration::get('PS_GTM_USE_SKU');
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
            'event' => 'view_item',
            'ecommerce' => [
                'currency' => $this->context->currency->iso_code,
                'value' => $price,
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
            'event' => 'view_item_list',
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
            'event' => 'view_cart',
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
            'event' => 'begin_checkout',
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
            $itemId = (!empty($p['product_reference']) && $this->useSku)
                ? (string)$p['product_reference']
                : (string)$p['product_id'];

            $items[] = [
                'item_id' => $itemId,
                'item_name' => (string)$p['product_name'],
                'price' => round((float)$p['unit_price_tax_incl'], 2),
                'quantity' => (int)$p['product_quantity'],
            ];
        }

        $customer = new Customer((int)$order->id_customer);
        $address = new Address((int)$order->id_address_invoice);
        $userData = UserDataHasher::hashCustomerData($customer, $address);

        return [
            'event' => 'purchase',
            'ecommerce' => [
                'transaction_id' => !empty($order->reference) ? (string)$order->reference : (string)$order->id,
                'value' => round((float)$order->total_paid_tax_incl, 2),
                'tax' => round((float)($order->total_paid_tax_incl - $order->total_paid_tax_excl), 2),
                'shipping' => round((float)$order->total_shipping_tax_incl, 2),
                'currency' => Currency::getIsoCodeById((int)$order->id_currency),
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

        $itemId = (!empty($product->reference) && $this->useSku)
            ? (string)$product->reference
            : (string)$product->id;

        return [
            'item_id' => $itemId,
            'item_name' => (string)$product->name,
            'item_brand' => (string)$product->manufacturer_name,
            'item_category' => Validate::isLoadedObject($category) ? (string)$category->name : '',
            'item_variant' => $variant,
            'price' => round((float)$price, 2),
            'quantity' => (int)$quantity,
        ];
    }
}
