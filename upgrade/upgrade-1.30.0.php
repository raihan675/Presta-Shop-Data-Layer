<?php
/**
 * Upgrade script for version 1.30.0
 * Registers actionOrderStatusPostUpdate hook for universal Back Office status updates.
 * Initializes Server-to-Server Order Status Change Webhook configuration defaults.
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_30_0($module)
{
    try {
        if (is_object($module)) {
            $module->registerHook('actionOrderStatusPostUpdate');
        }

        if (Configuration::get('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED') === false) {
            Configuration::updateValue('PS_GTM_WEBHOOK_STATUS_UPDATE_ENABLED', '1');
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
