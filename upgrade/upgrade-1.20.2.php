<?php
/**
 * Upgrade script for version 1.20.2
 * Refines customer lifecycle metrics (NCA and CLV) to strictly count authoritative
 * orders with accepted payment (valid = 1), and flushes caches.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_20_2($module)
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
