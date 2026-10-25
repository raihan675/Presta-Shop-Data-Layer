<?php
/**
 * Upgrade script for version 1.34.0
 * Cleans up redundant configuration keys (GA4 Measurement ID and sGTM Preview Header)
 * in favor of dedicated PrestaShop Webhook Client routing and Stape Power-Ups.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_34_0($module)
{
    try {
        if (class_exists('Configuration')) {
            Configuration::deleteByName('PS_SGTM_GA4_MEASUREMENT_ID');
            Configuration::deleteByName('PS_SGTM_PREVIEW_HEADER');
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
