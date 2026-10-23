<?php
/**
 * Upgrade script for version 1.13.0
 * Extends universal advertising platform tracking to support official Pinterest Ads (Tag & Conversions API)
 * with catalog-matching line_items, product_ids, value, and event-level routing controls.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_13_0($module)
{
    // Initialize Pinterest configuration settings if not previously set
    if (Configuration::get('PS_GTM_ADV_PINTEREST_ENABLED') === false) {
        Configuration::updateValue('PS_GTM_ADV_PINTEREST_ENABLED', '1');
    }

    if (Configuration::get('PS_GTM_ADV_PINTEREST_EVENTS') === false) {
        $allEvents = array_keys(Ps_Gtm_DataLayer::getSupportedEvents());
        Configuration::updateValue('PS_GTM_ADV_PINTEREST_EVENTS', json_encode($allEvents));
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
