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
