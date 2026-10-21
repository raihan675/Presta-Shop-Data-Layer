<?php
/**
 * Upgrade script for version 1.6.1
 * Category page view_item_list enhancements:
 * Adds currency, value, item-level list identifiers, and dynamic pagination.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_6_1($module)
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
