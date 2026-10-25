<?php
/**
 * Upgrade script for version 1.31.0
 * Adds self-healing Payment Revocation & Reversal classification to order status updates.
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_31_0($module)
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
