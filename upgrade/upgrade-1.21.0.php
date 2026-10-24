<?php
/**
 * Upgrade script for version 1.21.0
 * Initializes Microsoft Advertising (Bing Ads) & UET configuration and flushes caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_21_0($module)
{
    // Initialize Microsoft Advertising platform settings if not present
    if (Configuration::get('PS_GTM_ADV_MICROSOFT_ENABLED') === false) {
        Configuration::updateValue('PS_GTM_ADV_MICROSOFT_ENABLED', '1');
    }

    if (Configuration::get('PS_GTM_ADV_MICROSOFT_EVENTS') === false) {
        $allEvents = array_keys(Ps_Gtm_DataLayer::getSupportedEvents());
        Configuration::updateValue('PS_GTM_ADV_MICROSOFT_EVENTS', json_encode($allEvents));
    }

    // Clear Smarty and module template caches
    if (method_exists($module, '_clearCache')) {
        $module->_clearCache('views/templates/hook/datalayer_init.tpl');
        $module->_clearCache('views/templates/hook/gtm_head.tpl');
        $module->_clearCache('views/templates/hook/gtm_body.tpl');
    }

    if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
        PlatformAdapter::clearAllCaches();
    }

    return true;
}
