<?php
/**
 * Upgrade script for version 1.9.2
 * Comprehensive audit release: defensive exception handling, controller method guards, path-independent AJAX controller.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_9_2($module)
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
