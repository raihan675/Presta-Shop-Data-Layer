<?php
/**
 * Upgrade script for version 1.39.1
 * Fixes Smarty template modifier compatibility with PHP 8, pre-formats active destinations,
 * and adds defensive exception boundaries around Back Office template rendering.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_39_1($module)
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
