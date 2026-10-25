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
     *
     * @param string $sgtmUrl
     * @param string $eventName
     * @param array $payload
     * @return bool
     */
    public static function sendHttpRequest($sgtmUrl, $eventName, array $payload)
    {
        try {
            $endpoint = rtrim($sgtmUrl, '/') . '/g/collect?v=2';
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (!function_exists('curl_init')) {
                return false;
            }

            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-PrestaShop-Source: ps_gtm_datalayer',
                'X-Event-Type: ' . $eventName,
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 1000);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ($httpCode >= 200 && $httpCode < 300);
        } catch (\Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('ps_gtm_datalayer webhook dispatch exception: ' . $e->getMessage(), 2);
            }
            return false;
        }
    }
}
