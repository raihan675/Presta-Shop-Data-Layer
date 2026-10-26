<?php
/**
 * Upgrade script for version 1.40.7
 * PrestaShop DataLayer - GA4, Meta, Pinterest, TikTok, Google & Microsoft Ads
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_40_7($module)
{
    // Automatically purge empty or corrupted PS_GTM_ACTIVE_EVENTS configuration
    if (class_exists('Configuration')) {
        $rawActive = Configuration::get('PS_GTM_ACTIVE_EVENTS');
        if ($rawActive === '[]' || (is_string($rawActive) && empty(json_decode($rawActive, true)))) {
            Configuration::deleteByName('PS_GTM_ACTIVE_EVENTS');
        }
    }

    // Clear template caches to force reload of settings layout and hook templates
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/admin/configuration.tpl');
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
        $module->_clearCache('views/templates/hook/gtm_body.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
