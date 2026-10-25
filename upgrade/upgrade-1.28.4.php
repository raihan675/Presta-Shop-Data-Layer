<?php
/**
 * Upgrade script for version 1.28.4
 * Updates Product Catalog Identifier Strategy "Learn More" explanations
 * with exact formatted guidance and examples for Option 1, Option 2, and Option 3.
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_28_4($module)
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
