<?php
/**
 * Upgrade script for version 1.14.3
 * Resolves search results event isolation: suppresses duplicate item array in view_search_results ecommerce payload,
 * prevents false ViewCategory/ViewContent emissions on search-listing view_item_list, preserves Google Ads dynamic remarketing, and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_14_3($module)
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
