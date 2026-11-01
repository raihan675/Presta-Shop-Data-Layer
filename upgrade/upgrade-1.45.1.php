<?php
/**
 * Upgrade script for version 1.45.1
 * Enforces strict canonical GA4 item schema compliance by eliminating line_total
 * under items array across all events, while preserving authoritative PrestaShop
 * ecommerce.value calculations.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_45_1($module)
{
    try {
        if (is_object($module)) {
            if (method_exists($module, 'clearModuleCache')) {
                $module->clearModuleCache();
            }
            if (method_exists($module, '_clearCache')) {
                $module->_clearCache('views/templates/hook/datalayer_init.tpl');
                $module->_clearCache('*');
            }
        }

        if (class_exists('PlatformAdapter') && method_exists('PlatformAdapter', 'clearAllCaches')) {
            PlatformAdapter::clearAllCaches();
        }
    } catch (\Throwable $e) {}

    return true;
}
