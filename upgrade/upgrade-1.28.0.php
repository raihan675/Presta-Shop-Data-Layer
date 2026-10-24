<?php
/**
 * Upgrade script for version 1.28.0
 * Universal Backend-Driven Tracking Architecture & Zero DOM Scraping.
 * Eliminates theme-specific DOM queries and flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_28_0($module)
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
