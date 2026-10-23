<?php
/**
 * Upgrade script for version 1.17.1
 * Updates Pinterest view_cart event mapping to Custom (custom_event_name: 'ViewCart'),
 * ensuring accurate event distinction from PageVisit, and flushes caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_17_1($module)
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
