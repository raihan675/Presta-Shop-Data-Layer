<?php
/**
 * Upgrade script for version 1.42.1
 * Updates Snapchat icon and refines Back Office navigation menu styling.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_42_1($module)
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
