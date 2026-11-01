<?php
/**
 * Upgrade script for version 1.45.2
 * Synchronizes cart delta values (add_to_cart, remove_from_cart) with PrestaShop's
 * authoritative core cart subtotal calculations across any quantity removed or added,
 * completely preventing multi-quantity rounding discrepancies against view_cart and back office.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_45_2($module)
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
