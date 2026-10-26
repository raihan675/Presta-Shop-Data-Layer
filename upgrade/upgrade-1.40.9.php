<?php
/**
 * Upgrade script for version 1.40.9
 * PrestaShop DataLayer - GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_9($module)
{
    // Clear template caches to force reload of updated event icons and inspect drawer
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/admin/configuration.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
