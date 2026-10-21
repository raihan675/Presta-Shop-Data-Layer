<?php
/**
 * Upgrade script for version 1.5.1
 * Accurate Quantity Resolution & Variant Formatting without DOM.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_5_1($module)
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
