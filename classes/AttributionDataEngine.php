<?php
/**
 * AttributionDataEngine: Captures, persists, and rehydrates advertising click IDs,
 * browser cookies, and marketing campaign parameters across the order lifecycle.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AttributionDataEngine
{
    const TABLE_NAME = 'gtm_order_attribution';
    const COOKIE_KEY = 'ps_gtm_attribution_data';

    /**
     * Supported URL Ad Click Identifiers (Priority 1)
     */
    public static $clickParams = [
        'gclid',
        'gbraid',
        'wbraid',
        'fbclid',
        'ttclid',
        'sccid',
        'msclkid',
        'oppref',
        'epik',
        'li_fat_id',
        'rdt_cid',
        'twclid',
        'dclid',
    ];

    /**
     * Supported UTM & Campaign Parameters
     */
    public static $utmParams = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
    ];

    /**
     * Supported Browser Tracking Cookies (Priority 2 & 3)
     */
    public static $cookieParams = [
        '_fbp' => 'fbp',
        '_fbc' => 'fbc',
        '_ttp' => 'ttp',
        '_scid' => 'scid',
        '_uetvid' => 'uetvid',
        '_uetsid' => 'uetsid',
    ];

    /**
     * Creates the database table for order attribution persistence.
     *
     * @return bool
     */
    public static function createDatabaseTable()
    {
        if (!class_exists('Db')) {
            return false;
        }

        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` (
            `id_order` INT(11) UNSIGNED NOT NULL,
            `id_customer` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `gclid` VARCHAR(255) NULL DEFAULT NULL,
            `gbraid` VARCHAR(255) NULL DEFAULT NULL,
            `wbraid` VARCHAR(255) NULL DEFAULT NULL,
            `fbclid` VARCHAR(255) NULL DEFAULT NULL,
            `ttclid` VARCHAR(255) NULL DEFAULT NULL,
            `sccid` VARCHAR(255) NULL DEFAULT NULL,
            `msclkid` VARCHAR(255) NULL DEFAULT NULL,
            `oppref` VARCHAR(255) NULL DEFAULT NULL,
            `epik` VARCHAR(255) NULL DEFAULT NULL,
            `li_fat_id` VARCHAR(255) NULL DEFAULT NULL,
            `rdt_cid` VARCHAR(255) NULL DEFAULT NULL,
            `twclid` VARCHAR(255) NULL DEFAULT NULL,
            `dclid` VARCHAR(255) NULL DEFAULT NULL,
            `fbp` VARCHAR(100) NULL DEFAULT NULL,
            `fbc` VARCHAR(150) NULL DEFAULT NULL,
            `ttp` VARCHAR(100) NULL DEFAULT NULL,
            `scid` VARCHAR(100) NULL DEFAULT NULL,
            `uetvid` VARCHAR(100) NULL DEFAULT NULL,
            `uetsid` VARCHAR(100) NULL DEFAULT NULL,
            `ga_client_id` VARCHAR(100) NULL DEFAULT NULL,
            `ga_session_id` VARCHAR(50) NULL DEFAULT NULL,
            `utm_source` VARCHAR(100) NULL DEFAULT NULL,
            `utm_medium` VARCHAR(100) NULL DEFAULT NULL,
            `utm_campaign` VARCHAR(150) NULL DEFAULT NULL,
            `utm_content` VARCHAR(150) NULL DEFAULT NULL,
            `utm_term` VARCHAR(150) NULL DEFAULT NULL,
            `customer_ip` VARCHAR(45) NULL DEFAULT NULL,
            `user_agent` VARCHAR(512) NULL DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_order`),
            INDEX `idx_customer` (`id_customer`),
            INDEX `idx_gclid` (`gclid`),
            INDEX `idx_fbclid` (`fbclid`)
        ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Drops the database table during uninstall.
     *
     * @return bool
     */
    public static function dropDatabaseTable()
    {
        if (!class_exists('Db')) {
            return false;
        }

        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '`');
    }

    /**
     * Captures marketing identifiers from request URL and cookies during storefront browsing,
     * maintaining state across pages in the customer's session/cookie.
     *
     * @param Context|null $context
     * @return void
     */
    public static function captureStorefrontAttribution($context = null)
    {
        try {
            if (!$context) {
                $context = class_exists('Context') ? Context::getContext() : null;
            }

            if (!$context || !isset($context->cookie)) {
                return;
            }

            $currentData = self::getCapturedSessionData($context);
            $hasUpdates = false;

            // 1. Capture Ad Click IDs from URL
            foreach (self::$clickParams as $param) {
                $val = self::getQueryParam($param);
                if (!empty($val)) {
                    $currentData[$param] = self::sanitizeIdentifier($val);
                    $hasUpdates = true;
                }
            }

            // 2. Capture UTM Campaign Parameters from URL
            foreach (self::$utmParams as $utm) {
                $val = self::getQueryParam($utm);
                if (!empty($val)) {
                    $currentData[$utm] = self::sanitizeIdentifier($val);
                    $hasUpdates = true;
                }
            }

            // 3. Capture Browser Cookies
            foreach (self::$cookieParams as $cookieName => $targetKey) {
                $val = self::getCookieValue($cookieName);
                if (!empty($val)) {
                    $currentData[$targetKey] = self::sanitizeIdentifier($val);
                    $hasUpdates = true;
                }
            }

            // 4. Capture GA4 Client ID & Session ID
            $gaCookie = self::getCookieValue('_ga');
            if (!empty($gaCookie)) {
                $clientId = self::extractGaClientId($gaCookie);
                if (!empty($clientId)) {
                    $currentData['ga_client_id'] = $clientId;
                    $hasUpdates = true;
                }
            }

            $gaSessionId = self::extractGaSessionIdFromCookies();
            if (!empty($gaSessionId)) {
                $currentData['ga_session_id'] = $gaSessionId;
                $hasUpdates = true;
            }

            if ($hasUpdates) {
                $context->cookie->__set(self::COOKIE_KEY, json_encode($currentData));
            }
        } catch (\Throwable $e) {}
    }

    /**
     * Persists marketing attribution for an Order into ps_gtm_order_attribution.
     *
     * @param Order $order
     * @param Context|null $context
     * @return bool
     */
    public static function saveOrderAttribution(Order $order, $context = null)
    {
        try {
            if (!class_exists('Db') || !class_exists('Validate') || !Validate::isLoadedObject($order)) {
                return false;
            }

            if (!$context) {
                $context = class_exists('Context') ? Context::getContext() : null;
            }

            $data = self::getCapturedSessionData($context);

            // Fresh extraction from current request in case order was placed directly from URL
            foreach (self::$clickParams as $param) {
                if (empty($data[$param])) {
                    $val = self::getQueryParam($param);
                    if (!empty($val)) {
                        $data[$param] = self::sanitizeIdentifier($val);
                    }
                }
            }

            foreach (self::$utmParams as $utm) {
                if (empty($data[$utm])) {
                    $val = self::getQueryParam($utm);
                    if (!empty($val)) {
                        $data[$utm] = self::sanitizeIdentifier($val);
                    }
                }
            }

            foreach (self::$cookieParams as $cookieName => $targetKey) {
                if (empty($data[$targetKey])) {
                    $val = self::getCookieValue($cookieName);
                    if (!empty($val)) {
                        $data[$targetKey] = self::sanitizeIdentifier($val);
                    }
                }
            }

            if (empty($data['ga_client_id'])) {
                $gaCookie = self::getCookieValue('_ga');
                if (!empty($gaCookie)) {
                    $data['ga_client_id'] = self::extractGaClientId($gaCookie);
                }
            }

            if (empty($data['ga_session_id'])) {
                $data['ga_session_id'] = self::extractGaSessionIdFromCookies();
            }

            $ip = self::getClientIp();
            $userAgent = self::getUserAgent();
            $now = date('Y-m-d H:i:s');

            $row = [
                'id_order' => (int)$order->id,
                'id_customer' => (int)$order->id_customer,
                'gclid' => !empty($data['gclid']) ? pSQL($data['gclid']) : null,
                'gbraid' => !empty($data['gbraid']) ? pSQL($data['gbraid']) : null,
                'wbraid' => !empty($data['wbraid']) ? pSQL($data['wbraid']) : null,
                'fbclid' => !empty($data['fbclid']) ? pSQL($data['fbclid']) : null,
                'ttclid' => !empty($data['ttclid']) ? pSQL($data['ttclid']) : null,
                'sccid' => !empty($data['sccid']) ? pSQL($data['sccid']) : null,
                'msclkid' => !empty($data['msclkid']) ? pSQL($data['msclkid']) : null,
                'oppref' => !empty($data['oppref']) ? pSQL($data['oppref']) : null,
                'epik' => !empty($data['epik']) ? pSQL($data['epik']) : null,
                'li_fat_id' => !empty($data['li_fat_id']) ? pSQL($data['li_fat_id']) : null,
                'rdt_cid' => !empty($data['rdt_cid']) ? pSQL($data['rdt_cid']) : null,
                'twclid' => !empty($data['twclid']) ? pSQL($data['twclid']) : null,
                'dclid' => !empty($data['dclid']) ? pSQL($data['dclid']) : null,
                'fbp' => !empty($data['fbp']) ? pSQL($data['fbp']) : null,
                'fbc' => !empty($data['fbc']) ? pSQL($data['fbc']) : null,
                'ttp' => !empty($data['ttp']) ? pSQL($data['ttp']) : null,
                'scid' => !empty($data['scid']) ? pSQL($data['scid']) : null,
                'uetvid' => !empty($data['uetvid']) ? pSQL($data['uetvid']) : null,
                'uetsid' => !empty($data['uetsid']) ? pSQL($data['uetsid']) : null,
                'ga_client_id' => !empty($data['ga_client_id']) ? pSQL($data['ga_client_id']) : null,
                'ga_session_id' => !empty($data['ga_session_id']) ? pSQL($data['ga_session_id']) : null,
                'utm_source' => !empty($data['utm_source']) ? pSQL($data['utm_source']) : null,
                'utm_medium' => !empty($data['utm_medium']) ? pSQL($data['utm_medium']) : null,
                'utm_campaign' => !empty($data['utm_campaign']) ? pSQL($data['utm_campaign']) : null,
                'utm_content' => !empty($data['utm_content']) ? pSQL($data['utm_content']) : null,
                'utm_term' => !empty($data['utm_term']) ? pSQL($data['utm_term']) : null,
                'customer_ip' => !empty($ip) ? pSQL($ip) : null,
                'user_agent' => !empty($userAgent) ? pSQL(substr($userAgent, 0, 500)) : null,
                'date_add' => $now,
                'date_upd' => $now,
            ];

            return Db::getInstance()->insert(self::TABLE_NAME, $row, false, true, Db::ON_DUPLICATE_KEY_UPDATE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolves attribution data for an order by ID.
     *
     * @param int $idOrder
     * @return array
     */
    public static function getByOrderId($idOrder)
    {
        $idOrder = (int)$idOrder;
        if ($idOrder <= 0 || !class_exists('Db')) {
            return [];
        }

        try {
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '` WHERE `id_order` = ' . $idOrder;
            $row = Db::getInstance()->getRow($sql);
            if (!empty($row) && is_array($row)) {
                return self::formatAttributionPayload($row);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Formats raw database row into clean, typed attribution payload.
     *
     * @param array $raw
     * @return array
     */
    public static function formatAttributionPayload(array $raw)
    {
        $attribution = [];

        $keys = [
            'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'sccid', 'msclkid', 'oppref',
            'epik', 'li_fat_id', 'rdt_cid', 'twclid', 'dclid',
            'fbp', 'fbc', 'ttp', 'scid', 'uetvid', 'uetsid',
            'ga_client_id', 'ga_session_id',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        ];

        foreach ($keys as $key) {
            if (!empty($raw[$key])) {
                $attribution[$key] = (string)$raw[$key];
            }
        }

        return $attribution;
    }

    /**
     * Extracts GA4 Client ID from _ga cookie value (e.g. GA1.1.123456789.987654321 -> 123456789.987654321).
     *
     * @param string $cookieVal
     * @return string|null
     */
    public static function extractGaClientId($cookieVal)
    {
        if (empty($cookieVal)) {
            return null;
        }

        $parts = explode('.', $cookieVal);
        if (count($parts) >= 4) {
            return $parts[2] . '.' . $parts[3];
        } elseif (count($parts) === 2) {
            return $cookieVal;
        }

        return null;
    }

    /**
     * Extracts GA4 Session ID from _ga_<container> cookies.
     *
     * @return string|null
     */
    public static function extractGaSessionIdFromCookies()
    {
        if (empty($_COOKIE) || !is_array($_COOKIE)) {
            return null;
        }

        foreach ($_COOKIE as $name => $val) {
            if (strpos($name, '_ga_') === 0 && !empty($val)) {
                // Typical format: GS1.1.1712345678.1.1.1712345678.0.0.0
                $parts = explode('.', $val);
                if (isset($parts[2]) && is_numeric($parts[2])) {
                    return (string)$parts[2];
                }
            }
        }

        return null;
    }

    /**
     * Retrieves stored attribution from customer session cookie.
     *
     * @param Context|null $context
     * @return array
     */
    protected static function getCapturedSessionData($context)
    {
        if (!$context || !isset($context->cookie)) {
            return [];
        }

        $raw = $context->cookie->__get(self::COOKIE_KEY);
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Reads a query parameter safely from $_GET.
     *
     * @param string $key
     * @return string|null
     */
    protected static function getQueryParam($key)
    {
        if (class_exists('Tools') && method_exists('Tools', 'getValue')) {
            $val = Tools::getValue($key);
            if (!empty($val) && is_string($val)) {
                return trim($val);
            }
        } elseif (isset($_GET[$key]) && is_string($_GET[$key])) {
            return trim($_GET[$key]);
        }

        return null;
    }

    /**
     * Reads a cookie safely from $_COOKIE.
     *
     * @param string $key
     * @return string|null
     */
    protected static function getCookieValue($key)
    {
        if (isset($_COOKIE[$key]) && is_string($_COOKIE[$key])) {
            return trim($_COOKIE[$key]);
        }

        return null;
    }

    /**
     * Sanitizes marketing and click identifiers.
     *
     * @param string $str
     * @return string
     */
    public static function sanitizeIdentifier($str)
    {
        return trim(preg_replace('/[^a-zA-Z0-9_\-\.\:\/]/', '', (string)$str));
    }

    /**
     * Retrieves client IP address safely.
     *
     * @return string
     */
    public static function getClientIp()
    {
        if (class_exists('Tools') && method_exists('Tools', 'getRemoteAddr')) {
            return (string)Tools::getRemoteAddr();
        }

        return isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Retrieves User Agent string safely.
     *
     * @return string
     */
    public static function getUserAgent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    }
}
