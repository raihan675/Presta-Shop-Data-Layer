<?php
/**
 * Upgrade script for version 1.33.0
 * Adds dedicated PrestaShop Webhook Client support, configurable webhook endpoint path,
 * and universal JSON payload mapping for sGTM.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_33_0($module)
{
    try {
        if (class_exists('Configuration')) {
            if (Configuration::get('PS_SGTM_WEBHOOK_ENDPOINT_PATH') === false) {
                Configuration::updateValue('PS_SGTM_WEBHOOK_ENDPOINT_PATH', '/ps-webhook');
            }
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
