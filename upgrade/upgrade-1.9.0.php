<?php
/**
 * Upgrade script for version 1.9.0
 * Universal PrestaShop Pricing Logic: Accurate Backend-Derived Data Layer Values & Financial Reconciliation.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_9_0($module)
{
    try {
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
