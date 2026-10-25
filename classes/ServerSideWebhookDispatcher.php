<?php
/**
 * ServerSideWebhookDispatcher: Dispatches server-to-server ecommerce payloads (refunds, offline orders)
 * to the configured Server-Side GTM Transport URL (sGTM / Stape / custom domain) via non-blocking cURL.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ServerSideWebhookDispatcher
{
    /**
     * Dispatches a refund event to Server-Side GTM.
     *
     * @param array $refundData Data produced by RefundDataResolver
     * @param string|null $sgtmUrl Optional override of transport URL
     * @return bool
     */
    public static function dispatchRefund(array $refundData, $sgtmUrl = null)
    {
        if (empty($sgtmUrl)) {
            $sgtmUrl = class_exists('Configuration') ? (string)Configuration::get('PS_SGTM_TRANSPORT_URL') : '';
        }

        if (empty($sgtmUrl)) {
            return false;
        }

        $clientId = !empty($refundData['transaction_id'])
            ? $refundData['transaction_id'] . '.' . time()
            : 'ps_refund.' . time();

        $params = [
            'transaction_id' => (string)$refundData['transaction_id'],
            'currency' => !empty($refundData['currency']) ? (string)$refundData['currency'] : 'EUR',
            'value' => (float)$refundData['value'],
        ];

        if (isset($refundData['shipping']) && (float)$refundData['shipping'] > 0) {
            $params['shipping'] = (float)$refundData['shipping'];
        }

        if (!empty($refundData['items']) && is_array($refundData['items'])) {
            $params['items'] = $refundData['items'];
        }

        if (!empty($refundData['user_data']) && is_array($refundData['user_data'])) {
            $params['user_data'] = $refundData['user_data'];
        }

        $envelope = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => 'refund',
                    'params' => $params,
                ]
            ]
        ];

        return self::sendHttpRequest($sgtmUrl, 'refund', $envelope);
    }

    /**
     * Dispatches an offline order (purchase event) to Server-Side GTM.
     *
     * @param Order $order PrestaShop Order object
     * @param string|null $sgtmUrl Optional override of transport URL
     * @return bool
     */
    public static function dispatchOfflinePurchase(Order $order, $sgtmUrl = null)
    {
        if (empty($sgtmUrl)) {
            $sgtmUrl = class_exists('Configuration') ? (string)Configuration::get('PS_SGTM_TRANSPORT_URL') : '';
        }

        if (empty($sgtmUrl) || !Validate::isLoadedObject($order)) {
            return false;
        }

        require_once __DIR__ . '/PayloadBuilder.php';
        $eventPrefix = class_exists('Configuration') ? (string)Configuration::get('PS_GTM_EVENT_PREFIX') : '';
        $payloadBuilder = new PayloadBuilder(Context::getContext(), $eventPrefix);
        $purchasePayload = $payloadBuilder->buildPurchasePayload($order);

        if (empty($purchasePayload['ecommerce'])) {
            return false;
        }

        $ecom = $purchasePayload['ecommerce'];
        $clientId = $order->id_customer > 0
            ? $order->id_customer . '.' . strtotime($order->date_add)
            : $order->id . '.' . time();

        $userData = RefundDataResolver::extractUserData($order);

        $params = [
            'transaction_id' => (string)$order->id,
            'currency' => !empty($ecom['currency']) ? (string)$ecom['currency'] : 'EUR',
            'value' => (float)$ecom['value'],
            'tax' => isset($ecom['tax']) ? (float)$ecom['tax'] : 0.0,
            'shipping' => isset($ecom['shipping']) ? (float)$ecom['shipping'] : 0.0,
            'items' => !empty($ecom['items']) ? $ecom['items'] : [],
        ];

        if (!empty($ecom['coupon'])) {
            $params['coupon'] = (string)$ecom['coupon'];
        }

        if (!empty($userData)) {
            $params['user_data'] = $userData;
        }

        $envelope = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => 'purchase',
                    'params' => $params,
                ]
            ]
        ];

        return self::sendHttpRequest($sgtmUrl, 'purchase', $envelope);
    }

    /**
     * Dispatches an order/payment status update event to Server-Side GTM.
     *
     * @param Order $order PrestaShop Order object
     * @param OrderState $newOrderStatus PrestaShop OrderState object
     * @param int $previousStatusId
     * @param string|null $sgtmUrl
     * @return bool
     */
    public static function dispatchOrderStatusUpdate(Order $order, OrderState $newOrderStatus, $previousStatusId = 0, $sgtmUrl = null)
    {
        if (empty($sgtmUrl)) {
            $sgtmUrl = class_exists('Configuration') ? (string)Configuration::get('PS_SGTM_TRANSPORT_URL') : '';
        }

        if (empty($sgtmUrl) || !Validate::isLoadedObject($order) || !Validate::isLoadedObject($newOrderStatus)) {
            return false;
        }

        $idLanguage = (Context::getContext() && Context::getContext()->language)
            ? (int)Context::getContext()->language->id
            : (int)Configuration::get('PS_LANG_DEFAULT');

        $statusName = is_array($newOrderStatus->name)
            ? (isset($newOrderStatus->name[$idLanguage]) ? $newOrderStatus->name[$idLanguage] : reset($newOrderStatus->name))
            : (string)$newOrderStatus->name;

        $previousIsPaid = false;
        $previousStatusName = '';
        if ($previousStatusId > 0) {
            $prevOrderState = new OrderState((int)$previousStatusId);
            if (Validate::isLoadedObject($prevOrderState)) {
                $previousIsPaid = (bool)$prevOrderState->paid;
                $previousStatusName = is_array($prevOrderState->name)
                    ? (isset($prevOrderState->name[$idLanguage]) ? $prevOrderState->name[$idLanguage] : reset($prevOrderState->name))
                    : (string)$prevOrderState->name;
            }
        }

        $isPaidNow = (bool)$newOrderStatus->paid;

        // Classify the exact transition type for self-healing ad platform reconciliation
        if (!$previousIsPaid && $isPaidNow) {
            $transitionType = 'payment_confirmed';
            $conversionAction = 'record';
        } elseif ($previousIsPaid && !$isPaidNow) {
            $transitionType = 'payment_revoked';
            $conversionAction = 'retract';
        } elseif ($previousIsPaid && $isPaidNow) {
            $transitionType = 'status_changed_while_paid';
            $conversionAction = 'maintain';
        } else {
            $transitionType = 'status_changed_unpaid';
            $conversionAction = 'none';
        }

        $canceledId = class_exists('Configuration') ? (int)Configuration::get('PS_OS_CANCELED') : 6;
        $refundedId = class_exists('Configuration') ? (int)Configuration::get('PS_OS_REFUND') : 7;
        $isCanceled = ((int)$newOrderStatus->id === $canceledId);
        $isRefunded = ((int)$newOrderStatus->id === $refundedId);

        $currency = new Currency((int)$order->id_currency);
        $currencyIso = Validate::isLoadedObject($currency) ? $currency->iso_code : 'EUR';

        $userData = RefundDataResolver::extractUserData($order);

        // Resolve order items according to catalog strategy
        $items = [];
        $catalogStrategy = class_exists('Configuration') && Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')
            ? (string)Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')
            : 'composite';

        if (method_exists($order, 'getProducts')) {
            $products = $order->getProducts();
            if (!empty($products) && is_array($products)) {
                foreach ($products as $p) {
                    $idProduct = (int)$p['product_id'];
                    $idProductAttribute = (int)$p['product_attribute_id'];
                    $productObj = new Product($idProduct, false, $idLanguage);
                    $catalogItemId = RefundDataResolver::resolveCatalogIdentifier(
                        $productObj,
                        $idProduct,
                        $idProductAttribute,
                        isset($p['product_reference']) ? $p['product_reference'] : '',
                        $catalogStrategy
                    );

                    $items[] = [
                        'item_id' => $catalogItemId,
                        'item_name' => isset($p['product_name']) ? (string)$p['product_name'] : '',
                        'price' => round((float)$p['unit_price_tax_incl'], 2),
                        'quantity' => (int)$p['product_quantity'],
                    ];
                }
            }
        }

        $clientId = $order->id_customer > 0
            ? $order->id_customer . '.' . strtotime($order->date_add)
            : $order->id . '.' . time();

        $params = [
            'transaction_id' => (string)$order->id,
            'order_reference' => (string)$order->reference,
            'status_transition_type' => $transitionType,
            'conversion_action' => $conversionAction,
            'new_status_id' => (int)$newOrderStatus->id,
            'new_status_name' => (string)$statusName,
            'previous_status_id' => (int)$previousStatusId,
            'previous_status_name' => (string)$previousStatusName,
            'is_paid' => $isPaidNow,
            'previous_is_paid' => $previousIsPaid,
            'is_shipped' => (bool)$newOrderStatus->shipped,
            'is_canceled' => $isCanceled,
            'is_refunded' => $isRefunded,
            'currency' => $currencyIso,
            'value' => round((float)$order->total_paid_tax_incl, 2),
            'payment_method' => (string)$order->payment,
            'items' => $items,
        ];

        if (!empty($userData)) {
            $params['user_data'] = $userData;
        }

        $envelope = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => 'order_status_update',
                    'params' => $params,
                ]
            ]
        ];

        return self::sendHttpRequest($sgtmUrl, 'order_status_update', $envelope);
    }

    /**
     * Sends the non-blocking HTTP POST request to the Server GTM container endpoint.
     * Supports both native sGTM GA4 Client beacon format (when GA4 Measurement ID is configured)
     * and raw JSON / Stape Data Client format, with optional GTM Preview header injection.
     *
     * @param string $sgtmUrl
     * @param string $eventName
     * @param array $payload
     * @return bool
     */
    public static function sendHttpRequest($sgtmUrl, $eventName, array $payload)
    {
        try {
            if (!function_exists('curl_init') || empty($sgtmUrl)) {
                return false;
            }

            $measurementId = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_GA4_MEASUREMENT_ID')) : '';
            $previewHeader = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_PREVIEW_HEADER')) : '';

            $clientId = !empty($payload['client_id']) ? (string)$payload['client_id'] : '';
            $events = !empty($payload['events']) && is_array($payload['events']) ? $payload['events'] : [];
            $firstEvent = !empty($events[0]) && is_array($events[0]) ? $events[0] : [];
            $params = !empty($firstEvent['params']) && is_array($firstEvent['params']) ? $firstEvent['params'] : [];

            // If a GA4 Measurement ID is configured, format payload for Google's native sGTM GA4 Client
            if (!empty($measurementId)) {
                $endpoint = rtrim($sgtmUrl, '/') . '/g/collect';
                $body = self::buildGa4BeaconBody($measurementId, $clientId, $eventName, $params);
                $contentType = 'text/plain';
            } else {
                $endpointPath = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_WEBHOOK_ENDPOINT_PATH')) : '';
                if (empty($endpointPath)) {
                    $endpointPath = '/ps-webhook';
                }

                $parsedUrl = parse_url($sgtmUrl);
                if (!empty($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
                    $endpoint = $sgtmUrl;
                } else {
                    $endpoint = rtrim($sgtmUrl, '/') . '/' . ltrim($endpointPath, '/');
                }

                $jsonPayload = array_merge([
                    'event_name' => $eventName,
                    'client_id' => $clientId,
                    'timestamp' => time(),
                ], $params);
                $jsonPayload['events'] = [
                    [
                        'name' => $eventName,
                        'params' => $params,
                    ]
                ];

                $body = json_encode($jsonPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $contentType = 'application/json';
            }

            $headers = [
                'Content-Type: ' . $contentType,
                'X-PrestaShop-Source: ps_gtm_datalayer',
                'X-Event-Type: ' . $eventName,
            ];

            if (!empty($previewHeader)) {
                $headers[] = 'X-Gtm-Server-Preview: ' . $previewHeader;
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog(
                    sprintf(
                        'ps_gtm_datalayer webhook [%s] dispatched to %s | HTTP Code: %d%s',
                        $eventName,
                        $endpoint,
                        $httpCode,
                        !empty($curlError) ? ' | cURL Error: ' . $curlError : ''
                    ),
                    ($httpCode >= 200 && $httpCode < 300) ? 1 : 2
                );
            }

            return ($httpCode >= 200 && $httpCode < 300);
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer webhook dispatch exception: ' . $e->getMessage(), 2);
            }
            return false;
        }
    }

    /**
     * Builds canonical GA4 Measurement Protocol / gtag beacon body for sGTM GA4 Client consumption.
     * Formats event parameters (ep.*, epn.*), items (pr1=...), and user data (em, ph, ep.user_*).
     *
     * @param string $measurementId GA4 Measurement ID (e.g. G-XXXXXXXX)
     * @param string $clientId sGTM Client ID
     * @param string $eventName GA4 Event Name
     * @param array $params Event parameters
     * @return string URL-encoded beacon query string
     */
    public static function buildGa4BeaconBody($measurementId, $clientId, $eventName, array $params)
    {
        $fields = [
            'v' => '2',
            'tid' => $measurementId,
            'cid' => $clientId,
            'en' => $eventName,
        ];

        $parts = [];
        foreach ($fields as $k => $v) {
            $parts[] = $k . '=' . rawurlencode((string)$v);
        }

        $itemIndex = 1;
        foreach ($params as $key => $val) {
            if ($key === 'items' && is_array($val)) {
                foreach ($val as $item) {
                    $itemParts = [];
                    if (isset($item['item_id'])) {
                        $itemParts[] = 'id' . str_replace('~', '_', (string)$item['item_id']);
                    }
                    if (isset($item['item_name'])) {
                        $itemParts[] = 'nm' . str_replace('~', '_', (string)$item['item_name']);
                    }
                    if (isset($item['price'])) {
                        $itemParts[] = 'pr' . (float)$item['price'];
                    }
                    if (isset($item['quantity'])) {
                        $itemParts[] = 'qt' . (int)$item['quantity'];
                    }
                    if (isset($item['item_variant']) && $item['item_variant'] !== '') {
                        $itemParts[] = 'va' . str_replace('~', '_', (string)$item['item_variant']);
                    }
                    if (isset($item['item_brand']) && $item['item_brand'] !== '') {
                        $itemParts[] = 'br' . str_replace('~', '_', (string)$item['item_brand']);
                    }
                    if (isset($item['item_category']) && $item['item_category'] !== '') {
                        $itemParts[] = 'ca' . str_replace('~', '_', (string)$item['item_category']);
                    }
                    if (!empty($itemParts)) {
                        $parts[] = 'pr' . $itemIndex . '=' . rawurlencode(implode('~', $itemParts));
                        $itemIndex++;
                    }
                }
            } elseif ($key === 'user_data' && is_array($val)) {
                if (!empty($val['email_address'])) {
                    $parts[] = 'em=' . rawurlencode((string)$val['email_address']);
                    $parts[] = 'ep.user_email_hash=' . rawurlencode((string)$val['email_address']);
                }
                if (!empty($val['phone_number'])) {
                    $parts[] = 'ph=' . rawurlencode((string)$val['phone_number']);
                    $parts[] = 'ep.user_phone_hash=' . rawurlencode((string)$val['phone_number']);
                }
                if (!empty($val['address']) && is_array($val['address'])) {
                    foreach ($val['address'] as $addrKey => $addrVal) {
                        if (is_scalar($addrVal) && (string)$addrVal !== '') {
                            $parts[] = 'ep.user_' . $addrKey . '=' . rawurlencode((string)$addrVal);
                        }
                    }
                }
            } elseif (is_numeric($val) && !is_string($val)) {
                $parts[] = 'epn.' . $key . '=' . $val;
            } elseif (is_bool($val)) {
                $parts[] = 'ep.' . $key . '=' . ($val ? 'true' : 'false');
            } elseif (is_scalar($val) && (string)$val !== '') {
                $parts[] = 'ep.' . $key . '=' . rawurlencode((string)$val);
            }
        }

        return implode('&', $parts);
    }
}
