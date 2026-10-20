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

    public static function createDatabaseTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` (
            `id_order` INT(11) UNSIGNED NOT NULL,
            `order_reference` VARCHAR(64) NOT NULL,
            `tracked_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_order`),
            INDEX `idx_ref` (`order_reference`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    public static function dropDatabaseTable()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '`');
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
}
