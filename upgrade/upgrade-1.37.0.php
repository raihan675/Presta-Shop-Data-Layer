<?php
/**
 * Upgrade script for version 1.37.0
 * Deploys new 6-Tab Back Office UI/UX Architecture: Overview, Configuration,
 * Events Manager, Platform Routing Matrix, Data Mapping Inspector & Diagnostics.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_37_0($module)
{
    try {
        if (is_object($module)) {
            if (!$module->isRegisteredInHook('displayBackOfficeHeader')) {
                $module->registerHook('displayBackOfficeHeader');
            }
            if (!$module->isRegisteredInHook('displayBackOfficeTop')) {
                $module->registerHook('displayBackOfficeTop');
            }
            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
