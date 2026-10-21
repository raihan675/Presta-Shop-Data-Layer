<?php
/**
 * Upgrade script for version 1.6.0
 * Universal Cart Activity Tracking: Accurate Add/Remove Quantities,
 * Multi-item adjustments, and Zero DOM Cart Delta Engine.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_6_0($module)
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
