<?php
/**
 * Upgrade script for version 1.18.2
 * Separates view_search_results and view_item_list events strictly by user action,
 * eliminates dual-event firing on search pages, removes synthetic list_id/list_name strings,
 * and flushes all caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_18_2($module)
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
