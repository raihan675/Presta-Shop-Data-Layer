<?php
/**
 * Upgrade script for version 1.8.1
 * Zero-DOM Wishlist Detection & Universal Wishlist Integration Layer.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_8_1($module)
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
