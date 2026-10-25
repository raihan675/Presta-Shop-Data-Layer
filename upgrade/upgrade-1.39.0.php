<?php
/**
 * Upgrade script for version 1.39.0
 * Deploys redesigned Events management layout, slide-out Inspect Drawer,
 * live destination routing controls, unsaved changes tracking, and event active state persistence.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_39_0($module)
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
