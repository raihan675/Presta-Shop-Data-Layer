<?php
/**
 * Upgrade script for version 1.17.0
 * Adds TikTok Advanced Matching user_data (SHA-256 hashed email, E.164 phone_number),
 * pre-hydrates customer session data on storefront, and clears caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_17_0($module)
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
