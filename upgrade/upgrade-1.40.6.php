<?php
/**
 * Upgrade script for version 1.40.6
 * PrestaShop DataLayer - GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_6($module)
{
    // Clear template caches to force reload of categorized advertising routing layout
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/admin/configuration.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
