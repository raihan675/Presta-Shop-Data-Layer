<?php
/**
 * Upgrade script for version 1.18.1
 * Fixes remove_from_cart multi-quantity resolution to capture full line item quantity (Zero DOM),
 * ensures all advertising platforms (Meta, Pinterest, TikTok, Google Ads) receive consistent data,
 * and flushes caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_18_1($module)
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
