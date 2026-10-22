<?php
/**
 * Upgrade script for version 1.10.2
 * Adds interactive hover tooltip cards for Catalog ID Strategy and tooltips for all settings.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_10_2($module)
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
