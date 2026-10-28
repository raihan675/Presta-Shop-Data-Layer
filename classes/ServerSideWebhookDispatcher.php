<?php
/**
 * ServerSideWebhookDispatcher: Dispatches server-to-server ecommerce payloads (refunds, offline orders)
 * to the configured Server-Side GTM Transport URL (sGTM / Stape / custom domain) via non-blocking cURL.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/AttributionDataEngine.php';

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

        $attribution = AttributionDataEngine::getByOrderId((int)$refundData['transaction_id']);
        if (!empty($attribution)) {
            $params['attribution'] = $attribution;
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

        $attribution = AttributionDataEngine::getByOrderId((int)$order->id);
        if (!empty($attribution)) {
            $params['attribution'] = $attribution;
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

        // Resolve previous status from database order history if not directly passed by hook
        if ($previousStatusId <= 0 && class_exists('Db') && !empty($order->id)) {
            try {
                $sql = 'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_history` ' .
                    'WHERE `id_order` = ' . (int)$order->id . ' AND `id_order_state` != ' . (int)$newOrderStatus->id . ' ' .
                    'ORDER BY `date_add` DESC, `id_order_history` DESC';
                $previousStatusId = (int)Db::getInstance()->getValue($sql);
            } catch (\Throwable $e) {}
        }

        // Fallback for Back Office updates where previous order state was not yet committed or history row was absent
        if ($previousStatusId <= 0 && !empty($order->current_state) && (int)$order->current_state !== (int)$newOrderStatus->id) {
            $previousStatusId = (int)$order->current_state;
        }

        // Strict guard: An order status update requires an actual change between two distinct statuses.
        // If there is no previous status (initial order placement at checkout) or if the status did not change, ignore.
        if ($previousStatusId <= 0 || $previousStatusId === (int)$newOrderStatus->id) {
            return false;
        }

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

        $canceledId = class_exists('Configuration') ? (int)Configuration::get('PS_OS_CANCELED') : 6;
        $refundedId = class_exists('Configuration') ? (int)Configuration::get('PS_OS_REFUND') : 7;
        $newStatusId = (int)$newOrderStatus->id;
        $statusNameLower = strtolower($statusName);

        $isRefunded = ($newStatusId === $refundedId
            || $newStatusId === 7
            || $newStatusId === 20
            || $newStatusId === 26
            || strpos($statusNameLower, 'refund') !== false
            || strpos($statusNameLower, 'erstattet') !== false);

        $isCanceled = ($newStatusId === $canceledId
            || $newStatusId === 6
            || strpos($statusNameLower, 'cancel') !== false
            || strpos($statusNameLower, 'storniert') !== false);

        // Classify the exact transition type for self-healing ad platform reconciliation
        if ($isRefunded) {
            $transitionType = 'refund_issued';
            $conversionAction = 'retract';
        } elseif ($isCanceled) {
            $transitionType = 'order_canceled';
            $conversionAction = 'retract';
        } elseif (!$previousIsPaid && $isPaidNow) {
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

        $attribution = AttributionDataEngine::getByOrderId((int)$order->id);
        if (!empty($attribution)) {
            $params['attribution'] = $attribution;
        }

        $statusContext = self::resolveStatusChangeContext($order, $newOrderStatus, (int)$previousStatusId);
        if (!empty($statusContext)) {
            $params['status_change_context'] = $statusContext;
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
     * Transmits canonical PrestaShop JSON carrying event_name, financial totals, items,
     * status transitions, and hashed user_data directly to the sGTM Webhook Client.
     *
     * @param string $sgtmUrl
     * @param string $eventName
     * @param array $payload
     * @return bool
     */
    public static function sendHttpRequest($sgtmUrl, $eventName, array $payload, $previewHeader = null)
    {
        try {
            if (!function_exists('curl_init') || empty($sgtmUrl)) {
                return false;
            }

            $clientId = !empty($payload['client_id']) ? (string)$payload['client_id'] : '';
            $events = !empty($payload['events']) && is_array($payload['events']) ? $payload['events'] : [];
            $firstEvent = !empty($events[0]) && is_array($events[0]) ? $events[0] : [];
            $params = !empty($firstEvent['params']) && is_array($firstEvent['params']) ? $firstEvent['params'] : [];

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

            $headers = [
                'Content-Type: application/json',
                'X-PrestaShop-Source: ps_gtm_datalayer',
                'X-Event-Type: ' . $eventName,
            ];

            if (empty($previewHeader)) {
                $previewHeader = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_PREVIEW_HEADER')) : '';
            }
            if (!empty($previewHeader)) {
                $headers[] = 'X-Gtm-Server-Preview: ' . $previewHeader;
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3500);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 2000);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false && !empty($curlError) && (stripos($curlError, 'SSL') !== false || stripos($curlError, 'certificate') !== false)) {
                $chRetry = curl_init($endpoint);
                curl_setopt($chRetry, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($chRetry, CURLOPT_POSTFIELDS, $body);
                curl_setopt($chRetry, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chRetry, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($chRetry, CURLOPT_TIMEOUT_MS, 3500);
                curl_setopt($chRetry, CURLOPT_CONNECTTIMEOUT_MS, 2000);
                curl_setopt($chRetry, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($chRetry, CURLOPT_SSL_VERIFYHOST, 0);
                $response = curl_exec($chRetry);
                $httpCode = curl_getinfo($chRetry, CURLINFO_HTTP_CODE);
                $curlError = curl_error($chRetry);
                curl_close($chRetry);
            }

            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog(
                    sprintf(
                        'ps_gtm_datalayer webhook [%s] dispatched to %s%s | HTTP Code: %d%s',
                        $eventName,
                        $endpoint,
                        !empty($previewHeader) ? ' [Preview Mode]' : '',
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
     * Resolves comprehensive forensic and operational context for an order status change event.
     *
     * @param Order $order
     * @param OrderState $newOrderStatus
     * @param int $previousStatusId
     * @return array
     */
    public static function resolveStatusChangeContext(Order $order, OrderState $newOrderStatus, $previousStatusId = 0)
    {
        $contextData = [];
        try {
            if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($newOrderStatus)) {
                return [];
            }

            $orderAddTimestamp = !empty($order->date_add) ? strtotime($order->date_add) : time();
            $orderAgeSeconds = max(0, time() - $orderAddTimestamp);

            // 1. Order history ID and time in previous status
            $idOrderHistory = 0;
            $timeInPreviousStatus = 0;
            if (class_exists('Db') && !empty($order->id)) {
                $histSql = 'SELECT `id_order_history`, `date_add` FROM `' . _DB_PREFIX_ . 'order_history` ' .
                    'WHERE `id_order` = ' . (int)$order->id . ' ORDER BY `date_add` DESC, `id_order_history` DESC';
                $histRows = Db::getInstance()->executeS($histSql);
                if (!empty($histRows) && is_array($histRows)) {
                    if (isset($histRows[0]['id_order_history'])) {
                        $idOrderHistory = (int)$histRows[0]['id_order_history'];
                    }
                    if (isset($histRows[1]['date_add'])) {
                        $prevDate = strtotime($histRows[1]['date_add']);
                        if ($prevDate > 0) {
                            $timeInPreviousStatus = max(0, time() - $prevDate);
                        }
                    }
                }
            }

            // 2. Actor / Initiator Information
            $actorType = 'system';
            $actorId = 'system';
            $actorName = 'System';
            $psContext = class_exists('Context') ? Context::getContext() : null;

            if ($psContext && isset($psContext->employee) && Validate::isLoadedObject($psContext->employee)) {
                $actorType = 'administrator';
                $actorId = 'id_employee: ' . (int)$psContext->employee->id;
                $actorName = trim($psContext->employee->firstname . ' ' . $psContext->employee->lastname);
            } elseif (!empty($_SERVER['REQUEST_URI']) && (
                stripos($_SERVER['REQUEST_URI'], 'webhook') !== false
                || stripos($_SERVER['REQUEST_URI'], 'ipn') !== false
                || stripos($_SERVER['REQUEST_URI'], 'callback') !== false
                || stripos($_SERVER['REQUEST_URI'], 'notify') !== false
            )) {
                $actorType = 'payment_gateway_webhook';
                $actorId = !empty($order->module) ? (string)$order->module : 'payment_webhook';
                $actorName = !empty($order->payment) ? (string)$order->payment : 'Payment Gateway';
            } elseif (php_sapi_name() === 'cli') {
                $actorType = 'system_cron';
                $actorId = 'cli_cron';
                $actorName = 'Cron Worker';
            } elseif (!empty($order->module)) {
                $actorId = (string)$order->module;
                $actorName = (string)$order->payment;
            }

            $actorIp = class_exists('Tools') && method_exists('Tools', 'getRemoteAddr')
                ? (string)Tools::getRemoteAddr()
                : (isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '');

            // 3. Carrier & Tracking Information
            $carrierName = '';
            if (!empty($order->id_carrier)) {
                $carrierObj = new Carrier((int)$order->id_carrier);
                if (Validate::isLoadedObject($carrierObj) && !empty($carrierObj->name)) {
                    $carrierName = (string)$carrierObj->name;
                }
            }

            $trackingNumber = '';
            if (!empty($order->shipping_number)) {
                $trackingNumber = (string)$order->shipping_number;
            } elseif (class_exists('Db')) {
                $trackSql = 'SELECT `tracking_number` FROM `' . _DB_PREFIX_ . 'order_carrier` WHERE `id_order` = ' . (int)$order->id;
                $dbTrack = (string)Db::getInstance()->getValue($trackSql);
                if (!empty($dbTrack)) {
                    $trackingNumber = $dbTrack;
                }
            }

            // 4. Payment Gateway Transaction ID
            $gatewayTxId = '';
            if (class_exists('Db') && !empty($order->reference)) {
                $cleanRef = function_exists('pSQL') ? pSQL($order->reference) : addslashes($order->reference);
                $paySql = 'SELECT `transaction_id` FROM `' . _DB_PREFIX_ . 'order_payment` ' .
                    "WHERE `order_reference` = '" . $cleanRef . "' AND `transaction_id` != '' " .
                    'ORDER BY `date_add` DESC';
                $gatewayTxId = (string)Db::getInstance()->getValue($paySql);
            }

            $contextData = [
                'id_order_history' => $idOrderHistory,
                'status_change_timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'actor_name' => $actorName,
                'actor_ip' => $actorIp,
                'order_age_seconds' => $orderAgeSeconds,
                'time_in_previous_status_seconds' => $timeInPreviousStatus,
                'is_within_google_ads_window' => ($orderAgeSeconds <= (55 * 86400)),
                'is_within_meta_window' => ($orderAgeSeconds <= (90 * 86400)),
                'is_invoice_generated' => isset($newOrderStatus->invoice) ? (bool)$newOrderStatus->invoice : false,
                'is_delivery_slip_generated' => isset($newOrderStatus->delivery) ? (bool)$newOrderStatus->delivery : false,
                'customer_email_sent' => isset($newOrderStatus->send_email) ? (bool)$newOrderStatus->send_email : false,
                'order_state_color' => isset($newOrderStatus->color) ? (string)$newOrderStatus->color : '',
                'tracking_number' => $trackingNumber,
                'carrier_name' => $carrierName,
                'gateway_transaction_id' => $gatewayTxId,
            ];
        } catch (\Throwable $e) {}

        return $contextData;
    }

    /**
     * Diagnostic ping method to verify reachability and latency of the Server-Side GTM container.
     *
     * @param string|null $sgtmUrl Optional override URL
     * @return array Result array with success, http_code, latency_ms, endpoint, and error
     */
    public static function testPing($sgtmUrl = null, $previewHeader = null, $endpointPath = null)
    {
        if (empty($sgtmUrl)) {
            $sgtmUrl = class_exists('Configuration') ? (string)Configuration::get('PS_SGTM_TRANSPORT_URL') : '';
        }

        $sgtmUrl = trim((string)$sgtmUrl);
        if (empty($sgtmUrl)) {
            return [
                'success' => false,
                'http_code' => 0,
                'latency_ms' => 0,
                'endpoint' => '',
                'error' => 'No Server-Side GTM Transport URL configured.',
                'message' => 'Please enter a valid Transport URL in Configuration.',
            ];
        }

        if (!filter_var($sgtmUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $sgtmUrl)) {
            return [
                'success' => false,
                'http_code' => 0,
                'latency_ms' => 0,
                'endpoint' => $sgtmUrl,
                'error' => 'Invalid URL format.',
                'message' => 'The Transport URL must be a valid HTTP or HTTPS URL (e.g. https://gtm.yourdomain.com).',
            ];
        }

        if (empty($endpointPath)) {
            $endpointPath = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_WEBHOOK_ENDPOINT_PATH')) : '';
            if (empty($endpointPath)) {
                $endpointPath = '/ps-webhook';
            }
        }

        if (empty($previewHeader)) {
            $previewHeader = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_PREVIEW_HEADER')) : '';
        }

        $parsedUrl = parse_url($sgtmUrl);
        if (!empty($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
            $endpoint = $sgtmUrl;
        } else {
            $endpoint = rtrim($sgtmUrl, '/') . '/' . ltrim($endpointPath, '/');
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'http_code' => 0,
                'latency_ms' => 0,
                'endpoint' => $endpoint,
                'error' => 'cURL PHP extension is not installed or enabled.',
                'message' => 'PHP cURL extension required for server-side dispatching.',
            ];
        }

        $testPayload = [
            'event_name' => 'test_ping',
            'client_id' => 'diagnostic_ping.' . time(),
            'timestamp' => time(),
            'source' => 'ps_gtm_datalayer_diagnostic',
            'events' => [
                [
                    'name' => 'test_ping',
                    'params' => [
                        'diagnostic' => true,
                        'timestamp' => time(),
                    ],
                ],
            ],
        ];

        $body = json_encode($testPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'X-PrestaShop-Source: ps_gtm_datalayer',
            'X-Event-Type: test_ping',
            'X-Diagnostic-Ping: 1',
        ];
        if (!empty($previewHeader)) {
            $headers[] = 'X-Gtm-Server-Preview: ' . $previewHeader;
        }

        $startTime = microtime(true);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 4000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 2500);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

        $isSuccess = ($httpCode >= 200 && $httpCode < 400);
        $message = $isSuccess
            ? 'Endpoint reached successfully (' . $latencyMs . ' ms, HTTP ' . $httpCode . ')'
            : (!empty($curlError) ? 'Connection failed: ' . $curlError : 'HTTP ' . $httpCode . ' response from endpoint');

        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'latency_ms' => $latencyMs,
            'endpoint' => $endpoint,
            'preview_header_sent' => !empty($previewHeader),
            'error' => $curlError,
            'message' => $message,
            'raw_response' => substr((string)$response, 0, 300),
        ];
    }

    /**
     * Dispatches a live test order_status_update payload to Server-Side GTM.
     * Includes X-Gtm-Server-Preview header so the request immediately renders in GTM Server Preview debugger.
     *
     * @param string|null $sgtmUrl Optional override URL
     * @param string|null $previewHeader Optional preview header override
     * @param string|null $endpointPath Optional endpoint path override
     * @return array
     */
    public static function testOrderStatusWebhook($sgtmUrl = null, $previewHeader = null, $endpointPath = null)
    {
        if (empty($sgtmUrl)) {
            $sgtmUrl = class_exists('Configuration') ? (string)Configuration::get('PS_SGTM_TRANSPORT_URL') : '';
        }
        $sgtmUrl = trim((string)$sgtmUrl);
        if (empty($sgtmUrl)) {
            return [
                'success' => false,
                'http_code' => 0,
                'latency_ms' => 0,
                'endpoint' => '',
                'error' => 'No Server-Side GTM Transport URL configured.',
                'message' => 'Please enter a valid Transport URL in Configuration.',
            ];
        }

        if (!filter_var($sgtmUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $sgtmUrl)) {
            return [
                'success' => false,
                'http_code' => 0,
                'latency_ms' => 0,
                'endpoint' => $sgtmUrl,
                'error' => 'Invalid URL format.',
                'message' => 'The Transport URL must be a valid HTTP or HTTPS URL (e.g. https://gtm.yourdomain.com).',
            ];
        }

        if (empty($endpointPath)) {
            $endpointPath = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_WEBHOOK_ENDPOINT_PATH')) : '';
            if (empty($endpointPath)) {
                $endpointPath = '/ps-webhook';
            }
        }

        if (empty($previewHeader)) {
            $previewHeader = class_exists('Configuration') ? trim((string)Configuration::get('PS_SGTM_PREVIEW_HEADER')) : '';
        }

        $parsedUrl = parse_url($sgtmUrl);
        if (!empty($parsedUrl['path']) && $parsedUrl['path'] !== '/') {
            $endpoint = $sgtmUrl;
        } else {
            $endpoint = rtrim($sgtmUrl, '/') . '/' . ltrim($endpointPath, '/');
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'http_code' => 0,
                'latency_ms' => 0,
                'endpoint' => $endpoint,
                'error' => 'cURL PHP extension is not installed or enabled.',
                'message' => 'PHP cURL extension required for server-side dispatching.',
            ];
        }

        $samplePayload = [
            'event_name' => 'order_status_update',
            'client_id' => 'test_order.' . time(),
            'timestamp' => time(),
            'transaction_id' => 'TEST-1001',
            'order_reference' => 'DIAGNOSTIC-TEST',
            'status_transition_type' => 'payment_confirmed',
            'conversion_action' => 'record',
            'new_status_id' => 2,
            'new_status_name' => 'Payment accepted',
            'previous_status_id' => 10,
            'previous_status_name' => 'Awaiting bank wire payment',
            'is_paid' => true,
            'previous_is_paid' => false,
            'is_shipped' => false,
            'is_canceled' => false,
            'is_refunded' => false,
            'currency' => 'EUR',
            'value' => 49.99,
            'payment_method' => 'Bank Wire',
            'items' => [
                [
                    'item_id' => 'TEST_ITEM_1',
                    'item_name' => 'Test Product - sGTM Webhook Diagnostics',
                    'price' => 49.99,
                    'quantity' => 1
                ]
            ],
            'user_data' => [
                'email_address' => hash('sha256', 'test@example.com'),
                'phone_number' => hash('sha256', '1234567890')
            ],
            'events' => [
                [
                    'name' => 'order_status_update',
                    'params' => [
                        'transaction_id' => 'TEST-1001',
                        'order_reference' => 'DIAGNOSTIC-TEST',
                        'status_transition_type' => 'payment_confirmed',
                        'conversion_action' => 'record',
                        'new_status_name' => 'Payment accepted',
                        'is_paid' => true,
                        'currency' => 'EUR',
                        'value' => 49.99
                    ]
                ]
            ]
        ];

        $body = json_encode($samplePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'X-PrestaShop-Source: ps_gtm_datalayer',
            'X-Event-Type: order_status_update',
            'X-Diagnostic-Test: 1'
        ];
        if (!empty($previewHeader)) {
            $headers[] = 'X-Gtm-Server-Preview: ' . $previewHeader;
        }

        $startTime = microtime(true);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 4000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 2500);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false && !empty($curlError) && (stripos($curlError, 'SSL') !== false || stripos($curlError, 'certificate') !== false)) {
            $chRetry = curl_init($endpoint);
            curl_setopt($chRetry, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($chRetry, CURLOPT_POSTFIELDS, $body);
            curl_setopt($chRetry, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chRetry, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($chRetry, CURLOPT_TIMEOUT_MS, 4000);
            curl_setopt($chRetry, CURLOPT_CONNECTTIMEOUT_MS, 2500);
            curl_setopt($chRetry, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chRetry, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($chRetry);
            $httpCode = (int)curl_getinfo($chRetry, CURLINFO_HTTP_CODE);
            $curlError = curl_error($chRetry);
            curl_close($chRetry);
        }

        $latencyMs = round((microtime(true) - $startTime) * 1000, 2);
        $isSuccess = ($httpCode >= 200 && $httpCode < 400);
        $message = $isSuccess
            ? 'Order status webhook dispatched successfully (' . $latencyMs . ' ms, HTTP ' . $httpCode . ')' . (!empty($previewHeader) ? ' - Check your Server GTM Preview debugger window!' : ' (Note: Add Preview Header to see in GTM Preview mode)')
            : (!empty($curlError) ? 'Connection failed: ' . $curlError : 'HTTP ' . $httpCode . ' response from endpoint');

        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'latency_ms' => $latencyMs,
            'endpoint' => $endpoint,
            'preview_header_sent' => !empty($previewHeader),
            'error' => $curlError,
            'message' => $message,
            'raw_response' => substr((string)$response, 0, 300),
        ];
    }
}
