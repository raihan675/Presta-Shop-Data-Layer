<?php
/**
 * Upgrade script for version 1.37.1
 * Restores 3-card Product Catalog Identifier Strategy interactive selection & descriptions in Configuration tab.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_37_1($module)
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
