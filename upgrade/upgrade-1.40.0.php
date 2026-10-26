<?php
/**
 * Upgrade script for version 1.40.0
 * Deploys streamlined left-sidebar panel navigation architecture,
 * focusing exclusively on core GTM & DataLayer, Advertising Platform Routing,
 * Server-Side Tracking & Webhooks (sGTM), and Custom Script Injection.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_0($module)
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
