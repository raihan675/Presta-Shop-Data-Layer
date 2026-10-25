<?php
/**
 * Upgrade script for version 1.35.0
 * Ensures robust previous status resolution and explicit refund/cancel retraction classification.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_35_0($module)
{
    try {
        if (is_object($module) && method_exists($module, 'clearModuleCache')) {
            $module->clearModuleCache();
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
