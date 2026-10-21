<?php
/**
 * Upgrade script for version 1.8.2
 * Fixes blockwishlist GraphQL parameter extraction & adds WishlistEventBus integration.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_8_2($module)
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
