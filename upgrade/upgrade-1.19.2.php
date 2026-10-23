<?php
/**
 * Upgrade script for version 1.19.2
 * Enables Google Ads Dynamic Remarketing and Cart Data tracking on view_search_results
 * with ecomm_pagetype: 'searchresults', search_term, items array, and cache flushing.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_19_2($module)
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
