<?php
/**
 * Upgrade script for version 1.5.2
 * Sets transaction_id to internal numeric Order ID for GA4 purchase event.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_5_2($module)
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
