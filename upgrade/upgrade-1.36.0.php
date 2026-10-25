<?php
/**
 * Upgrade script for version 1.36.0
 * Suppresses premature order_status_update webhook on initial checkout order creation.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_36_0($module)
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
