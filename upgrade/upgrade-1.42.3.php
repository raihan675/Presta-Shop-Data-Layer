<?php
/**
 * Upgrade script for version 1.42.3
 * Updates module title and description with Snapchat Ads and refreshes Snapchat brand icon asset.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_42_3($module)
{
    try {
        // Clear module and template caches to force reload of updated assets
        if (is_object($module)) {
            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
            if (method_exists($module, '_clearCache')) {
                $module->_clearCache('views/templates/admin/configuration.tpl');
                $module->_clearCache('*');
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
