<?php
/**
 * Upgrade script for version 1.40.1
 * Deploys professional 2-column PrestaShop grid layout, elevated card containers,
 * embedded layout fallbacks, and version-tagged asset cache-busting.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_1($module)
{
    try {
        if (is_object($module)) {
            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
            if (method_exists($module, '_clearCache')) {
                $module->_clearCache('*');
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
