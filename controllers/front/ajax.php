<?php
/**
 * AJAX Front Controller: Authoritative backend resolution of product & combination data.
 * Completely eliminates any client-side DOM dependency.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__, 3) . '/classes/ProductIdentityResolver.php';
require_once dirname(__FILE__, 3) . '/classes/PayloadBuilder.php';

class Ps_Gtm_DataLayerAjaxModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json; charset=utf-8');

        $action = Tools::getValue('action');
        if ($action === 'resolveProduct') {
            $this->ajaxProcessResolveProduct();
        } else {
            die(json_encode(['error' => 'Invalid action']));
        }
    }

    public function ajaxProcessResolveProduct()
    {
        $idProduct = (int)Tools::getValue('id_product');
        $idProductAttribute = (int)Tools::getValue('id_product_attribute');
        $quantity = (int)Tools::getValue('quantity', 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }

        if ($idProduct <= 0) {
            die(json_encode(['error' => 'Missing id_product']));
        }

        $idLanguage = $this->context->language ? (int)$this->context->language->id : 1;
        $idShop = $this->context->shop ? (int)$this->context->shop->id : 1;

        $product = new Product($idProduct, true, $idLanguage, $idShop);
        if (!Validate::isLoadedObject($product)) {
            die(json_encode(['error' => 'Product not found']));
        }

        $eventPrefix = Configuration::get('PS_GTM_EVENT_PREFIX');
        $payloadBuilder = new PayloadBuilder($this->context, $eventPrefix);
        $item = $payloadBuilder->formatItem($product, $idProductAttribute, $quantity);

        $idCategoryDefault = (int)$product->id_category_default;
        $category = new Category($idCategoryDefault, $idLanguage);
        $categoryName = Validate::isLoadedObject($category) ? (string)$category->name : '';

        if ($idCategoryDefault > 0) {
            $item['item_list_id'] = 'category_' . $idCategoryDefault;
            $item['item_list_name'] = $categoryName;
        }

        $currency = $this->context->currency ? $this->context->currency->iso_code : 'EUR';

        die(json_encode([
            'success' => true,
            'currency' => $currency,
            'item' => $item,
            'default_category_id' => $idCategoryDefault,
            'default_category_name' => $categoryName,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
