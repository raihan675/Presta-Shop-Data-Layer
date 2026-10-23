<?php
/**
 * Upgrade script for version 1.14.5
 * Updates TikTok advertising namespace to include official standard search_string parameter,
 * maintains backwards-compatible query alias, updates GTM variable mappings, and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_14_5($module)
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
