<?php
/**
 * Upgrade script for version 1.28.3
 * Professional Redesign of Settings Cards UI/UX:
 * - 5-tier card hierarchy (Title, Short Desc, Control, Learn More, Expanded Drawer)
 * - Interactive expandable drawers for all Core Settings and Strategy Cards
 * - Keyboard accessibility (Space/Enter) and clean UI states
 * Flushes all PrestaShop caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_28_3($module)
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
