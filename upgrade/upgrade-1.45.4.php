<?php
/**
 * Upgrade script for version 1.45.4
 * Synchronizes end-to-end multi-quantity calculations across add_to_cart, view_cart,
 * begin_checkout, and remove_from_cart with PrestaShop core back office subtotal logic.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_45_4($module)
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
