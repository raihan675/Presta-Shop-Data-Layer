<?php
/**
 * Upgrade script for version 1.19.0
 * Registers Google Ads Conversions with Cart Data and Merchant Center ID settings,
 * establishes separated dynamic remarketing and cart data items schemas,
 * and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_19_0($module)
{
    if (Configuration::get('PS_GTM_ADV_GADS_CART_DATA') === false) {
        Configuration::updateValue('PS_GTM_ADV_GADS_CART_DATA', '1');
    }
    if (Configuration::get('PS_GTM_ADV_GADS_MERCHANT_ID') === false) {
        Configuration::updateValue('PS_GTM_ADV_GADS_MERCHANT_ID', '');
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
