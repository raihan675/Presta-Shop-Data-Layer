<?php
/**
 * Upgrade script for version 1.45.3
 * Ensures universal empty-cart initial subtotal tracking and multi-tier line calculation
 * so that initial multi-quantity add_to_cart actions strictly reflect authoritative
 * PrestaShop backend cart subtotal calculations.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_45_3($module)
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
