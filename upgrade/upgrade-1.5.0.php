<?php
/**
 * Upgrade script for version 1.5.0
 * Universal Architecture Release: PlatformAdapter, PricingEngine & CanonicalDataModel.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_5_0($module)
{
    require_once dirname(__FILE__) . '/../classes/PlatformAdapter.php';
    PlatformAdapter::syncModuleDatabaseVersion($module->name, $module->version);
    PlatformAdapter::clearAllCaches();
    return true;
}
