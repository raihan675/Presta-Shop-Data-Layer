<?php
/**
 * Upgrade script for version 1.10.0
 * Adds native catalog-matching namespaces for Meta Pixel/CAPI, TikTok Pixel/Events API,
 * and Google Ads Dynamic Remarketing (Retail).
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_10_0($module)
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
