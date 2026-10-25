<?php
/**
 * Deduplication manager to ensure strict idempotency of ecommerce conversion events.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class DeduplicationManager
{
    const TABLE_NAME = 'gtm_purchase_log';
    const REFUND_TABLE_NAME = 'gtm_refund_log';

    public static function createDatabaseTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` (
            `id_order` INT(11) UNSIGNED NOT NULL,
            `order_reference` VARCHAR(64) NOT NULL,
            `tracked_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_order`),
            INDEX `idx_ref` (`order_reference`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        $refundSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::REFUND_TABLE_NAME . '` (
            `id_order_slip` INT(11) UNSIGNED NOT NULL,
            `id_order` INT(11) UNSIGNED NOT NULL,
            `amount` DECIMAL(20,6) NOT NULL,
            `tracked_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_order_slip`),
            INDEX `idx_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($refundSql);
    }

    public static function createRefundLogTable()
    {
        $refundSql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::REFUND_TABLE_NAME . '` (
            `id_order_slip` INT(11) UNSIGNED NOT NULL,
            `id_order` INT(11) UNSIGNED NOT NULL,
            `amount` DECIMAL(20,6) NOT NULL,
            `tracked_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_order_slip`),
            INDEX `idx_order` (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($refundSql);
    }

    public static function dropDatabaseTable()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '`')
            && Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::REFUND_TABLE_NAME . '`');
    }

    public static function isTransactionTracked($idOrder)
    {
        $sql = 'SELECT 1 FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '` WHERE `id_order` = ' . (int)$idOrder;
        return (bool)Db::getInstance()->getValue($sql);
    }

    public static function markTransactionTracked($idOrder, $reference)
    {
        return Db::getInstance()->insert(self::TABLE_NAME, [
            'id_order' => (int)$idOrder,
            'order_reference' => pSQL($reference),
            'tracked_at' => date('Y-m-d H:i:s'),
        ], false, true, Db::INSERT_IGNORE);
    }

    public static function isRefundTracked($idOrderSlip)
    {
        $sql = 'SELECT 1 FROM `' . _DB_PREFIX_ . self::REFUND_TABLE_NAME . '` WHERE `id_order_slip` = ' . (int)$idOrderSlip;
        return (bool)Db::getInstance()->getValue($sql);
    }

    public static function markRefundTracked($idOrderSlip, $idOrder, $amount)
    {
        return Db::getInstance()->insert(self::REFUND_TABLE_NAME, [
            'id_order_slip' => (int)$idOrderSlip,
            'id_order' => (int)$idOrder,
            'amount' => (float)$amount,
            'tracked_at' => date('Y-m-d H:i:s'),
        ], false, true, Db::INSERT_IGNORE);
    }
}
