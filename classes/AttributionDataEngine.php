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
    const CART_TABLE_NAME = 'gtm_cart_attribution';
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
        '_gcl_aw' => 'gcl_aw',
        '_gcl_gb' => 'gcl_gb',
        '_gcl_au' => 'gcl_au',
        '_pin_unauth' => 'pin_unauth',
        '_pinterest_ct_ua' => 'pinterest_ct_ua',
        '_rdt_uuid' => 'rdt_uuid',
    ];

    /**
     * Creates the database tables for order & cart attribution persistence.
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

            // 1. Order Attribution Table (Permanent Source of Truth)
            $sqlOrder = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` (
                `id_order` INT(11) UNSIGNED NOT NULL,
                `id_cart` INT(11) UNSIGNED NOT NULL DEFAULT 0,
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
                INDEX `idx_cart` (`id_cart`),
                INDEX `idx_customer` (`id_customer`),
                INDEX `idx_gclid` (`gclid`),
                INDEX `idx_fbclid` (`fbclid`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8;';

            Db::getInstance()->execute($sqlOrder);

            // Ensure id_cart column exists if upgrading from earlier version
            try {
                $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '` LIKE \'id_cart\'');
                if (empty($columns)) {
                    Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . self::TABLE_NAME . '` ADD `id_cart` INT(11) UNSIGNED NOT NULL DEFAULT 0 AFTER `id_order`, ADD INDEX `idx_cart` (`id_cart`)');
                }
            } catch (\Throwable $e) {}

            // 2. Cart Attribution Table (Active Pre-Order Browsing Bridge)
            $sqlCart = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::CART_TABLE_NAME . '` (
                `id_cart` INT(11) UNSIGNED NOT NULL,
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
                PRIMARY KEY (`id_cart`),
                INDEX `idx_cart_customer` (`id_customer`),
                INDEX `idx_cart_gclid` (`gclid`),
                INDEX `idx_cart_fbclid` (`fbclid`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8;';

            return Db::getInstance()->execute($sqlCart);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Drops the database tables during uninstall.
     *
     * @return bool
     */
    public static function dropDatabaseTable()
    {
        if (!class_exists('Db')) {
            return false;
        }

        try {
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '`');
            Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::CART_TABLE_NAME . '`');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
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
     * Persists marketing attribution for an active Cart into ps_gtm_cart_attribution.
     * Provides pre-order attribution bridge so Click IDs and UTMs survive across browsing and checkout.
     *
     * @param int $idCart
     * @param array $data
     * @param int $idCustomer
     * @return bool
     */
    public static function saveCartAttribution($idCart, array $data, $idCustomer = 0)
    {
        $idCart = (int)$idCart;
        if ($idCart <= 0 || !class_exists('Db')) {
            return false;
        }

        try {
            self::createDatabaseTable();

            $existing = Db::getInstance()->getRow(
                'SELECT * FROM `' . _DB_PREFIX_ . self::CART_TABLE_NAME . '` WHERE `id_cart` = ' . $idCart
            );

            $now = date('Y-m-d H:i:s');
            $ip = self::getClientIp();
            $userAgent = self::getUserAgent();

            $row = [
                'id_cart' => $idCart,
                'id_customer' => $idCustomer > 0 ? (int)$idCustomer : 0,
                'date_upd' => $now,
            ];

            if (empty($existing)) {
                $row['date_add'] = $now;
                if (!empty($ip)) {
                    $row['customer_ip'] = pSQL($ip);
                }
                if (!empty($userAgent)) {
                    $row['user_agent'] = pSQL(substr($userAgent, 0, 500));
                }
            } else {
                if (empty($row['id_customer']) && !empty($existing['id_customer'])) {
                    $row['id_customer'] = (int)$existing['id_customer'];
                }
            }

            $allFields = array_merge(
                self::$clickParams,
                self::$utmParams,
                array_values(self::$cookieParams)
            );

            foreach ($allFields as $field) {
                if (!empty($data[$field])) {
                    $row[$field] = pSQL($data[$field]);
                } elseif (!empty($existing[$field])) {
                    $row[$field] = pSQL($existing[$field]);
                }
            }

            if (!empty($data['ga_client_id'])) {
                $row['ga_client_id'] = pSQL($data['ga_client_id']);
            } elseif (!empty($existing['ga_client_id'])) {
                $row['ga_client_id'] = pSQL($existing['ga_client_id']);
            }

            if (!empty($data['ga_session_id'])) {
                $row['ga_session_id'] = pSQL($data['ga_session_id']);
            } elseif (!empty($existing['ga_session_id'])) {
                $row['ga_session_id'] = pSQL($existing['ga_session_id']);
            }

            if (!empty($existing)) {
                return (bool)Db::getInstance()->update(self::CART_TABLE_NAME, $row, '`id_cart` = ' . $idCart, 1, true);
            } else {
                return (bool)Db::getInstance()->insert(self::CART_TABLE_NAME, $row, true, false, Db::INSERT);
            }
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Seamlessly copies and promotes cart-level attribution to order-level attribution.
     * Called during order validation (actionValidateOrder) to ensure zero attribution data loss.
     *
     * @param int $idCart
     * @param int $idOrder
     * @param int $idCustomer
     * @return bool
     */
    public static function promoteCartAttributionToOrder($idCart, $idOrder, $idCustomer = 0)
    {
        $idCart = (int)$idCart;
        $idOrder = (int)$idOrder;
        if ($idCart <= 0 || $idOrder <= 0 || !class_exists('Db')) {
            return false;
        }

        try {
            self::createDatabaseTable();

            $cartRow = Db::getInstance()->getRow(
                'SELECT * FROM `' . _DB_PREFIX_ . self::CART_TABLE_NAME . '` WHERE `id_cart` = ' . $idCart
            );

            if (empty($cartRow) || !is_array($cartRow)) {
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $rowToSave = [
                'id_order' => $idOrder,
                'id_cart' => $idCart,
                'id_customer' => $idCustomer > 0 ? (int)$idCustomer : (int)$cartRow['id_customer'],
                'date_add' => !empty($cartRow['date_add']) ? $cartRow['date_add'] : $now,
                'date_upd' => $now,
            ];

            if (!empty($cartRow['customer_ip'])) {
                $rowToSave['customer_ip'] = pSQL($cartRow['customer_ip']);
            }
            if (!empty($cartRow['user_agent'])) {
                $rowToSave['user_agent'] = pSQL($cartRow['user_agent']);
            }

            $allFields = array_merge(
                self::$clickParams,
                self::$utmParams,
                array_values(self::$cookieParams)
            );

            foreach ($allFields as $field) {
                if (!empty($cartRow[$field])) {
                    $rowToSave[$field] = pSQL($cartRow[$field]);
                }
            }

            if (!empty($cartRow['ga_client_id'])) {
                $rowToSave['ga_client_id'] = pSQL($cartRow['ga_client_id']);
            }
            if (!empty($cartRow['ga_session_id'])) {
                $rowToSave['ga_session_id'] = pSQL($cartRow['ga_session_id']);
            }

            return self::upsertOrderAttributionRow($rowToSave);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Persists marketing attribution for an Order into ps_gtm_order_attribution.
     * Automatically pulls and merges any pre-existing cart attribution.
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

            // First: automatically promote cart-level attribution if present
            if (!empty($order->id_cart) && (int)$order->id_cart > 0) {
                self::promoteCartAttributionToOrder((int)$order->id_cart, (int)$order->id, (int)$order->id_customer);
            }

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
                'id_cart' => !empty($order->id_cart) ? (int)$order->id_cart : 0,
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
     * Resolves attribution data for an order by ID, falling back to cart attribution
     * and PrestaShop's native connections_source traffic records if not already in ps_gtm_order_attribution.
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

            // Fallback 1: Check cart attribution table if order object has id_cart
            $order = new Order($idOrder);
            if (Validate::isLoadedObject($order) && !empty($order->id_cart) && (int)$order->id_cart > 0) {
                $cartSql = 'SELECT * FROM `' . _DB_PREFIX_ . self::CART_TABLE_NAME . '` WHERE `id_cart` = ' . (int)$order->id_cart;
                $cartRow = Db::getInstance()->getRow($cartSql);
                if (!empty($cartRow) && is_array($cartRow)) {
                    $cartPayload = self::formatAttributionPayload($cartRow);
                    if (!empty($cartPayload['gclid']) || !empty($cartPayload['fbclid']) || !empty($cartPayload['ttclid']) || !empty($cartPayload['msclkid']) || !empty($cartPayload['sccid']) || !empty($cartPayload['utm_source']) || !empty($cartPayload['utm_campaign'])) {
                        self::promoteCartAttributionToOrder((int)$order->id_cart, $idOrder, (int)$order->id_customer);
                        return $cartPayload;
                    }
                }
            }

            // Fallback 2: Recover from PrestaShop native connections_source (Sources / Quellen)
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
     * Returns structured attribution payload split cleanly into:
     * - click_ids
     * - browser_cookies
     * - utm_parameters
     * - analytics_identifiers
     *
     * @param int $idOrder
     * @return array
     */
    public static function getStructuredAttributionPayload($idOrder)
    {
        $flat = self::getByOrderId($idOrder);
        if (empty($flat)) {
            return [];
        }

        $structured = [
            'click_ids' => [],
            'browser_cookies' => [],
            'utm_parameters' => [],
            'analytics_identifiers' => [],
        ];

        // 1. Click IDs
        foreach (self::$clickParams as $cp) {
            if (!empty($flat[$cp])) {
                $structured['click_ids'][$cp] = $flat[$cp];
            }
        }

        // 2. Browser Cookies
        foreach (self::$cookieParams as $cookieName => $targetKey) {
            if (!empty($flat[$targetKey])) {
                $structured['browser_cookies'][$targetKey] = $flat[$targetKey];
            }
        }

        // 3. UTM Parameters
        foreach (self::$utmParams as $utm) {
            if (!empty($flat[$utm])) {
                $structured['utm_parameters'][$utm] = $flat[$utm];
            }
        }

        // 4. Analytics Identifiers
        if (!empty($flat['ga_client_id'])) {
            $structured['analytics_identifiers']['client_id'] = $flat['ga_client_id'];
        }
        if (!empty($flat['ga_session_id'])) {
            $structured['analytics_identifiers']['session_id'] = $flat['ga_session_id'];
        }

        return $structured;
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
