<?php
/**
 * Upgrade script for version 1.14.0
 * Introduces official GA4 view_search_results tracking, authoritative search_term and search_results_count sourcing,
 * native ad platform search namespaces (Meta, TikTok, Pinterest), strict exclusion of Google Ads Remarketing, and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_14_0($module)
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
