<?php
/**
 * Upgrade script for version 1.29.0
 * Registers actionOrderSlipAdd and actionValidateOrder hooks.
 * Creates ps_gtm_refund_log table for strict refund deduplication.
 * Initializes Server-to-Server sGTM Webhook configuration defaults.
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_29_0($module)
{
    try {
        if (is_object($module)) {
            $module->registerHook('actionOrderSlipAdd');
            $module->registerHook('actionValidateOrder');
        }

        require_once _PS_MODULE_DIR_ . 'ps_gtm_datalayer/classes/DeduplicationManager.php';
        DeduplicationManager::createRefundLogTable();

        if (Configuration::get('PS_GTM_WEBHOOK_REFUND_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_WEBHOOK_REFUND_ENABLED', '1');
        }

        if (Configuration::get('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_WEBHOOK_OFFLINE_ORDER_ENABLED', '1');
        }

        if (is_object($module) && method_exists($module, 'clearModuleCache')) {
            $module->clearModuleCache();
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
