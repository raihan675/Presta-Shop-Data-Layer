<?php
/**
 * Upgrade script for version 1.28.2
 * Isolates Product Catalog Identifier Strategy CSS classes (.ps-gtm-strategy-body)
 * to prevent collision with accordion display rules and ensure description visibility.
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_28_2($module)
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
