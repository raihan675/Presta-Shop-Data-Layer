<?php
/**
 * AJAX Front Controller: Authoritative backend resolution of product & combination data
 * and resilient client-side marketing attribution synchronization.
 * Completely eliminates any client-side DOM dependency.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__, 3) . '/classes/ProductIdentityResolver.php';
require_once dirname(__FILE__, 3) . '/classes/PayloadBuilder.php';
require_once dirname(__FILE__, 3) . '/classes/AttributionDataEngine.php';

class Ps_Gtm_DataLayerAjaxModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json; charset=utf-8');

        $action = Tools::getValue('action');
        if ($action === 'resolveProduct' || $action === 'resolveWishlistProduct' || $action === 'resolveCartDelta') {
            $this->ajaxProcessResolveProduct();
        } elseif ($action === 'syncAttribution') {
            $this->ajaxProcessSyncAttribution();
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

    public function ajaxProcessSyncAttribution()
    {
        try {
            $rawAttribution = Tools::getValue('attribution');
            $idOrder = (int)Tools::getValue('id_order');

            $data = [];
            if (!empty($rawAttribution)) {
                if (is_string($rawAttribution)) {
                    $decoded = json_decode(urldecode($rawAttribution), true);
                    if (!is_array($decoded)) {
                        $decoded = json_decode($rawAttribution, true);
                    }
                    if (is_array($decoded)) {
                        $data = $decoded;
                    }
                } elseif (is_array($rawAttribution)) {
                    $data = $rawAttribution;
                }
            }

            // Fresh check of direct query parameters
            foreach (AttributionDataEngine::$clickParams as $p) {
                $val = Tools::getValue($p);
                if (!empty($val) && empty($data[$p])) {
                    $data[$p] = $val;
                }
            }
            foreach (AttributionDataEngine::$utmParams as $u) {
                $val = Tools::getValue($u);
                if (!empty($val) && empty($data[$u])) {
                    $data[$u] = $val;
                }
            }

            // Clean & sanitize data
            $cleanData = [];
            foreach ($data as $k => $v) {
                if (!empty($v) && is_string($v)) {
                    $cleanData[$k] = AttributionDataEngine::sanitizeIdentifier($v);
                }
            }

            // Store into customer session cookie
            if (!empty($cleanData) && $this->context && isset($this->context->cookie)) {
                $existing = [];
                $rawCookie = $this->context->cookie->__get(AttributionDataEngine::COOKIE_KEY);
                if (!empty($rawCookie)) {
                    $dec = json_decode($rawCookie, true);
                    if (is_array($dec)) {
                        $existing = $dec;
                    }
                }
                $merged = array_merge($existing, $cleanData);
                $this->context->cookie->__set(AttributionDataEngine::COOKIE_KEY, json_encode($merged));
                if (method_exists($this->context->cookie, 'write')) {
                    $this->context->cookie->write();
                }
            }

            // If id_order is provided, upsert directly into database!
            if ($idOrder > 0 && !empty($cleanData)) {
                $order = new Order($idOrder);
                if (Validate::isLoadedObject($order)) {
                    $now = date('Y-m-d H:i:s');
                    $ip = AttributionDataEngine::getClientIp();
                    $userAgent = AttributionDataEngine::getUserAgent();

                    $row = [
                        'id_order' => (int)$order->id,
                        'id_customer' => (int)$order->id_customer,
                        'customer_ip' => !empty($ip) ? pSQL($ip) : null,
                        'user_agent' => !empty($userAgent) ? pSQL(substr($userAgent, 0, 500)) : null,
                        'date_add' => $now,
                        'date_upd' => $now,
                    ];

                    $allFields = array_merge(
                        AttributionDataEngine::$clickParams,
                        AttributionDataEngine::$utmParams,
                        array_values(AttributionDataEngine::$cookieParams)
                    );

                    foreach ($allFields as $field) {
                        if (!empty($cleanData[$field])) {
                            $row[$field] = pSQL($cleanData[$field]);
                        }
                    }

                    if (!empty($cleanData['ga_client_id'])) {
                        $row['ga_client_id'] = pSQL($cleanData['ga_client_id']);
                    }
                    if (!empty($cleanData['ga_session_id'])) {
                        $row['ga_session_id'] = pSQL($cleanData['ga_session_id']);
                    }

                    AttributionDataEngine::upsertOrderAttributionRow($row);
                }
            }

            die(json_encode([
                'success' => true,
                'synced_keys' => array_keys($cleanData),
                'id_order' => $idOrder,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            die(json_encode(['error' => $e->getMessage()]));
        }
    }
}
