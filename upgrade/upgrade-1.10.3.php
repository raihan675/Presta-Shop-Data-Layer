<?php
/**
 * Upgrade script for version 1.10.3
 * Displays all 3 catalog strategy options with independent hover tooltips and interactive selection.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_10_3($module)
{
    try {
        if (!Configuration::get('PS_GTM_CATALOG_ID_STRATEGY')) {
            Configuration::updateValue('PS_GTM_CATALOG_ID_STRATEGY', 'composite');
        }

        $adapterPath = dirname(__FILE__) . '/../classes/PlatformAdapter.php';
        if (file_exists($adapterPath)) {
            require_once $adapterPath;
            if (class_exists('PlatformAdapter')) {
                PlatformAdapter::syncModuleDatabaseVersion($module->name, $module->version);
                PlatformAdapter::clearAllCaches();
            }
        }
    } catch (\Throwable $e) {}
    return true;
}
