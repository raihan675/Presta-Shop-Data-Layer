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

        try {
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
        } catch (\Throwable $e) {
            return false;
        }
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
                if (method_exists($context->cookie, 'write')) {
                    $context->cookie->write();
                }

                if (!headers_sent()) {
                    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                    setcookie('ps_gtm_attribution', json_encode($currentData), time() + (30 * 86400), '/', '', $isSecure, false);
                }
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

            self::createDatabaseTable();

            if (!$context) {
                $context = class_exists('Context') ? Context::getContext() : null;
            }

            $data = self::getCapturedSessionData($context);

            // Fresh check of direct first-party cookie
            if (empty($data['gclid']) && empty($data['fbclid']) && empty($data['utm_source']) && !empty($_COOKIE['ps_gtm_attribution'])) {
                $rawC = $_COOKIE['ps_gtm_attribution'];
                $dec = json_decode(urldecode($rawC), true);
                if (!is_array($dec)) $dec = json_decode($rawC, true);
                if (is_array($dec)) {
                    foreach ($dec as $k => $v) {
                        if (!empty($v) && empty($data[$k])) {
                            $data[$k] = self::sanitizeIdentifier($v);
                        }
                    }
                }
            }

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

            return self::upsertOrderAttributionRow($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Safely inserts or updates an attribution record in ps_gtm_order_attribution.
     * Guaranteed cross-database compatibility without relying on non-existent constants.
     *
     * @param array $row
     * @return bool
     */
    public static function upsertOrderAttributionRow(array $row)
    {
        if (empty($row['id_order']) || !class_exists('Db')) {
            return false;
        }

        try {
            self::createDatabaseTable();
            $idOrder = (int)$row['id_order'];

            $exists = (int)Db::getInstance()->getValue(
                'SELECT `id_order` FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '` WHERE `id_order` = ' . $idOrder
            );

            if ($exists > 0) {
                // Merge with existing row so existing non-empty values are never overwritten with null
                $existing = Db::getInstance()->getRow(
                    'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '` WHERE `id_order` = ' . $idOrder
                );
                if (!empty($existing) && is_array($existing)) {
                    foreach ($existing as $col => $val) {
                        if (!empty($val) && (!isset($row[$col]) || $row[$col] === null || $row[$col] === '')) {
                            $row[$col] = $val;
                        }
                    }
                }
                $row['date_upd'] = date('Y-m-d H:i:s');
                return (bool)Db::getInstance()->update(self::TABLE_NAME, $row, '`id_order` = ' . $idOrder, 1, true);
            } else {
                if (empty($row['date_add'])) {
                    $row['date_add'] = date('Y-m-d H:i:s');
                }
                if (empty($row['date_upd'])) {
                    $row['date_upd'] = date('Y-m-d H:i:s');
                }
                return (bool)Db::getInstance()->insert(self::TABLE_NAME, $row, true, false, Db::INSERT);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolves attribution data for an order by ID, falling back to PrestaShop's
     * native connections_source traffic records if not already in ps_gtm_order_attribution.
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
            self::createDatabaseTable();

            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '` WHERE `id_order` = ' . $idOrder;
            $row = Db::getInstance()->getRow($sql);
            if (!empty($row) && is_array($row)) {
                $payload = self::formatAttributionPayload($row);
                if (!empty($payload['gclid']) || !empty($payload['fbclid']) || !empty($payload['ttclid']) || !empty($payload['msclkid']) || !empty($payload['sccid']) || !empty($payload['utm_source']) || !empty($payload['utm_campaign'])) {
                    return $payload;
                }
            }

            // Fallback: Recover from PrestaShop native connections_source (Sources / Quellen)
            $recovered = self::recoverFromPrestaShopConnections($idOrder);
            if (!empty($recovered) && (!empty($recovered['gclid']) || !empty($recovered['fbclid']) || !empty($recovered['ttclid']) || !empty($recovered['msclkid']) || !empty($recovered['sccid']) || !empty($recovered['utm_source']) || !empty($recovered['utm_campaign']))) {
                return self::formatAttributionPayload($recovered);
            }

            if (!empty($row) && is_array($row)) {
                return self::formatAttributionPayload($row);
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Recovers marketing attribution from PrestaShop's native ps_connections_source table
     * using the order's cart, customer connection logs, and forensic IP time-window matching.
     *
     * @param int $idOrder
     * @return array
     */
    public static function recoverFromPrestaShopConnections($idOrder)
    {
        $idOrder = (int)$idOrder;
        if ($idOrder <= 0 || !class_exists('Db')) {
            return [];
        }

        try {
            $rows = [];

            // 1. Official PrestaShop Core method (used by Back Office Sources panel)
            if (!class_exists('ConnectionsSource') && defined('_PS_CLASS_DIR_') && file_exists(_PS_CLASS_DIR_ . 'ConnectionsSource.php')) {
                require_once _PS_CLASS_DIR_ . 'ConnectionsSource.php';
            }
            if (class_exists('ConnectionsSource') && method_exists('ConnectionsSource', 'getOrderSources')) {
                $orderSources = ConnectionsSource::getOrderSources($idOrder);
                if (!empty($orderSources) && is_array($orderSources)) {
                    $rows = $orderSources;
                }
            }

            // 2. PrestaShop Core SQL Join (orders -> guest -> connections -> connections_source)
            if (empty($rows)) {
                $sql = 'SELECT cos.`http_referer`, cos.`request_uri`, cos.`keywords`, cos.`date_add`, c.`ip_address`
                        FROM `' . _DB_PREFIX_ . 'orders` o
                        INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON (g.`id_customer` = o.`id_customer`)
                        INNER JOIN `' . _DB_PREFIX_ . 'connections` c ON (c.`id_guest` = g.`id_guest`)
                        INNER JOIN `' . _DB_PREFIX_ . 'connections_source` cos ON (cos.`id_connections` = c.`id_connections`)
                        WHERE o.`id_order` = ' . (int)$idOrder . '
                        ORDER BY cos.`date_add` DESC';
                $dbRows = Db::getInstance()->executeS($sql);
                if (!empty($dbRows) && is_array($dbRows)) {
                    $rows = $dbRows;
                }
            }

            // 3. Fallback: by customer ID
            if (empty($rows)) {
                $sql = 'SELECT cos.`http_referer`, cos.`request_uri`, cos.`keywords`, cos.`date_add`, c.`ip_address`
                        FROM `' . _DB_PREFIX_ . 'orders` o
                        INNER JOIN `' . _DB_PREFIX_ . 'customer` cust ON (cust.`id_customer` = o.`id_customer`)
                        INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON (g.`id_customer` = cust.`id_customer`)
                        INNER JOIN `' . _DB_PREFIX_ . 'connections` c ON (c.`id_guest` = g.`id_guest`)
                        INNER JOIN `' . _DB_PREFIX_ . 'connections_source` cos ON (cos.`id_connections` = c.`id_connections`)
                        WHERE o.`id_order` = ' . (int)$idOrder . '
                        ORDER BY cos.`date_add` DESC';
                $dbRows = Db::getInstance()->executeS($sql);
                if (!empty($dbRows) && is_array($dbRows)) {
                    $rows = $dbRows;
                }
            }

            // 4. Fallback: by cart guest
            if (empty($rows)) {
                $sql = 'SELECT cos.`http_referer`, cos.`request_uri`, cos.`keywords`, cos.`date_add`, c.`ip_address`
                        FROM `' . _DB_PREFIX_ . 'orders` o
                        INNER JOIN `' . _DB_PREFIX_ . 'cart` cart ON (cart.`id_cart` = o.`id_cart`)
                        INNER JOIN `' . _DB_PREFIX_ . 'connections` c ON (c.`id_guest` = cart.`id_guest`)
                        INNER JOIN `' . _DB_PREFIX_ . 'connections_source` cos ON (cos.`id_connections` = c.`id_connections`)
                        WHERE o.`id_order` = ' . (int)$idOrder . '
                        ORDER BY cos.`date_add` DESC';
                $dbRows = Db::getInstance()->executeS($sql);
                if (!empty($dbRows) && is_array($dbRows)) {
                    $rows = $dbRows;
                }
            }

            // 5. Forensic Fallback: IP & Timestamp Window Lookup from ps_connections & ps_connections_source
            $orderObj = new Order($idOrder);
            if (Validate::isLoadedObject($orderObj)) {
                $orderIp = '';
                if ((int)$orderObj->id_customer > 0) {
                    $ipSql = 'SELECT c.`ip_address`
                              FROM `' . _DB_PREFIX_ . 'connections` c
                              INNER JOIN `' . _DB_PREFIX_ . 'guest` g ON (g.`id_guest` = c.`id_guest`)
                              WHERE g.`id_customer` = ' . (int)$orderObj->id_customer . '
                              ORDER BY c.`date_add` DESC';
                    $rawIp = Db::getInstance()->getValue($ipSql);
                    if (!empty($rawIp)) {
                        $orderIp = (string)$rawIp;
                    }
                }

                if (!empty($orderIp)) {
                    $ipCondition = is_numeric($orderIp) ? 'c.`ip_address` = ' . (int)$orderIp : 'c.`ip_address` = ' . (int)sprintf('%u', ip2long($orderIp));
                    $ipQuery = 'SELECT cos.`http_referer`, cos.`request_uri`, cos.`keywords`, cos.`date_add`, c.`ip_address`
                                FROM `' . _DB_PREFIX_ . 'connections` c
                                INNER JOIN `' . _DB_PREFIX_ . 'connections_source` cos ON (cos.`id_connections` = c.`id_connections`)
                                WHERE ' . $ipCondition . '
                                  AND cos.`date_add` BETWEEN DATE_SUB(\'' . pSQL($orderObj->date_add) . '\', INTERVAL 48 HOUR) AND DATE_ADD(\'' . pSQL($orderObj->date_add) . '\', INTERVAL 1 HOUR)
                                ORDER BY cos.`date_add` DESC
                                LIMIT 20';
                    $ipRows = Db::getInstance()->executeS($ipQuery);
                    if (!empty($ipRows) && is_array($ipRows)) {
                        $rows = array_merge($rows ? $rows : [], $ipRows);
                    }
                }
            }

            if (!empty($rows) && is_array($rows)) {
                $recovered = [];
                $clientIp = '';

                foreach ($rows as $row) {
                    if (empty($clientIp) && !empty($row['ip_address'])) {
                        $clientIp = is_numeric($row['ip_address']) ? long2ip((int)$row['ip_address']) : (string)$row['ip_address'];
                    }

                    $urlsToCheck = [
                        !empty($row['request_uri']) ? $row['request_uri'] : '',
                        !empty($row['http_referer']) ? $row['http_referer'] : '',
                    ];

                    foreach ($urlsToCheck as $url) {
                        if (empty($url)) {
                            continue;
                        }

                        $qPos = strpos($url, '?');
                        $queryStr = ($qPos !== false) ? substr($url, $qPos + 1) : '';
                        if (empty($queryStr)) {
                            $parsedQ = parse_url($url, PHP_URL_QUERY);
                            if (!empty($parsedQ)) {
                                $queryStr = $parsedQ;
                            }
                        }

                        if (!empty($queryStr)) {
                            $params = [];
                            parse_str($queryStr, $params);

                            foreach (self::$clickParams as $p) {
                                if (!empty($params[$p]) && empty($recovered[$p])) {
                                    $recovered[$p] = self::sanitizeIdentifier($params[$p]);
                                }
                            }
                            foreach (self::$utmParams as $u) {
                                if (!empty($params[$u]) && empty($recovered[$u])) {
                                    $val = self::sanitizeIdentifier($params[$u]);
                                    if ($val !== '') {
                                        $recovered[$u] = $val;
                                    }
                                }
                            }
                        }
                    }

                    if (!empty($recovered['gclid']) || !empty($recovered['fbclid'])) {
                        break;
                    }
                }

                if (!empty($recovered)) {
                    if (!empty($clientIp)) {
                        $recovered['customer_ip'] = $clientIp;
                    }

                    // Save recovered record into ps_gtm_order_attribution for fast future retrieval
                    $order = new Order($idOrder);
                    if (Validate::isLoadedObject($order)) {
                        $now = date('Y-m-d H:i:s');
                        $rowToSave = [
                            'id_order' => (int)$order->id,
                            'id_customer' => (int)$order->id_customer,
                            'gclid' => !empty($recovered['gclid']) ? pSQL($recovered['gclid']) : null,
                            'gbraid' => !empty($recovered['gbraid']) ? pSQL($recovered['gbraid']) : null,
                            'wbraid' => !empty($recovered['wbraid']) ? pSQL($recovered['wbraid']) : null,
                            'fbclid' => !empty($recovered['fbclid']) ? pSQL($recovered['fbclid']) : null,
                            'ttclid' => !empty($recovered['ttclid']) ? pSQL($recovered['ttclid']) : null,
                            'msclkid' => !empty($recovered['msclkid']) ? pSQL($recovered['msclkid']) : null,
                            'sccid' => !empty($recovered['sccid']) ? pSQL($recovered['sccid']) : null,
                            'oppref' => !empty($recovered['oppref']) ? pSQL($recovered['oppref']) : null,
                            'epik' => !empty($recovered['epik']) ? pSQL($recovered['epik']) : null,
                            'li_fat_id' => !empty($recovered['li_fat_id']) ? pSQL($recovered['li_fat_id']) : null,
                            'rdt_cid' => !empty($recovered['rdt_cid']) ? pSQL($recovered['rdt_cid']) : null,
                            'twclid' => !empty($recovered['twclid']) ? pSQL($recovered['twclid']) : null,
                            'dclid' => !empty($recovered['dclid']) ? pSQL($recovered['dclid']) : null,
                            'utm_source' => !empty($recovered['utm_source']) ? pSQL($recovered['utm_source']) : null,
                            'utm_medium' => !empty($recovered['utm_medium']) ? pSQL($recovered['utm_medium']) : null,
                            'utm_campaign' => !empty($recovered['utm_campaign']) ? pSQL($recovered['utm_campaign']) : null,
                            'utm_content' => !empty($recovered['utm_content']) ? pSQL($recovered['utm_content']) : null,
                            'utm_term' => !empty($recovered['utm_term']) ? pSQL($recovered['utm_term']) : null,
                            'customer_ip' => !empty($recovered['customer_ip']) ? pSQL($recovered['customer_ip']) : null,
                            'date_add' => $now,
                            'date_upd' => $now,
                        ];

                        self::upsertOrderAttributionRow($rowToSave);
                    }

                    return $recovered;
                }
            }
        } catch (\Throwable $e) {}

        return [];
    }

    /**
     * Formats database or recovered array into standardized attribution payload.
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
            'customer_ip', 'user_agent', 'date_add',
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
        $currentData = [];

        // 1. Direct standard cookie (resilient to header timing and encryption limits)
        if (!empty($_COOKIE['ps_gtm_attribution'])) {
            $rawCookie = $_COOKIE['ps_gtm_attribution'];
            if (is_string($rawCookie)) {
                $decoded = json_decode(urldecode($rawCookie), true);
                if (!is_array($decoded)) {
                    $decoded = json_decode($rawCookie, true);
                }
                if (is_array($decoded)) {
                    foreach ($decoded as $k => $v) {
                        if (!empty($v)) {
                            $currentData[$k] = self::sanitizeIdentifier($v);
                        }
                    }
                }
            }
        }

        // 2. PrestaShop core cookie object
        if ($context && isset($context->cookie)) {
            $raw = $context->cookie->__get(self::COOKIE_KEY);
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k => $v) {
                        if (!empty($v) && empty($currentData[$k])) {
                            $currentData[$k] = self::sanitizeIdentifier($v);
                        }
                    }
                }
            }
        }

        return $currentData;
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
