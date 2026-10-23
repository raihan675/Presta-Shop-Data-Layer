<?php
/**
 * Upgrade script for version 1.19.1
 * Extends Google Ads cart data items schema across ecommerce interactions
 * including add_to_cart, view_cart, and view_item, and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_19_1($module)
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
