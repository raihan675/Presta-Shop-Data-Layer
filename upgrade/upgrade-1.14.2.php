<?php
/**
 * Upgrade script for version 1.14.2
 * Expands search detection to third-party theme search controllers (Warehouse/iqitsearch, Leo Search, Jolisearch),
 * introduces multi-parameter keyword extraction (s, search_query, q, query), adds client-side search discovery safety net, and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_14_2($module)
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
