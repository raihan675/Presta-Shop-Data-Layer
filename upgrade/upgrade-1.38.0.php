<?php
/**
 * Upgrade script for version 1.38.0
 * Deploys comprehensive Back Office typography hierarchy, high-contrast readability overhaul,
 * and fixes module title HTML entity escaping (&amp; to &).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_38_0($module)
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
