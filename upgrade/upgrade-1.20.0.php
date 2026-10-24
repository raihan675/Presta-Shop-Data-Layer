<?php
/**
 * Upgrade script for version 1.20.0
 * Adds Google Ads New Customer Acquisition (NCA) and Customer Lifetime Value (CLV)
 * tracking parameters (new_customer, customer_lifetime_value) and flushes caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_20_0($module)
{
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
