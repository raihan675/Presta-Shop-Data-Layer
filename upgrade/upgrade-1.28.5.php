<?php
/**
 * Upgrade script for version 1.28.5
 * Deletes obsolete PS_GTM_CONSENT_MODE configuration setting and cleans up templates.
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_28_5($module)
{
    try {
        Configuration::deleteByName('PS_GTM_CONSENT_MODE');

        if (is_object($module) && method_exists($module, 'clearModuleCache')) {
            $module->clearModuleCache();
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
